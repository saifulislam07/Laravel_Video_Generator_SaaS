<?php

namespace App\Models;

use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'thumbnail_path', 'is_public'])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CharacterPose> */
    public function poses(): HasMany
    {
        return $this->hasMany(CharacterPose::class);
    }

    /** System characters have no owner. */
    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    /** @param  Builder<Character>  $query */
    public function scopeSystem(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /** @param  Builder<Character>  $query */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /** Characters available to a given user: their own + public/system ones. */
    public function scopeAvailableTo(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id)
            ->orWhereNull('user_id')
            ->orWhere('is_public', true);
    }
}
