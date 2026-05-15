<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResidentialComplex extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'district',
        'address',
        'lat',
        'lng',
        'developer_name',
        'notes',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
