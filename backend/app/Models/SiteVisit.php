<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'visit_date',
        'visited_by_user_id',
        'summary',
    ];
}
