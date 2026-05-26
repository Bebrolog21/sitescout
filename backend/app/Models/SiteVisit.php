<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'visit_date',
        'visited_by_user_id',
        'summary',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visited_by_user_id');
    }
}
