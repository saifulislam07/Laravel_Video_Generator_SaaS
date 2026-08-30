<?php

use App\Models\BackgroundImage;
use App\Models\Character;
use App\Models\CharacterPose;
use App\Models\Project;
use App\Models\Scene;
use App\Models\SceneCharacter;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

it('creates a project and redirects into the builder', function () {
    $user = User::factory()->create();
    actingAs($user);

    Volt::test('projects.index')
        ->set('title', 'Motivation Monday')
        ->call('create')
        ->assertRedirect();

    $project = Project::query()->sole();
    expect($project->title)->toBe('Motivation Monday')
        ->and($project->user_id)->toBe($user->id)
        ->and($project->status)->toBe(Project::STATUS_DRAFT);
});

it('validates the project title', function () {
    actingAs(User::factory()->create());

    Volt::test('projects.index')
        ->set('title', 'ab')
        ->call('create')
        ->assertHasErrors('title');
});

it('only lets the owner open the builder', function () {
    $project = Project::factory()->create();
    actingAs(User::factory()->create());

    Volt::test('projects.builder', ['project' => $project])
        ->assertForbidden();
});

it('adds, reorders and deletes scenes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    actingAs($user);

    $component = Volt::test('projects.builder', ['project' => $project]);
    $component->call('addScene')->call('addScene')->call('addScene');

    $scenes = $project->scenes()->pluck('id', 'order');
    expect($scenes)->toHaveCount(3);

    // move the 3rd scene to the front
    $third = $project->scenes()->where('order', 3)->value('id');
    $component->call('moveScene', $third, 0);

    expect($project->scenes()->orderBy('order')->first()->id)->toBe($third);

    // delete the first, remaining scenes re-indexed to 1..2
    $component->call('deleteScene', $third);
    expect($project->scenes()->pluck('order')->all())->toBe([1, 2]);
});

it('saves scene background, character, caption and duration', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $scene = Scene::factory()->for($project)->create(['background_image_path' => null, 'dialogue_text' => null]);
    $background = BackgroundImage::factory()->for($user)->create();
    $pose = CharacterPose::factory()->for(Character::factory()->system())->create();

    actingAs($user);

    Volt::test('projects.scene-editor', ['sceneId' => $scene->id])
        ->set('backgroundImageId', $background->id)
        ->set('characterPoseId', $pose->id)
        ->set('dialogueText', 'Keep going.')
        ->set('durationSeconds', 7)
        ->call('save', ['posX' => 40, 'posY' => 80, 'scale' => 1.4])
        ->assertHasNoErrors();

    $scene->refresh();
    expect($scene->background_image_path)->toBe($background->path)
        ->and($scene->dialogue_text)->toBe('Keep going.')
        ->and($scene->duration_seconds)->toBe(7);

    $link = $scene->sceneCharacter;
    expect($link->character_pose_id)->toBe($pose->id)
        ->and((float) $link->position_x)->toBe(40.0)
        ->and((float) $link->scale)->toBe(1.4);
});

it('removes the character link when the pose is cleared', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $scene = Scene::factory()->for($project)->create();
    $pose = CharacterPose::factory()->for(Character::factory()->system())->create();
    SceneCharacter::create([
        'scene_id' => $scene->id, 'character_pose_id' => $pose->id,
        'position_x' => 50, 'position_y' => 50, 'scale' => 1,
    ]);

    actingAs($user);

    Volt::test('projects.scene-editor', ['sceneId' => $scene->id])
        ->set('characterPoseId', null)
        ->call('save')
        ->assertHasNoErrors();

    expect($scene->sceneCharacter()->exists())->toBeFalse();
});

it('rejects an out-of-range duration', function () {
    $user = User::factory()->create();
    $scene = Scene::factory()->for(Project::factory()->for($user))->create();
    actingAs($user);

    Volt::test('projects.scene-editor', ['sceneId' => $scene->id])
        ->set('durationSeconds', 999)
        ->call('save')
        ->assertHasErrors('durationSeconds');
});

it('shows the preview timeline with a total duration', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Scene::factory()->for($project)->create(['order' => 1, 'duration_seconds' => 5]);
    Scene::factory()->for($project)->create(['order' => 2, 'duration_seconds' => 8]);

    actingAs($user);

    Volt::test('projects.timeline', ['project' => $project])
        ->assertOk()
        ->assertSee('13s total');
});
