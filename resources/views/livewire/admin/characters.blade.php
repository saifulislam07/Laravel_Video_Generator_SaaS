<?php

use App\Models\Character;
use App\Models\CharacterPose;
use App\Services\CharacterService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin'), Title('Characters')] class extends Component
{
    use WithFileUploads;

    #[Validate('required|string|min:2|max:60')]
    public string $newName = '';

    #[Validate('nullable|image|mimes:png|max:4096')]
    public $newThumbnail = null;

    public ?int $poseCharacterId = null;
    #[Validate('required|string|max:40')]
    public string $poseName = '';
    #[Validate('required|image|mimes:png|max:8192')]
    public $poseImage = null;

    public function createCharacter(CharacterService $service): void
    {
        $this->validateOnly('newName');
        $this->validateOnly('newThumbnail');

        $service->createSystemCharacter($this->newName, $this->newThumbnail);

        $this->reset('newName', 'newThumbnail');
    }

    public function deleteCharacter(int $id, CharacterService $service): void
    {
        $character = Character::system()->findOrFail($id);
        $service->deleteCharacter($character);
    }

    public function startPose(int $characterId): void
    {
        $this->poseCharacterId = $characterId;
        $this->reset('poseName', 'poseImage');
        $this->resetErrorBag();
    }

    public function savePose(CharacterService $service): void
    {
        $this->validateOnly('poseName');
        $this->validateOnly('poseImage');

        $character = Character::system()->findOrFail($this->poseCharacterId);
        $service->addPose($character, $this->poseName, $this->poseImage);

        $this->reset('poseCharacterId', 'poseName', 'poseImage');
    }

    public function deletePose(int $id, CharacterService $service): void
    {
        $pose = CharacterPose::whereHas('character', fn ($q) => $q->system())->findOrFail($id);
        $service->deletePose($pose);
    }

    public function with(): array
    {
        return [
            'characters' => Character::system()->with('poses')->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <h1 class="mb-4">System characters</h1>

    <div class="card">
        <div class="card-header"><h3 class="card-title">New character</h3></div>
        <div class="card-body">
            <form wire:submit="createCharacter" class="form-row align-items-end">
                <div class="col-auto">
                    <label>Name</label>
                    <input type="text" wire:model="newName" class="form-control">
                    @error('newName') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-auto">
                    <label>Thumbnail (PNG, optional)</label>
                    <input type="file" wire:model="newThumbnail" accept="image/png" class="form-control-file">
                    @error('newThumbnail') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    @forelse ($characters as $character)
        <div class="card" wire:key="c-{{ $character->id }}">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    @if ($character->thumbnail_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($character->thumbnail_path) }}"
                             class="img-circle mr-2" width="40" height="40" alt="">
                    @endif
                    <strong>{{ $character->name }}</strong>
                </div>
                <div>
                    <button type="button" wire:click="startPose({{ $character->id }})" class="btn btn-xs btn-outline-primary">Add pose</button>
                    <button type="button" wire:click="deleteCharacter({{ $character->id }})"
                            wire:confirm="Delete this character and all its poses?"
                            class="btn btn-xs btn-outline-danger">Delete</button>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap" style="gap: .75rem">
                    @foreach ($character->poses as $pose)
                        <figure class="text-center position-relative m-0" wire:key="p-{{ $pose->id }}">
                            <img src="{{ $pose->url() }}" alt="{{ $pose->pose_name }}"
                                 class="img-thumbnail" width="80" height="80" style="object-fit: contain">
                            <figcaption class="small text-muted">{{ $pose->pose_name }}</figcaption>
                            <button type="button" wire:click="deletePose({{ $pose->id }})"
                                    class="btn btn-danger btn-xs position-absolute" style="top:-6px; right:-6px; border-radius:50%">&times;</button>
                        </figure>
                    @endforeach
                </div>

                @if ($poseCharacterId === $character->id)
                    <div class="mt-3 p-3 bg-light rounded form-row align-items-end">
                        <div class="col-auto">
                            <label>Pose name</label>
                            <input type="text" wire:model="poseName" class="form-control form-control-sm" placeholder="idle / smiling / surprised">
                        </div>
                        <div class="col-auto">
                            <label>Transparent PNG</label>
                            <input type="file" wire:model="poseImage" accept="image/png" class="form-control-file">
                        </div>
                        <div class="col-auto">
                            <button type="button" wire:click="savePose" class="btn btn-sm btn-primary">Save pose</button>
                            <button type="button" wire:click="$set('poseCharacterId', null)" class="btn btn-sm btn-link">Cancel</button>
                        </div>
                        @error('poseName') <div class="col-12 text-danger small">{{ $message }}</div> @enderror
                        @error('poseImage') <div class="col-12 text-danger small">{{ $message }}</div> @enderror
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="text-muted">No system characters yet.</p>
    @endforelse
</div>
