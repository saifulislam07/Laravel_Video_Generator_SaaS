<?php

use App\Jobs\PublishRenderJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\SocialAccount;
use App\Models\SocialPublication;
use App\Models\User;
use App\Models\VideoRender;
use App\Services\Social\SocialPublisher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

function configureMeta(): void
{
    config()->set('services.facebook', [
        'client_id' => 'app-id', 'client_secret' => 'app-secret',
        'redirect' => 'https://app.test/social/facebook/callback',
        'graph_version' => 'v21.0',
    ]);
}

function doneRenderFor(User $user): VideoRender
{
    $project = Project::factory()->for($user)->create();
    Scene::factory()->for($project)->create(['dialogue_text' => 'Hello world']);

    return VideoRender::factory()->for($project)->done()->create(['output_url' => 'https://cdn/final.mp4']);
}

it('hides connect when Meta is not configured', function () {
    config()->set('services.facebook.client_id', null);
    actingAs(User::factory()->create());

    Volt::test('social.accounts')->assertSet('configured', false)->assertSee('not configured');
});

it('links Facebook pages and their Instagram accounts from the OAuth callback', function () {
    configureMeta();
    $user = User::factory()->create();

    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'short'])
            ->push(['access_token' => 'long', 'expires_in' => 5184000]),
        '*me/accounts*' => Http::response(['data' => [[
            'id' => 'PAGE1', 'name' => 'My Page', 'access_token' => 'page-token',
            'instagram_business_account' => ['id' => 'IG1', 'username' => 'my.brand'],
        ]]]),
    ]);

    actingAs($user)
        ->get(route('social.callback', ['code' => 'abc', 'state' => Crypt::encryptString((string) $user->id)]))
        ->assertRedirect(route('social.index'));

    expect($user->socialAccounts()->pluck('provider')->sort()->values()->all())
        ->toBe(['facebook_page', 'instagram']);

    $ig = $user->socialAccounts()->where('provider', 'instagram')->first();
    expect($ig->provider_account_id)->toBe('IG1')
        ->and($ig->access_token)->toBe('page-token')            // decrypts via cast
        ->and($ig->getRawOriginal('access_token'))->not->toBe('page-token'); // stored encrypted
});

it('rejects a callback whose state is not the current user', function () {
    configureMeta();
    Http::fake();

    actingAs(User::factory()->create())
        ->get(route('social.callback', ['code' => 'x', 'state' => Crypt::encryptString('999999')]))
        ->assertForbidden();
});

it('queues a publish job for a finished render', function () {
    Bus::fake();
    $user = User::factory()->create();
    $render = doneRenderFor($user);
    $account = SocialAccount::factory()->for($user)->create(['provider' => 'facebook_page']);

    app(SocialPublisher::class)->publish($render, $account);

    Bus::assertDispatched(PublishRenderJob::class);
    expect(SocialPublication::where('video_render_id', $render->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('will not publish a render that is not done', function () {
    $user = User::factory()->create();
    $render = VideoRender::factory()->for(Project::factory()->for($user))->create(['status' => 'rendering']);
    $account = SocialAccount::factory()->for($user)->create();

    expect(fn () => app(SocialPublisher::class)->publish($render, $account))
        ->toThrow(RuntimeException::class);
});

it('publishes an Instagram reel through the Graph API', function () {
    $user = User::factory()->create();
    $render = doneRenderFor($user);
    $account = SocialAccount::factory()->for($user)->create([
        'provider' => 'instagram', 'provider_account_id' => 'IG1', 'access_token' => 'tok',
    ]);
    $publication = SocialPublication::create([
        'video_render_id' => $render->id, 'social_account_id' => $account->id, 'status' => 'pending',
    ]);

    Http::fake([
        '*/IG1/media' => Http::response(['id' => 'CONTAINER1']),
        '*/CONTAINER1*' => Http::response(['status_code' => 'FINISHED']),
        '*/IG1/media_publish' => Http::response(['id' => 'MEDIA99']),
    ]);

    app(SocialPublisher::class)->runPublish($publication, 'caption here');

    expect($publication->fresh())
        ->status->toBe('published')
        ->external_id->toBe('MEDIA99');
});

it('marks the publication failed when the Graph API errors', function () {
    $user = User::factory()->create();
    $render = doneRenderFor($user);
    $account = SocialAccount::factory()->for($user)->create(['provider' => 'facebook_page', 'provider_account_id' => 'PAGE1']);
    $publication = SocialPublication::create([
        'video_render_id' => $render->id, 'social_account_id' => $account->id, 'status' => 'pending',
    ]);

    Http::fake(['*/PAGE1/videos' => Http::response(['error' => ['message' => 'nope']], 400)]);

    expect(fn () => app(SocialPublisher::class)->runPublish($publication, 'cap'))->toThrow(RuntimeException::class);
    expect($publication->fresh()->status)->toBe('failed');
});

it('publishes from the dashboard button', function () {
    Bus::fake();
    $user = User::factory()->create();
    $render = doneRenderFor($user);
    $account = SocialAccount::factory()->for($user)->create(['provider' => 'facebook_page']);
    actingAs($user);

    Volt::test('dashboard')
        ->call('publish', $render->id, $account->id)
        ->assertSet('error', null);

    Bus::assertDispatched(PublishRenderJob::class);
});
