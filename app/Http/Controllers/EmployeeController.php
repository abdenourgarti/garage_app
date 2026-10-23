<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employés (mécaniciens) : CRUD minimal ; les salaires arrivent en phase 10.
 */
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
                ->through(fn (Employee $employee) => $employee->only(['id', 'name', 'position', 'phone', 'is_active'])),
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
