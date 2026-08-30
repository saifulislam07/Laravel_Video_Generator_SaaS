<?php

use App\Models\BackgroundImage;
use App\Models\Character;
use App\Models\CharacterPose;
use App\Models\Project;
use App\Models\User;
use App\Models\VideoRender;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

/**
 * Walks the core money path end to end:
 * register → create project → add & save a scene → render → credit spent.
 */
it('takes a user from sign-up to a queued render', function () {
    config()->set('services.shotstack.key', 'test-key');
    Bus::fake();
    Http::fake([
        'api.shotstack.io/*' => Http::response(['response' => ['id' => 'e2e-1']], 201),
    ]);

    // register
    Volt::test('pages.auth.register')
        ->set('name', 'Arifull')
        ->set('email', 'ariful@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $user = User::where('email', 'ariful@example.com')->sole();
    expect($user->credits)->toBe(5);
    actingAs($user);

    // asset + project
    $background = BackgroundImage::factory()->for($user)->create();
    $pose = CharacterPose::factory()->for(Character::factory()->system())->create();

    Volt::test('projects.index')
        ->set('title', 'My first reel')
        ->call('create')
        ->assertRedirect();
    $project = Project::where('user_id', $user->id)->sole();

    // add + configure a scene
    Volt::test('projects.builder', ['project' => $project])->call('addScene');
    $scene = $project->scenes()->sole();

    Volt::test('projects.scene-editor', ['sceneId' => $scene->id])
        ->set('backgroundImageId', $background->id)
        ->set('characterPoseId', $pose->id)
        ->set('dialogueText', 'Go for it')
        ->set('durationSeconds', 6)
        ->call('save', ['posX' => 50, 'posY' => 70, 'scale' => 1.1])
        ->assertHasNoErrors();

    expect($scene->fresh()->duration_seconds)->toBe(6)
        ->and($scene->sceneCharacter->character_pose_id)->toBe($pose->id);

    // render from the panel
    Volt::test('projects.render-panel', ['project' => $project])
        ->call('render_')
        ->assertSet('error', null);

    $render = $project->videoRenders()->sole();
    expect($render->status)->toBe(VideoRender::STATUS_QUEUED)
        ->and($render->shotstack_render_id)->toBe('e2e-1')
        ->and($project->fresh()->status)->toBe(Project::STATUS_RENDERING)
        ->and($user->fresh()->credits)->toBe(4);          // one credit spent

    $this->assertDatabaseHas('credit_transactions', [
        'user_id' => $user->id,
        'amount' => -1,
        'reason' => 'video_render',
    ]);
});

it('blocks the render and keeps the credit when the balance is zero', function () {
    config()->set('services.shotstack.key', 'test-key');
    Http::fake();

    $user = User::factory()->broke()->create();
    actingAs($user);

    $project = Project::factory()->for($user)->create();
    \App\Models\Scene::factory()->for($project)->create(['background_image_path' => 'backgrounds/1/a.jpg']);

    Volt::test('projects.render-panel', ['project' => $project])
        ->call('render_')
        ->assertSet('outOfCredits', true);

    expect($project->videoRenders()->count())->toBe(0)
        ->and($user->fresh()->credits)->toBe(0);
    Http::assertNothingSent();
});
