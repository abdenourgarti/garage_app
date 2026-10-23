<?php

namespace App\Services;

use App\Models\Sequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Numéros continus par année (RG-12) : PR-2026-00001, FA-2026-00001.
 * À appeler dans la transaction de l'Action : le verrou sur le compteur est tenu jusqu'au commit,
 * deux postes simultanés obtiennent donc deux numéros différents et consécutifs.
 */
class NumberingService
{
    public const SERVICE_ORDER = 'service_order';

    public const INVOICE = 'invoice';

    public function serviceOrder(CarbonInterface $date): string
    {
        return $this->next(self::SERVICE_ORDER, 'PR', $date->year);
    }

    public function next(string $sequence, string $prefix, int $year): string
    {
        return DB::transaction(function () use ($sequence, $prefix, $year) {
            // Création sans conflit si deux postes ouvrent la première prestation de l'année en même temps.
            DB::table('sequences')->insertOrIgnore([
                'name' => $sequence,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = Sequence::where('name', $sequence)->where('year', $year)->lockForUpdate()->firstOrFail();
            $counter->increment('last_number');

            return sprintf('%s-%d-%05d', $prefix, $year, $counter->last_number);
        });
    }
}
