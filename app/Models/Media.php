<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'collection',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function isStored(): bool
    {
        return $this->path !== null && Storage::disk($this->disk)->exists($this->path);
    }
}
