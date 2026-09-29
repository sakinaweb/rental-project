<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use SoftDeletes;

    protected $fillable = ['iso_code', 'name', 'currency_code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return HasOne<CountryPack, $this> */
    public function pack(): HasOne
    {
        return $this->hasOne(CountryPack::class);
    }

    /** @return HasMany<City, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /** @param Builder<Country> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
