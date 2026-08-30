<?php

use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\VideoRender;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

it('redirects guests to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('lists the user\'s projects with their render state', function () {
    $user = User::factory()->create();
    $completed = Project::factory()->for($user)->completed()->create(['title' => 'Done reel']);
    VideoRender::factory()->for($completed)->done()->create(['output_url' => 'https://cdn.shotstack.io/final.mp4']);

    $failed = Project::factory()->for($user)->failed()->create(['title' => 'Broken reel']);
    VideoRender::factory()->for($failed)->failed()->create(['error_message' => 'asset missing']);

    Project::factory()->create(['title' => 'Someone else reel']); // other user

    actingAs($user);

    Volt::test('dashboard')
        ->assertSee('Done reel')
        ->assertSee('https://cdn.shotstack.io/final.mp4')
        ->assertSee('Broken reel')
        ->assertSee('asset missing')
        ->assertDontSee('Someone else reel');
});

it('retries a failed render', function () {
    Bus::fake();
    Http::fake(['*' => Http::response(['response' => ['id' => 'retry-1']], 201)]);
    config()->set('services.shotstack.key', 'test-key');

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->failed()->create();
    Scene::factory()->for($project)->create(['background_image_path' => 'backgrounds/1/a.jpg']);
    VideoRender::factory()->for($project)->failed()->create();

    actingAs($user);

    Volt::test('dashboard')
        ->call('retry', $project->id)
        ->assertSet('error', null)
        ->assertSee('Render restarted');

    expect($project->videoRenders()->count())->toBe(2)
        ->and($project->fresh()->status)->toBe(Project::STATUS_RENDERING);
});

it('shows a helpful error when retrying without an api key', function () {
    config()->set('services.shotstack.key', null);
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->failed()->create();
    Scene::factory()->for($project)->create(['background_image_path' => 'backgrounds/1/a.jpg']);

    actingAs($user);

    Volt::test('dashboard')
        ->call('retry', $project->id)
        ->assertSee('SHOTSTACK_API_KEY');
});

it('does not let a user retry another user\'s project', function () {
    $project = Project::factory()->failed()->create();
    actingAs(User::factory()->create());

    expect(fn () => Volt::test('dashboard')->call('retry', $project->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect($project->fresh()->status)->toBe(Project::STATUS_FAILED);
});
