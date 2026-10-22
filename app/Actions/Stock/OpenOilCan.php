<?php

namespace App\Actions\Stock;

use App\Enums\OilFormat;
use App\Enums\StockMovementType;
use App\Models\Article;
use App\Services\StockService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Ouvrir un bidon » (RG-10) : −1 bidon, + sa contenance en litres dans le vrac de la même huile.
 */
class OpenOilCan
{
    public function __construct(private StockService $stock) {}

    /**
     * @return Article the bulk article that received the litres
     */
    public function handle(Article $can): Article
    {
        if (! $can->isCan()) {
            throw ValidationException::withMessages(['article' => 'Seul un bidon peut être ouvert.']);
        }

        $bulk = Article::active()
            ->where('oil_product_id', $can->oil_product_id)
            ->where('oil_format', OilFormat::Bulk)
            ->first();

        if (! $bulk) {
            throw ValidationException::withMessages(['article' => 'Cette huile n’a pas de format vrac actif : ajoutez-le dans la fiche de l’huile avant d’ouvrir un bidon.']);
        }

        return DB::transaction(function () use ($can, $bulk) {
            // Relu sous verrou : deux ouvertures simultanées ne doivent pas passer sous zéro.
            $current = Article::whereKey($can->id)->lockForUpdate()->value('stock_qty');

            if (Decimal::cmp($current, 1) < 0) {
                throw ValidationException::withMessages(['article' => 'Aucun bidon en stock : corrigez d’abord le stock.']);
            }

            $litres = $can->volume_l;
            $costPerLitre = Decimal::div($can->purchase_price, $litres);

            $this->stock->out($can, '1', StockMovementType::CanOpenedOut, $can->purchase_price);
            $this->stock->in($bulk, $litres, StockMovementType::CanOpenedIn, $costPerLitre, reason: '1 '.$can->oil_format->inlineLabel().' ouvert');

            return $bulk;
        });
    }
}
