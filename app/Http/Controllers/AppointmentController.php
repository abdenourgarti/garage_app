<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\StartAppointment;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ServiceType;
use App\Support\FormOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rendez-vous (M2) : jour (par défaut), semaine (lundi → dimanche), mois ; création rapide, Commencer, Annuler.
 */
class AppointmentController extends Controller
{
    private const VIEWS = ['day', 'week', 'month'];

    public function index(Request $request): Response
    {
        $view = in_array($request->string('view')->toString(), self::VIEWS, true) ? $request->string('view')->toString() : 'day';
        $date = rescue(fn () => CarbonImmutable::parse($request->string('date')->toString()), null, false);
        $date = $request->filled('date') && $date ? $date->startOfDay() : CarbonImmutable::today();

        [$from, $to, $step] = match ($view) {
            'day' => [$date, $date, '1 day'],
            'week' => [$date->startOfWeek(), $date->endOfWeek()->startOfDay(), '1 week'],
            'month' => [$date->startOfMonth(), $date->endOfMonth()->startOfDay(), '1 month'],
        };

        return Inertia::render('appointments/index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'previous' => $date->sub($step)->toDateString(),
            'next' => $date->add($step)->toDateString(),
            // Mois : seulement le nombre de rendez-vous non annulés par jour.
            'days' => $view === 'month'
                ? self::between($from, $to)->where('status', '!=', AppointmentStatus::Cancelled)
                    ->selectRaw('date(scheduled_at) as day, count(*) as count')->groupBy('day')->pluck('count', 'day')->map(fn ($count) => (int) $count)
                : null,
            'appointments' => $view === 'month' ? [] : self::list($from, $to),
            'serviceTypes' => ServiceType::active()->withCount('lines')->orderByDesc('lines_count')->orderBy('name')->get(['id', 'name']),
            ...FormOptions::clientVehicle(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $appointment = Appointment::create([...Arr::except($data, 'service_type_ids'), 'user_id' => Auth::id()]);
            $appointment->serviceTypes()->sync($data['service_type_ids']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rendez-vous enregistré.']);

        return back();
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->ensurePlanned($appointment);
        $data = $this->validated($request);

        DB::transaction(function () use ($appointment, $data) {
            $appointment->update(Arr::except($data, 'service_type_ids'));
            $appointment->serviceTypes()->sync($data['service_type_ids']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rendez-vous modifié.']);

        return back();
    }

    public function start(Appointment $appointment, StartAppointment $start): RedirectResponse
    {
        $order = $start->handle($appointment);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Prestation {$order->number} commencée : complétez le kilométrage et les pièces."]);

        return to_route('service-orders.edit', $order);
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        DB::transaction(function () use ($appointment) {
            $this->ensurePlanned(Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail());
            $appointment->forceFill(['status' => AppointmentStatus::Cancelled])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rendez-vous annulé.']);

        return back();
    }

    /**
     * Rendez-vous de la période, dans l'ordre de l'heure (aussi pour le tableau de bord).
     *
     * @return list<array<string, mixed>>
     */
    public static function list(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return self::between($from, $to)
            ->with(['client:id,first_name,last_name,phone', 'vehicle.brand:id,name', 'vehicle.vehicleModel:id,name', 'serviceTypes:id,name', 'serviceOrder:id,number'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'day' => $appointment->scheduled_at->toDateString(),
                'time' => $appointment->scheduled_at->format('H:i'),
                'status' => $appointment->status->value,
                'notes' => $appointment->notes,
                'client' => ['value' => (string) $appointment->client->id, 'label' => $appointment->client->full_name, 'description' => $appointment->client->phone],
                'vehicle' => $appointment->vehicle ? [
                    'value' => (string) $appointment->vehicle->id,
                    'label' => $appointment->vehicle->plate,
                    'description' => $appointment->vehicle->label(),
                ] : null,
                'services' => $appointment->serviceTypes->sortBy('name')->map(fn (ServiceType $type) => ['id' => $type->id, 'name' => $type->name])->values(),
                'order' => $appointment->serviceOrder?->only(['id', 'number']),
            ])
            ->all();
    }

    /**
     * @return Builder<Appointment>
     */
    private static function between(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Appointment::query()
            ->where('scheduled_at', '>=', $from->startOfDay())
            ->where('scheduled_at', '<', $to->addDay()->startOfDay());
    }

    private function ensurePlanned(Appointment $appointment): void
    {
        if (! $appointment->isPlanned()) {
            throw ValidationException::withMessages(['appointment' => 'Ce rendez-vous est déjà '.mb_strtolower($appointment->status->label()).'.']);
        }
    }

    /**
     * @return array{client_id: int, vehicle_id: int|null, scheduled_at: string, notes: string|null, service_type_ids: list<int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('client_id', $request->integer('client_id'))],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'service_type_ids' => ['array'],
            'service_type_ids.*' => ['integer', Rule::exists('service_types', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'vehicle_id.exists' => 'Ce véhicule n’appartient pas à ce client.',
        ], [
            'client_id' => 'client',
            'vehicle_id' => 'véhicule',
            'date' => 'date',
            'time' => 'heure',
            'service_type_ids' => 'prestations prévues',
            'notes' => 'remarque',
        ]);

        return [
            'client_id' => (int) $data['client_id'],
            'vehicle_id' => isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            'scheduled_at' => CarbonImmutable::parse($data['date'].' '.$data['time'])->toDateTimeString(),
            'notes' => $data['notes'] ?? null,
            'service_type_ids' => array_values(array_unique(array_map('intval', $data['service_type_ids'] ?? []))),
        ];
    }
}
