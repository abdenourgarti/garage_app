<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres du garage (table settings), mis en cache jusqu'à la prochaine écriture.
 */
class SettingsService
{
    public const GARAGE_NAME = 'garage_name';

    public const GARAGE_ADDRESS = 'garage_address';

    public const GARAGE_PHONE = 'garage_phone';

    /** Chemin du logo sur le disque « local » (storage/app/private). */
    public const GARAGE_LOGO = 'garage_logo';

    public const REMINDER_THRESHOLD_KM = 'reminder_threshold_km';

    /**
     * @var array<string, string|null>
     */
    private const DEFAULTS = [
        self::GARAGE_NAME => null,
        self::GARAGE_ADDRESS => null,
        self::GARAGE_PHONE => null,
        self::GARAGE_LOGO => null,
        self::REMINDER_THRESHOLD_KM => '1000',
    ];

    private const CACHE_KEY = 'settings';

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => [...self::DEFAULTS, ...Setting::pluck('value', 'key')->all()],
        );
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    public function reminderThresholdKm(): int
    {
        return (int) $this->get(self::REMINDER_THRESHOLD_KM);
    }

    /**
     * @param  array<string, string|int|null>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
