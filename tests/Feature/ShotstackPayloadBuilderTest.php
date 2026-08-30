<?php

use App\Models\Character;
use App\Models\CharacterPose;
use App\Models\Project;
use App\Models\Scene;
use App\Models\SceneCharacter;
use App\Services\ShotstackPayloadBuilder;

function sampleProject(): Project
{
    $project = Project::factory()->create();

    $pose = CharacterPose::factory()->for(Character::factory()->system())->create([
        'image_path' => 'characters/system/rumi/smiling.png',
    ]);

    $one = Scene::factory()->for($project)->create([
        'order' => 1,
        'background_image_path' => 'backgrounds/1/beach.jpg',
        'dialogue_text' => 'Rise and grind',
        'duration_seconds' => 5,
    ]);
    SceneCharacter::create([
        'scene_id' => $one->id, 'character_pose_id' => $pose->id,
        'position_x' => 50, 'position_y' => 75, 'scale' => 1.2,
    ]);

    Scene::factory()->for($project)->create([
        'order' => 2,
        'background_image_path' => 'backgrounds/1/city.jpg',
        'dialogue_text' => null,
        'duration_seconds' => 8,
    ]);

    return $project;
}

it('produces a 1080x1920 mp4 output', function () {
    $payload = app(ShotstackPayloadBuilder::class)->build(sampleProject());

    expect($payload['output'])->toMatchArray([
        'format' => 'mp4',
        'size' => ['width' => 1080, 'height' => 1920],
    ])->and($payload['output']['fps'])->toBe(30);
});

it('orders tracks caption → character → background (top to bottom)', function () {
    $payload = app(ShotstackPayloadBuilder::class)->build(sampleProject());

    $tracks = $payload['timeline']['tracks'];
    expect($tracks)->toHaveCount(3)
        ->and($tracks[0]['clips'][0]['asset']['type'])->toBe('rich-text')
        ->and($tracks[1]['clips'][0]['asset']['type'])->toBe('image')
        ->and($tracks[1]['clips'][0]['fit'])->toBe('none')      // character
        ->and($tracks[2]['clips'][0]['fit'])->toBe('cover');    // background
});

it('sequences scene clips along the timeline', function () {
    $payload = app(ShotstackPayloadBuilder::class)->build(sampleProject());

    $backgrounds = $payload['timeline']['tracks'][2]['clips'];
    expect($backgrounds)->toHaveCount(2)
        ->and($backgrounds[0]['start'])->toBe(0)
        ->and($backgrounds[0]['length'])->toBe(5)
        ->and($backgrounds[1]['start'])->toBe(5)
        ->and($backgrounds[1]['length'])->toBe(8);

    // only scene 1 has a caption
    expect($payload['timeline']['tracks'][0]['clips'])->toHaveCount(1);
});

it('converts stored percentage positions to Shotstack offsets', function () {
    $payload = app(ShotstackPayloadBuilder::class)->build(sampleProject());

    $character = $payload['timeline']['tracks'][1]['clips'][0];
    // position_x 50 → centre → 0 ; position_y 75 → below centre → -0.25
    expect($character['offset'])->toBe(['x' => 0.0, 'y' => -0.25])
        ->and($character['scale'])->toBe(1.2);
});

it('emits absolute asset URLs that Shotstack can fetch', function () {
    $payload = app(ShotstackPayloadBuilder::class)->build(sampleProject());

    $src = $payload['timeline']['tracks'][2]['clips'][0]['asset']['src'];
    expect($src)->toMatch('#^https?://.+/storage/backgrounds/1/beach\.jpg$#');
});

it('returns a JSON-encodable structure', function () {
    $payload = app(ShotstackPayloadBuilder::class)->build(sampleProject());

    $json = json_encode($payload);
    expect($json)->toBeString()
        ->and(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and(json_decode($json, true))->toEqual($payload);
});

it('throws when the project has nothing to render', function () {
    $project = Project::factory()->create();
    Scene::factory()->for($project)->create([
        'background_image_path' => null, 'dialogue_text' => null,
    ]);

    app(ShotstackPayloadBuilder::class)->build($project);
})->throws(RuntimeException::class);

it('reports the total duration', function () {
    expect(app(ShotstackPayloadBuilder::class)->totalDurationSeconds(sampleProject()))->toBe(13);
});
