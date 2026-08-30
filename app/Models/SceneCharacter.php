<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scene_id', 'character_pose_id', 'position_x', 'position_y', 'scale'])]
class SceneCharacter extends Model
{
    protected $table = 'scene_characters';

    protected function casts(): array
    {
        return [
            'position_x' => 'float',
            'position_y' => 'float',
            'scale' => 'float',
        ];
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function characterPose(): BelongsTo
    {
        return $this->belongsTo(CharacterPose::class);
    }
}
