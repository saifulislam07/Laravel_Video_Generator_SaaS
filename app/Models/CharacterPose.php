<?php

namespace App\Models;

use Database\Factories\CharacterPoseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['character_id', 'pose_name', 'image_path'])]
class CharacterPose extends Model
{
    /** @use HasFactory<CharacterPoseFactory> */
    use HasFactory;

    public function url(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsToMany<Scene> */
    public function scenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class, 'scene_characters')
            ->withPivot(['position_x', 'position_y', 'scale'])
            ->withTimestamps();
    }
}
