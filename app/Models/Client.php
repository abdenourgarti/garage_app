<?php

namespace App\Models;

use App\Models\Concerns\Referential;
use App\Support\Normalize;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $phone_normalized
 * @property int|null $wilaya_id
 * @property string|null $notes
 * @property bool $is_active
 * @property-read string $full_name
 */
#[Fillable(['first_name', 'last_name', 'phone', 'wilaya_id', 'notes', 'is_active'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, Referential;

    /**
     * @var list<string>
     */
    protected $appends = ['full_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Writing the phone also writes its normalized form (unique, searchable).
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => [
            'phone' => trim((string) $value),
            'phone_normalized' => Normalize::phone($value),
        ]);
    }

    /**
     * « Benali Karim » : nom puis prénom, comme sur les papiers algériens.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->last_name.' '.$this->first_name));
    }

    /**
     * Every word in the last or first name (« Karim Benali » or « Benali Karim »),
     * or — when the term has digits — the phone or one of its plates (start).
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);

        $query->where(function (Builder $query) use ($term, $words) {
            $query->where(function (Builder $query) use ($words) {
                foreach ($words as $word) {
                    $query->where(fn (Builder $query) => $query
                        ->where('last_name', 'like', "%{$word}%")
                        ->orWhere('first_name', 'like', "%{$word}%"));
                }
            });

            if (preg_match('/\d/', $term)) {
                $phone = Normalize::phone($term);
                $plate = Normalize::plate($term);

                $query->orWhere('phone_normalized', 'like', "{$phone}%")
                    ->orWhereHas('vehicles', fn (Builder $query) => $query->where('plate_normalized', 'like', "{$plate}%"));
            }
        });
    }

    /**
     * @return BelongsTo<Wilaya, $this>
     */
    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    /**
     * Vehicles currently owned.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<VehicleOwnership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(VehicleOwnership::class);
    }
}
