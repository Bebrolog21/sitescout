<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'kind',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        $path = (string) $this->attributes['path'];

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public function isExternalLink(): bool
    {
        return Str::startsWith((string) $this->attributes['path'], ['http://', 'https://']);
    }
}
