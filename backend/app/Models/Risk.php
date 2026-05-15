<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Risk extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'type',
        'severity',
        'probability',
        'description',
        'mitigation',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
