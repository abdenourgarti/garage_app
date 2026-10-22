<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReferenceRequest;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paramètres > Référentiels : un seul écran pour les 9 référentiels (voir ReferenceType).
 */
class ReferenceController extends Controller
{
    public function index(Request $request, ReferenceType $type): Response
    {
        $search = trim($request->string('search')->toString());
        $brandId = $type === ReferenceType::VehicleModels ? ($request->integer('brand') ?: null) : null;

        $rows = $type->model()::query()
            ->when($type === ReferenceType::VehicleModels, fn (Builder $query) => $query->with('brand:id,name'))
            ->when($brandId, fn (Builder $query) => $query->where('brand_id', $brandId))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($type, $search) {
                foreach ($type->searchColumns() as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            }))
            // Modèles : regroupés par marque (sinon « 100 » d'Audi côtoie « 1007 » de Peugeot).
            ->when($type === ReferenceType::VehicleModels, fn (Builder $query) => $query->orderBy(
                Brand::select('name')->whereColumn('brands.id', 'vehicle_models.brand_id'),
            ))
            ->orderBy($type->orderColumn())
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/references', [
            'type' => $type->value,
            'types' => ReferenceType::options(),
            'rows' => $rows,
            'filters' => ['search' => $search, 'brand' => $brandId],
            'brands' => $type === ReferenceType::VehicleModels
                ? Brand::orderBy('name')->get(['id', 'name', 'is_active'])
                : [],
        ]);
    }

    public function store(ReferenceRequest $request, ReferenceType $type): RedirectResponse
    {
        $type->model()::create($request->validated());

        return $this->done('Élément ajouté.');
    }

    public function update(ReferenceRequest $request, ReferenceType $type, int $id): RedirectResponse
    {
        $item = $type->model()::findOrFail($id);

        if ($item->isLocked()) {
            return $this->locked();
        }

        $item->update($request->validated());

        return $this->done('Modifications enregistrées.');
    }

    public function toggle(ReferenceType $type, int $id): RedirectResponse
    {
        $item = $type->model()::findOrFail($id);

        if ($item->isLocked()) {
            return $this->locked();
        }

        $item->update(['is_active' => ! $item->is_active]);

        return $this->done($item->is_active ? 'Élément activé.' : 'Élément désactivé.');
    }

    public function destroy(ReferenceType $type, int $id): RedirectResponse
    {
        $item = $type->model()::findOrFail($id);

        if ($item->isLocked()) {
            return $this->locked();
        }

        try {
            $item->delete();
        } catch (QueryException $exception) {
            // 23000 : contrainte de clé étrangère (restrictOnDelete) → l'élément est utilisé.
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return $this->error('Cet élément est utilisé. Désactivez-le plutôt.');
        }

        return $this->done('Élément supprimé.');
    }

    private function locked(): RedirectResponse
    {
        return $this->error('Cette catégorie est utilisée par le logiciel : elle ne peut être ni modifiée, ni désactivée, ni supprimée.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function error(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
