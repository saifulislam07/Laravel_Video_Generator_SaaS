<?php

use App\Models\Character;
use App\Models\Project;
use App\Models\User;
use App\Models\VideoRender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('blocks non-admins from the admin area', function () {
    actingAs(User::factory()->create());

    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('admin.users'))->assertForbidden();
});

it('lets an admin in', function () {
    actingAs(User::factory()->admin()->create());

    $this->get(route('admin.dashboard'))->assertOk();
});

it('shows key stats on the admin overview', function () {
    $admin = User::factory()->admin()->create();
    Project::factory()->count(2)->create();
    VideoRender::factory()->create();
    VideoRender::factory()->done()->create();
    actingAs($admin);

    Volt::test('admin.dashboard')
        ->assertSee('Total renders')
        ->assertSee('Renders today');
});

describe('user management', function () {
    it('adjusts a user\'s credits and logs it', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->credits(5)->create();
        actingAs($admin);

        Volt::test('admin.users')
            ->call('startAdjust', $user->id)
            ->set('adjustAmount', 20)
            ->set('adjustReason', 'promo')
            ->call('saveAdjust');

        expect($user->fresh()->credits)->toBe(25)
            ->and($user->creditTransactions()->where('reason', 'promo')->exists())->toBeTrue();
    });

    it('rejects a zero adjustment', function () {
        actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        Volt::test('admin.users')
            ->call('startAdjust', $user->id)
            ->set('adjustAmount', 0)
            ->call('saveAdjust')
            ->assertHasErrors('adjustAmount');
    });

    it('promotes and demotes admins but not yourself', function () {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        actingAs($admin);

        $panel = Volt::test('admin.users');
        $panel->call('toggleAdmin', $target->id);
        expect($target->fresh()->isAdmin())->toBeTrue();

        $panel->call('toggleAdmin', $target->id);
        expect($target->fresh()->isAdmin())->toBeFalse();

        $panel->call('toggleAdmin', $admin->id)->assertStatus(403);
        expect($admin->fresh()->isAdmin())->toBeTrue();
    });
});

describe('system character management', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('creates a system character with a thumbnail', function () {
        actingAs(User::factory()->admin()->create());

        Volt::test('admin.characters')
            ->set('newName', 'Nadia')
            ->set('newThumbnail', UploadedFile::fake()->image('n.png', 400, 400))
            ->call('createCharacter')
            ->assertHasNoErrors();

        $character = Character::system()->where('name', 'Nadia')->sole();
        expect($character->is_public)->toBeTrue();
        Storage::disk('public')->assertExists($character->thumbnail_path);
    });

    it('adds and removes poses', function () {
        actingAs(User::factory()->admin()->create());
        $character = Character::factory()->system()->create();

        $panel = Volt::test('admin.characters');
        $panel->call('startPose', $character->id)
            ->set('poseName', 'waving')
            ->set('poseImage', UploadedFile::fake()->image('w.png', 500, 900))
            ->call('savePose')
            ->assertHasNoErrors();

        $pose = $character->poses()->where('pose_name', 'waving')->sole();
        Storage::disk('public')->assertExists($pose->image_path);

        $panel->call('deletePose', $pose->id);
        expect($character->poses()->count())->toBe(0);
    });

    it('deletes a character and its pose files', function () {
        actingAs(User::factory()->admin()->create());
        $character = Character::factory()->system()->create();
        Storage::disk('public')->put('characters/system/x/idle.png', 'x');
        $character->poses()->create(['pose_name' => 'idle', 'image_path' => 'characters/system/x/idle.png']);

        Volt::test('admin.characters')->call('deleteCharacter', $character->id);

        expect(Character::find($character->id))->toBeNull();
        Storage::disk('public')->assertMissing('characters/system/x/idle.png');
    });
});

describe('render moderation', function () {
    it('lists and deletes renders', function () {
        actingAs(User::factory()->admin()->create());
        $render = VideoRender::factory()->done()->create();

        Volt::test('admin.renders')
            ->assertSee($render->project->title)
            ->call('delete', $render->id);

        expect(VideoRender::find($render->id))->toBeNull();
    });
});
