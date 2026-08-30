<?php

namespace App\Models;

use Database\Factories\BackgroundImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'path', 'original_name', 'width', 'height', 'size_bytes'])]
class BackgroundImage extends Model
{
    /** @use HasFactory<BackgroundImageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return Storage::disk(config('video.backgrounds.disk'))->url($this->path);
    }
}
