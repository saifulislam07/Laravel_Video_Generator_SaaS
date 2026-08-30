<?php

namespace App\Models;

use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['project_id', 'order', 'background_image_path', 'dialogue_text', 'duration_seconds'])]
class Scene extends Model
{
    /** @use HasFactory<SceneFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<SceneCharacter> */
    public function sceneCharacters(): HasMany
    {
        return $this->hasMany(SceneCharacter::class);
    }

    /** The builder UI places a single character per scene. */
    public function sceneCharacter(): HasOne
    {
        return $this->hasOne(SceneCharacter::class);
    }

    public function backgroundUrl(): ?string
    {
        return $this->background_image_path
            ? Storage::disk(config('video.backgrounds.disk'))->url($this->background_image_path)
            : null;
    }

    /** @return BelongsToMany<CharacterPose> */
    public function characterPoses(): BelongsToMany
    {
        return $this->belongsToMany(CharacterPose::class, 'scene_characters')
            ->withPivot(['position_x', 'position_y', 'scale'])
            ->withTimestamps();
    }
}
