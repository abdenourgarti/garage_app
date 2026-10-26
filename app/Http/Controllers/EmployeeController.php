<?php

namespace App\Http\Controllers;

use App\Enums\SalaryType;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\SalaryPayment;
use App\Models\ServiceOrderLine;
use App\Services\PayrollService;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('employees/index', [
            'rows' => Employee::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Employee $employee) => [
                    ...$employee->only(['id', 'name', 'position', 'phone', 'salary_amount', 'is_active']),
                    'salary_type' => $employee->salary_type->value,
                ]),
            'salaryTypes' => SalaryType::options(),
        ]);
    }

    /**
     * Fiche employé, un mois à la fois : travaux réalisés, avances, paies.
     */
    public function show(Request $request, Employee $employee, PayrollService $payroll): Response
    {
        $period = $request->string('period')->toString() ?: now()->format('Y-m');
        $month = PayrollService::month($period);

        $lines = ServiceOrderLine::query()
            ->where('employee_id', $employee->id)
            ->whereHas('serviceOrder', fn ($query) => $query->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()]))
            ->with(['serviceOrder:id,number,date,vehicle_id,status', 'serviceOrder.vehicle:id,plate', 'serviceType:id,name'])
            ->get()
            ->sortByDesc(fn (ServiceOrderLine $line) => $line->serviceOrder->date)
            ->values();

        return Inertia::render('employees/show', [
            'employee' => [
                ...$employee->only(['id', 'name', 'position', 'phone', 'salary_amount', 'is_active']),
                'salary_type' => $employee->salary_type->value,
            ],
            'period' => $period,
            'periodLabel' => PayrollService::label($period),
            'works' => $lines->map(fn (ServiceOrderLine $line) => [
                'id' => $line->id,
                'order_id' => $line->service_order_id,
                'number' => $line->serviceOrder->number,
                'date' => $line->serviceOrder->date->toDateString(),
                'plate' => $line->serviceOrder->vehicle->plate,
                'type' => $line->serviceType->name,
                'labor_price' => $line->labor_price,
            ]),
            'advances' => $payroll->advances($employee, $period)->with('paymentMethod:id,name')->get()->map(fn (Expense $advance) => [
                'id' => $advance->id,
                'amount' => $advance->amount,
                'spent_at' => $advance->spent_at->toDateString(),
                'method' => $advance->paymentMethod?->name,
            ]),
            'paid' => SalaryPayment::where('employee_id', $employee->id)->where('period', $period)->exists(),
            'payments' => $employee->salaryPayments()->limit(24)->get()->map(fn (SalaryPayment $payment) => [
                ...$payment->only(['id', 'period', 'days_worked', 'base_amount', 'advances_total', 'carried_in', 'net_paid']),
                'period_label' => PayrollService::label($payment->period),
                'paid_at' => $payment->paid_at->toDateString(),
            ]),
            ...FormOptions::payment(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employé ajouté.']);

        return back();
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employé enregistré.']);

        return back();
    }

    public function toggle(Employee $employee): RedirectResponse
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $employee->is_active ? 'Employé réactivé.' : 'Employé désactivé : il n\'est plus proposé sur les prestations.']);

        return back();
    }
}
