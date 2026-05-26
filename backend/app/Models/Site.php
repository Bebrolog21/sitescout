<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'residential_complex_id',
        'title',
        'city',
        'district',
        'address',
        'lat',
        'lng',
        'site_type',
        'area_m2',
        'status',
        'owner_type',
        'contact_name',
        'contact_phone',
        'created_by',
        'site_score',
        'risk_score',
    ];

    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklistValues(): HasMany
    {
        return $this->hasMany(SiteChecklistValue::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    public function finance(): HasOne
    {
        return $this->hasOne(FinancialModel::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }
}
