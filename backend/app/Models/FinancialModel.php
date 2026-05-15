<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'assumptions_json',
        'results_json',
    ];

    protected $casts = [
        'assumptions_json' => 'array',
        'results_json' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
