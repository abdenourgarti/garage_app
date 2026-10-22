<?php

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\AdjustStock;
use App\Actions\Stock\CreateArticle;
use App\Actions\Stock\OpenOilCan;
use App\Enums\OilFormat;
use App\Enums\OilUsage;
use App\Enums\StockUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Http\Requests\Stock\ArticleRequest;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $categoryId = $request->integer('category') ?: null;
        $low = $request->boolean('low');
        $oils = $request->boolean('oils');

        $articles = Article::query()
            ->with('category:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")))
            ->when($categoryId, fn ($query) => $query->where('article_category_id', $categoryId))
            ->when($low, fn ($query) => $query->lowStock())
            ->when($oils, fn ($query) => $query->whereNotNull('oil_product_id'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Article $article) => [
                'id' => $article->id,
                'name' => $article->name,
                'reference' => $article->reference,
                'brand' => $article->brand,
                'category' => $article->category->name,
                'stock_qty' => $article->stock_qty,
                'unit' => $article->stock_unit->symbol(),
                'sale_price' => $article->sale_price,
                'stock_status' => $article->stockStatus(),
                'is_oil' => $article->oil_product_id !== null,
                'is_active' => $article->is_active,
            ]);

        return Inertia::render('stock/index', [
            'rows' => $articles,
            'filters' => ['search' => $search, 'category' => $categoryId, 'low' => $low, 'oils' => $oils],
            'lowCount' => Article::active()->lowStock()->count(),
            ...self::formOptions(),
        ]);
    }

    public function show(Article $article): Response
    {
        $article->load(['category:id,name', 'oilProduct.articles']);

        $movements = $article->movements()
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->through(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'created_at' => $movement->created_at->toIso8601String(),
                'type' => $movement->type->label(),
                'quantity' => $movement->quantity,
                'unit_cost' => $movement->unit_cost,
                'reason' => $movement->reason,
                'user' => $movement->user?->name,
                'purchase_id' => $movement->source_type === (new Purchase)->getMorphClass() ? $movement->source_id : null,
            ]);

        $oil = $article->oilProduct;

        return Inertia::render('stock/show', [
            'article' => [
                ...$article->only(['id', 'name', 'brand', 'reference', 'article_category_id', 'purchase_price', 'sale_price', 'stock_qty', 'alert_threshold', 'volume_l', 'is_active']),
                'stock_unit' => $article->stock_unit->value,
                'unit' => $article->stock_unit->symbol(),
                'category' => $article->category->name,
                'stock_status' => $article->stockStatus(),
                'margin' => Decimal::sub($article->sale_price, $article->purchase_price),
                'oil_format' => $article->oil_format?->value,
                'format_label' => $article->oil_format?->label(),
                'can_open' => $article->isCan() && $oil->articles->contains(fn (Article $format) => $format->oil_format === OilFormat::Bulk && $format->is_active),
            ],
            'oil' => $oil ? [
                ...$oil->only(['id', 'brand', 'name', 'viscosity', 'approval', 'is_active']),
                'usage' => $oil->usage->value,
                'usage_label' => $oil->usage->label(),
                'label' => $oil->label(),
                'formats' => $oil->articles->sortBy(fn (Article $format) => array_search($format->oil_format, OilFormat::cases(), true))->values()->map(fn (Article $format) => [
                    'id' => $format->id,
                    'format' => $format->oil_format->value,
                    'label' => $format->oil_format->label(),
                    'purchase_price' => $format->purchase_price,
                    'sale_price' => $format->sale_price,
                    'alert_threshold' => $format->alert_threshold,
                    'stock_qty' => $format->stock_qty,
                    'unit' => $format->stock_unit->symbol(),
                    'is_active' => $format->is_active,
                ]),
            ] : null,
            'movements' => $movements,
            ...self::formOptions(),
        ]);
    }

    public function store(ArticleRequest $request, CreateArticle $action): RedirectResponse
    {
        $article = $action->handle($request->articleData(), $request->validated('initial_stock'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Article ajouté.']);

        return to_route('articles.show', $article);
    }

    public function update(ArticleRequest $request, Article $article): RedirectResponse
    {
        $article->update($request->articleData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Article enregistré.']);

        return back();
    }

    public function toggle(Article $article): RedirectResponse
    {
        $article->update(['is_active' => ! $article->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $article->is_active ? 'Article réactivé.' : 'Article désactivé : il n\'est plus proposé dans les achats et les prestations.']);

        return back();
    }

    public function adjust(AdjustStockRequest $request, Article $article, AdjustStock $action): RedirectResponse
    {
        $action->handle($article, (string) $request->validated('counted_quantity'), $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock corrigé.']);

        return back();
    }

    public function openCan(Article $article, OpenOilCan $action): RedirectResponse
    {
        $bulk = $action->handle($article);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bidon ouvert : +'.Decimal::display($article->volume_l).' L dans le vrac ('.Decimal::display($bulk->fresh()->stock_qty).' L en stock).']);

        return back();
    }

    /**
     * Lists used by the article and oil forms.
     *
     * @return array<string, mixed>
     */
    private static function formOptions(): array
    {
        return [
            'categories' => ArticleCategory::active()->orderBy('name')->get(['id', 'name']),
            'stockUnits' => StockUnit::options(),
            'oilUsages' => OilUsage::options(),
            'oilFormats' => OilFormat::options(),
        ];
    }
}
