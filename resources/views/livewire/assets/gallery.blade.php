<?php

use App\Models\Character;
use App\Services\BackgroundImageService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    #[Validate('required|image|mimes:jpg,jpeg,png|max:10240')]
    public $upload;

    public function save(BackgroundImageService $service): void
    {
        $this->validate();

        $service->store(Auth::user(), $this->upload);

        $this->reset('upload');
        $this->dispatch('background-uploaded');
    }

    public function deleteBackground(int $id, BackgroundImageService $service): void
    {
        $background = Auth::user()->backgroundImages()->findOrFail($id);
        $service->delete($background);
    }

    public function with(): array
    {
        return [
            'backgrounds' => Auth::user()->backgroundImages()->latest()->get(),
            'characters' => Character::query()
                ->availableTo(Auth::user())
                ->with('poses')
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="py-10">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Asset gallery') }}</h2>

    {{-- Upload --}}
    <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
        <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ __('Upload a background') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('JPG or PNG, up to 10 MB. Images are resized to :w px wide for Reels.', ['w' => config('video.backgrounds.max_width')]) }}
        </p>

        <form wire:submit="save" class="mt-4 flex flex-wrap items-center gap-3">
            <input type="file" wire:model="upload" accept="image/jpeg,image/png"
                   class="text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" />
            <button type="submit" wire:loading.attr="disabled" wire:target="upload,save"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
                <span wire:loading.remove wire:target="save">{{ __('Upload') }}</span>
                <span wire:loading wire:target="upload,save">{{ __('Working…') }}</span>
            </button>
        </form>
        @error('upload') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </section>

    {{-- Backgrounds --}}
    <section>
        <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ __('Your backgrounds') }}</h3>
        @if ($backgrounds->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Nothing uploaded yet.') }}</p>
        @else
            <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($backgrounds as $background)
                    <div wire:key="bg-{{ $background->id }}"
                         class="group relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <img src="{{ $background->url() }}" alt="{{ $background->original_name }}"
                             class="aspect-[9/16] w-full object-cover" />
                        <div class="p-2 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $background->original_name }}</div>
                        <button type="button"
                                wire:click="deleteBackground({{ $background->id }})"
                                wire:confirm="{{ __('Delete this background?') }}"
                                class="absolute right-2 top-2 rounded-md bg-black/60 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100">
                            {{ __('Delete') }}
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Characters --}}
    <section>
        <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ __('Available characters') }}</h3>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($characters as $character)
                <div wire:key="char-{{ $character->id }}"
                     class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        @if ($character->thumbnail_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($character->thumbnail_path) }}"
                                 alt="{{ $character->name }}" class="h-12 w-12 rounded-full object-cover bg-gray-100" />
                        @endif
                        <div>
                            <p class="font-medium text-gray-800 dark:text-gray-200">{{ $character->name }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $character->isSystem() ? __('System') : __('Yours') }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($character->poses as $pose)
                            <figure wire:key="pose-{{ $pose->id }}" class="text-center">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($pose->image_path) }}"
                                     alt="{{ $pose->pose_name }}"
                                     class="h-16 w-16 rounded-md border border-gray-100 dark:border-gray-700 object-contain bg-gray-50 dark:bg-gray-900" />
                                <figcaption class="mt-1 text-[11px] text-gray-500">{{ $pose->pose_name }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

  </div>
</div>
