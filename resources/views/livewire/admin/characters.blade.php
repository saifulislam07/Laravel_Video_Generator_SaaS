<?php

use App\Models\Character;
use App\Models\CharacterPose;
use App\Services\CharacterService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
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

<div class="py-10">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Admin') }}</h2>
    <x-admin-nav />

    <form wire:submit="createCharacter" class="mb-8 rounded-lg bg-white dark:bg-gray-800 p-5 shadow-sm">
        <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ __('New system character') }}</h3>
        <div class="mt-3 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                <input type="text" wire:model="newName" class="mt-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500">{{ __('Thumbnail (PNG, optional)') }}</label>
                <input type="file" wire:model="newThumbnail" accept="image/png" class="mt-1 text-sm" />
            </div>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Create') }}</button>
        </div>
        @error('newName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('newThumbnail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </form>

    <div class="space-y-6">
        @forelse ($characters as $character)
            <div wire:key="c-{{ $character->id }}" class="rounded-lg bg-white dark:bg-gray-800 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        @if ($character->thumbnail_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($character->thumbnail_path) }}"
                                 class="h-12 w-12 rounded-full bg-gray-100 object-cover" alt="" />
                        @endif
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $character->name }}</p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <button type="button" wire:click="startPose({{ $character->id }})" class="text-indigo-600 hover:text-indigo-500">{{ __('Add pose') }}</button>
                        <button type="button" wire:click="deleteCharacter({{ $character->id }})"
                                wire:confirm="{{ __('Delete this character and all its poses?') }}"
                                class="text-red-600 hover:text-red-500">{{ __('Delete') }}</button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($character->poses as $pose)
                        <figure wire:key="p-{{ $pose->id }}" class="relative text-center">
                            <img src="{{ $pose->url() }}" alt="{{ $pose->pose_name }}"
                                 class="h-20 w-20 rounded-md border border-gray-100 dark:border-gray-700 object-contain bg-gray-50 dark:bg-gray-900" />
                            <figcaption class="mt-1 text-[11px] text-gray-500">{{ $pose->pose_name }}</figcaption>
                            <button type="button" wire:click="deletePose({{ $pose->id }})"
                                    class="absolute -right-1 -top-1 rounded-full bg-red-600 px-1.5 text-xs text-white">&times;</button>
                        </figure>
                    @endforeach
                </div>

                @if ($poseCharacterId === $character->id)
                    <div class="mt-4 flex flex-wrap items-end gap-3 rounded-md bg-indigo-50/50 dark:bg-indigo-900/10 p-3">
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Pose name') }}</label>
                            <input type="text" wire:model="poseName" placeholder="idle / smiling / surprised"
                                   class="mt-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Transparent PNG') }}</label>
                            <input type="file" wire:model="poseImage" accept="image/png" class="mt-1 text-sm" />
                        </div>
                        <button type="button" wire:click="savePose" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Save pose') }}</button>
                        <button type="button" wire:click="$set('poseCharacterId', null)" class="text-sm text-gray-500">{{ __('Cancel') }}</button>
                        @error('poseName') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('poseImage') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400">{{ __('No system characters yet.') }}</p>
        @endforelse
    </div>
  </div>
</div>
