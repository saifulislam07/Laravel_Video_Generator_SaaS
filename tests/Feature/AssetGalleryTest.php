<?php

use App\Models\BackgroundImage;
use App\Models\Character;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
});

it('requires auth to view the gallery', function () {
    $this->get(route('assets.gallery'))->assertRedirect(route('login'));
});

it('renders the gallery for an authed user', function () {
    actingAs(User::factory()->create());

    $this->get(route('assets.gallery'))->assertOk();
});

it('uploads, optimises and stores a background image', function () {
    $user = User::factory()->create();
    actingAs($user);

    $file = UploadedFile::fake()->image('beach.jpg', 4000, 6000);

    Volt::test('assets.gallery')
        ->set('upload', $file)
        ->call('save')
        ->assertHasNoErrors();

    $background = BackgroundImage::query()->where('user_id', $user->id)->sole();

    expect($background->width)->toBe(config('video.backgrounds.max_width'))
        ->and($background->original_name)->toBe('beach.jpg');

    Storage::disk('public')->assertExists($background->path);
});

it('rejects non-image uploads', function () {
    actingAs(User::factory()->create());

    Volt::test('assets.gallery')
        ->set('upload', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('upload');
});

it('rejects files larger than 10 MB', function () {
    actingAs(User::factory()->create());

    Volt::test('assets.gallery')
        ->set('upload', UploadedFile::fake()->image('huge.jpg')->size(11 * 1024))
        ->call('save')
        ->assertHasErrors('upload');
});

it('lets a user delete only their own background', function () {
    $user = User::factory()->create();
    $other = BackgroundImage::factory()->create();
    actingAs($user);

    $file = UploadedFile::fake()->image('mine.png', 800, 1200);
    Volt::test('assets.gallery')->set('upload', $file)->call('save');
    $mine = BackgroundImage::query()->where('user_id', $user->id)->sole();

    Volt::test('assets.gallery')->call('deleteBackground', $mine->id);
    expect(BackgroundImage::find($mine->id))->toBeNull();

    expect(fn () => Volt::test('assets.gallery')->call('deleteBackground', $other->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(BackgroundImage::find($other->id))->not->toBeNull();
});

it('shows system characters to every user', function () {
    $this->seed(Database\Seeders\SystemCharacterSeeder::class);
    $user = User::factory()->create();
    actingAs($user);

    $available = Character::query()->availableTo($user)->get();

    expect($available)->toHaveCount(4)
        ->and($available->every(fn ($c) => $c->isSystem()))->toBeTrue();
});
