<?php

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\SaveOilProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\OilProductRequest;
use App\Models\OilProduct;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OilProductController extends Controller
{
    public function store(OilProductRequest $request, SaveOilProduct $action): RedirectResponse
    {
        $oil = $action->handle(null, $request->oilData(), $request->integer('article_category_id'), $request->formats());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Huile ajoutée avec ses formats.']);

        return to_route('articles.show', $oil->articles()->orderBy('id')->firstOrFail());
    }

    public function update(OilProductRequest $request, OilProduct $oilProduct, SaveOilProduct $action): RedirectResponse
    {
        $action->handle($oilProduct, $request->oilData(), $request->integer('article_category_id'), $request->formats());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Huile enregistrée.']);

        return back();
    }
}
