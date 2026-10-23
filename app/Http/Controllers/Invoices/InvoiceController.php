<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Invoices\ApplyDiscount;
use App\Actions\Invoices\CancelInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PartOrigin;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceOrderLine;
use App\Models\ServiceOrderPart;
use App\Services\SettingsService;
use App\Support\Decimal;
use App\Support\FormOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $status = InvoiceStatus::tryFrom($request->string('status')->toString());
        $from = $request->date('from');
        $to = $request->date('to');

        $invoices = Invoice::query()
            ->with(['client:id,first_name,last_name', 'serviceOrder:id,vehicle_id', 'serviceOrder.vehicle:id,plate'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($query) => $query->search($search))
                ->orWhereHas('serviceOrder.vehicle', fn ($query) => $query->search($search))))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($from, fn ($query) => $query->whereDate('issued_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('issued_at', '<=', $to))
            ->latest('issued_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => self::summary($invoice));

        return Inertia::render('invoices/index', [
            'rows' => $invoices,
            'filters' => ['search' => $search, 'status' => $status?->value, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'statuses' => InvoiceStatus::options(),
        ]);
    }

    /**
     * Crédits clients : les plus anciennes dettes d'abord.
     */
    public function credits(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $clients = Client::query()
            ->whereHas('invoices', fn (Builder $query) => $query->outstanding())
            ->withSum(['invoices as due' => fn (Builder $query) => $query->outstanding()], 'total')
            ->withSum(['invoices as due_paid' => fn (Builder $query) => $query->outstanding()], 'amount_paid')
            ->withCount(['invoices as open_invoices' => fn (Builder $query) => $query->outstanding()])
            ->withMin(['invoices as oldest' => fn (Builder $query) => $query->outstanding()], 'issued_at')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->orderBy('oldest')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'phone' => $client->phone,
                'credit' => Decimal::sub((string) $client->due, (string) $client->due_paid),
                'open_invoices' => $client->open_invoices,
                'oldest' => substr((string) $client->oldest, 0, 10),
                'days' => (int) Carbon::parse($client->oldest)->startOfDay()->diffInDays(today()),
            ]);

        return Inertia::render('invoices/credits', [
            'rows' => $clients,
            'filters' => ['search' => $search],
            'total' => Decimal::sub((string) Invoice::outstanding()->sum('total'), (string) Invoice::outstanding()->sum('amount_paid')),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['client:id,first_name,last_name,phone', 'serviceOrder:id,number,vehicle_id', 'serviceOrder.vehicle:id,plate,brand_id,vehicle_model_id', 'serviceOrder.vehicle.brand:id,name', 'serviceOrder.vehicle.vehicleModel:id,name', 'payments.paymentMethod:id,name', 'payments.user:id,name', 'cancelledBy:id,name']);

        return Inertia::render('invoices/show', [
            'invoice' => [
                ...self::summary($invoice),
                'labor_total' => $invoice->labor_total,
                'parts_total' => $invoice->parts_total,
                'discount' => $invoice->discount,
                'client' => $invoice->client->only(['id', 'full_name', 'phone']),
                'order' => ['id' => $invoice->serviceOrder->id, 'number' => $invoice->serviceOrder->number],
                'vehicle_id' => $invoice->serviceOrder->vehicle_id,
                'vehicle_label' => $invoice->serviceOrder->vehicle->label(),
                'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
                'cancel_reason' => $invoice->cancel_reason,
                'cancelled_by' => $invoice->cancelledBy?->name,
                'payments' => $invoice->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->toIso8601String(),
                    'method' => $payment->paymentMethod->name,
                    'user' => $payment->user->name,
                    'has_proof' => $payment->proof_path !== null,
                    'cancelled' => $payment->cancelled_at !== null,
                ]),
                'can_pay' => ! $invoice->isCancelled() && Decimal::cmp($invoice->remaining(), 0) > 0,
                'can_discount' => ! $invoice->isCancelled() && $invoice->payments->isEmpty(),
                'can_cancel' => ! $invoice->isCancelled() && Gate::allows('cancel-invoices'),
            ],
            ...FormOptions::payment(),
        ]);
    }

    public function discount(Request $request, Invoice $invoice, ApplyDiscount $action): RedirectResponse
    {
        $validated = $request->validate(['discount' => ['required', 'numeric', 'min:0', 'max:9999999999']], [], ['discount' => 'remise']);

        $action->handle($invoice, (string) $validated['discount']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Remise enregistrée.']);

        return back();
    }

    public function cancel(Request $request, Invoice $invoice, CancelInvoice $action): RedirectResponse
    {
        Gate::authorize('cancel-invoices');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motif']);

        $action->handle($invoice, $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Facture {$invoice->number} annulée : la prestation est de nouveau En cours."]);

        return back();
    }

    /**
     * Facture imprimable (A4 ou ticket 80 mm) ou PDF A4 : une seule mise en page pour les trois.
     */
    public function document(Request $request, Invoice $invoice, string $format, SettingsService $settings): HttpResponse
    {
        $invoice->load(['client', 'serviceOrder.vehicle.brand', 'serviceOrder.vehicle.vehicleModel', 'serviceOrder.lines.serviceType', 'serviceOrder.lines.parts']);
        $order = $invoice->serviceOrder;

        // shortcut: les lignes viennent de la prestation (figée tant que la facture est active) ; une facture annulée
        // puis une prestation corrigée réaffiche les nouvelles lignes avec les anciens totaux. Copier les lignes dans la facture si cela gêne.
        $rows = $order->lines->flatMap(fn (ServiceOrderLine $line) => [
            [
                'label' => "Main-d'œuvre : {$line->serviceType->name}".($line->description ? " ({$line->description})" : ''),
                'quantity' => null,
                'unit_price' => null,
                'amount' => $line->labor_price,
            ],
            ...$line->parts->map(fn (ServiceOrderPart $part) => [
                'label' => $part->designation.($part->origin === PartOrigin::Client ? ' (fournie par le client)' : ''),
                'quantity' => Decimal::display($part->quantity),
                'unit_price' => $part->origin === PartOrigin::Client ? null : $part->unit_price,
                'amount' => $part->origin === PartOrigin::Client ? null : $part->line_total,
            ]),
        ])->all();

        return self::render('documents.invoice', $format, "facture-{$invoice->number}", $request->boolean('print'), [
            'invoice' => $invoice,
            'order' => $order,
            'rows' => $rows,
        ], $settings);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, string $format, string $filename, bool $autoPrint, array $data, SettingsService $settings): HttpResponse
    {
        abort_unless(in_array($format, ['a4', 'ticket', 'pdf'], true), 404);

        $logo = $settings->get(SettingsService::GARAGE_LOGO);
        $garage = [
            'name' => $settings->get(SettingsService::GARAGE_NAME),
            'address' => $settings->get(SettingsService::GARAGE_ADDRESS),
            'phone' => $settings->get(SettingsService::GARAGE_PHONE),
            // En data URI : lisible par le navigateur comme par dompdf, sans accès réseau.
            'logo' => $logo && Storage::disk('local')->exists($logo)
                ? 'data:'.Storage::disk('local')->mimeType($logo).';base64,'.base64_encode(Storage::disk('local')->get($logo))
                : null,
        ];

        $data = [...$data, 'garage' => $garage, 'format' => $format, 'autoPrint' => $autoPrint && $format !== 'pdf'];

        if ($format === 'pdf') {
            // Seuls les caractères utilisés de la police sont intégrés : un PDF léger à envoyer par WhatsApp.
            return Pdf::loadView($view, $data)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->download("{$filename}.pdf");
        }

        return response()->view($view, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'issued_at' => $invoice->issued_at->toDateString(),
            'status' => $invoice->status->value,
            'client_name' => $invoice->client->full_name,
            'plate' => $invoice->serviceOrder->vehicle->plate,
            'total' => $invoice->total,
            'amount_paid' => $invoice->amount_paid,
            'remaining' => $invoice->remaining(),
        ];
    }

    /**
     * Factures d'un client (onglet de la fiche client).
     *
     * @return list<array<string, mixed>>
     */
    public static function forClient(Client $client): array
    {
        return $client->invoices()
            ->with(['client:id,first_name,last_name', 'serviceOrder:id,vehicle_id', 'serviceOrder.vehicle:id,plate'])
            ->latest('issued_at')
            ->latest('id')
            ->get()
            ->map(fn (Invoice $invoice) => self::summary($invoice))
            ->all();
    }
}
