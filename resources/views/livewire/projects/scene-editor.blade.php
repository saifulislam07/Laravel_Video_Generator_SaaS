<?php

use App\Models\BackgroundImage;
use App\Models\Character;
use App\Models\CharacterPose;
use App\Models\Scene;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public int $sceneId;

    public ?int $backgroundImageId = null;
    public ?int $characterPoseId = null;
    public string $dialogueText = '';
    public int $durationSeconds = 5;
    public float $posX = 50;
    public float $posY = 68;
    public float $scale = 1.0;

    public bool $justSaved = false;

    public function mount(int $sceneId): void
    {
        $scene = $this->scene();
        $this->authorize('view', $scene->project);

        $this->dialogueText = $scene->dialogue_text ?? '';
        $this->durationSeconds = $scene->duration_seconds;

        if ($scene->background_image_path) {
            $this->backgroundImageId = Auth::user()->backgroundImages()
                ->where('path', $scene->background_image_path)->value('id');
        }

        if ($link = $scene->sceneCharacter) {
            $this->characterPoseId = $link->character_pose_id;
            $this->posX = (float) $link->position_x;
            $this->posY = (float) $link->position_y;
            $this->scale = (float) $link->scale;
        }
    }

    protected function scene(): Scene
    {
        return Scene::with('project', 'sceneCharacter')->findOrFail($this->sceneId);
    }

    protected function rules(): array
    {
        $min = config('video.scene.min_duration_seconds');
        $max = config('video.scene.max_duration_seconds');

        return [
            'backgroundImageId' => ['nullable', 'integer'],
            'characterPoseId' => ['nullable', 'integer'],
            'dialogueText' => ['nullable', 'string', 'max:500'],
            'durationSeconds' => ['required', 'integer', "min:{$min}", "max:{$max}"],
            'posX' => ['numeric', 'between:0,100'],
            'posY' => ['numeric', 'between:0,100'],
            'scale' => ['numeric', 'between:0.3,3'],
        ];
    }

    /** @param  array{posX?:float,posY?:float,scale?:float}  $geometry */
    public function save(array $geometry = []): void
    {
        $this->posX = round((float) ($geometry['posX'] ?? $this->posX), 2);
        $this->posY = round((float) ($geometry['posY'] ?? $this->posY), 2);
        $this->scale = round((float) ($geometry['scale'] ?? $this->scale), 3);

        $this->validate();

        $scene = $this->scene();
        $user = Auth::user();

        $backgroundPath = $this->backgroundImageId
            ? $user->backgroundImages()->whereKey($this->backgroundImageId)->value('path')
            : null;

        $pose = $this->characterPoseId
            ? CharacterPose::whereKey($this->characterPoseId)
                ->whereHas('character', fn ($q) => $q->availableTo($user))
                ->first()
            : null;

        $scene->update([
            'background_image_path' => $backgroundPath,
            'dialogue_text' => $this->dialogueText ?: null,
            'duration_seconds' => $this->durationSeconds,
        ]);

        if ($pose) {
            $scene->sceneCharacter()->updateOrCreate([], [
                'character_pose_id' => $pose->id,
                'position_x' => $this->posX,
                'position_y' => $this->posY,
                'scale' => $this->scale,
            ]);
        } else {
            $scene->sceneCharacter()->delete();
            $this->characterPoseId = null;
        }

        $this->backgroundImageId = $this->backgroundImageId && $backgroundPath ? $this->backgroundImageId : null;
        $this->justSaved = true;
        $this->dispatch('scene-updated');
    }

    public function with(): array
    {
        $user = Auth::user();
        $backgrounds = $user->backgroundImages()->latest()->get();
        $characters = Character::query()->availableTo($user)->with('poses')->orderBy('name')->get();

        $selectedBackground = $this->backgroundImageId
            ? $backgrounds->firstWhere('id', $this->backgroundImageId)
            : null;

        $selectedPose = null;
        foreach ($characters as $character) {
            if ($found = $character->poses->firstWhere('id', $this->characterPoseId)) {
                $selectedPose = $found;
                break;
            }
        }

        return [
            'backgrounds' => $backgrounds,
            'characters' => $characters,
            'selectedBackgroundUrl' => $selectedBackground?->url(),
            'selectedPoseUrl' => $selectedPose?->url(),
        ];
    }
}; ?>

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5"
     x-data="{ x: @js($posX), y: @js($posY), s: @js($scale), dragging: false }">

    <div class="grid gap-6 sm:grid-cols-[16rem_1fr]">

        {{-- Preview stage --}}
        <div>
            <div class="relative mx-auto aspect-[9/16] w-64 overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900 select-none"
                 x-ref="stage"
                 @pointermove="if (dragging) {
                     const r = $refs.stage.getBoundingClientRect();
                     x = Math.min(100, Math.max(0, (($event.clientX - r.left) / r.width) * 100));
                     y = Math.min(100, Math.max(0, (($event.clientY - r.top) / r.height) * 100));
                 }"
                 @pointerup.window="dragging = false"
                 @pointerleave="dragging = false">

                @if ($selectedBackgroundUrl)
                    <img src="{{ $selectedBackgroundUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover" />
                @else
                    <div class="absolute inset-0 flex items-center justify-center text-xs text-gray-400">{{ __('No background') }}</div>
                @endif

                @if ($selectedPoseUrl)
                    <img src="{{ $selectedPoseUrl }}" alt="" draggable="false"
                         class="absolute w-1/2 cursor-move touch-none"
                         :style="`left:${x}%; top:${y}%; transform:translate(-50%,-50%) scale(${s}); transform-origin:center;`"
                         @pointerdown.prevent="dragging = true" />
                @endif

                @if ($dialogueText)
                    <div class="absolute inset-x-0 bottom-0 bg-black/55 p-2 text-center text-[11px] font-medium text-white">
                        {{ \Illuminate\Support\Str::limit($dialogueText, 90) }}
                    </div>
                @endif
            </div>
            @if ($selectedPoseUrl)
                <p class="mt-2 text-center text-[11px] text-gray-400">{{ __('Drag the character on the stage') }}</p>
            @endif
        </div>

        {{-- Controls --}}
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Background') }}</label>
                <select wire:model.live="backgroundImageId"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm">
                    <option value="">{{ __('— none —') }}</option>
                    @foreach ($backgrounds as $bg)
                        <option value="{{ $bg->id }}">{{ $bg->original_name }}</option>
                    @endforeach
                </select>
                @if ($backgrounds->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">
                        {{ __('Upload backgrounds in the') }}
                        <a href="{{ route('assets.gallery') }}" wire:navigate class="underline">{{ __('Asset gallery') }}</a>.
                    </p>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Character pose') }}</label>
                <select wire:model.live="characterPoseId"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm">
                    <option value="">{{ __('— none —') }}</option>
                    @foreach ($characters as $character)
                        <optgroup label="{{ $character->name }}">
                            @foreach ($character->poses as $pose)
                                <option value="{{ $pose->id }}">{{ $character->name }} · {{ $pose->pose_name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div x-show="$wire.characterPoseId">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Scale') }} <span class="text-gray-400" x-text="s.toFixed(2) + '×'"></span>
                </label>
                <input type="range" min="0.3" max="3" step="0.05" x-model.number="s" class="mt-1 w-full" />
                <p class="mt-1 text-xs text-gray-400">
                    {{ __('Position') }}: <span x-text="Math.round(x)"></span>% / <span x-text="Math.round(y)"></span>%
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Dialogue / caption') }}</label>
                <textarea wire:model="dialogueText" rows="3" maxlength="500"
                          class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm"
                          placeholder="{{ __('Shown as an on-screen caption') }}"></textarea>
                @error('dialogueText') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Duration (seconds)') }}</label>
                <input type="number" wire:model="durationSeconds"
                       min="{{ config('video.scene.min_duration_seconds') }}"
                       max="{{ config('video.scene.max_duration_seconds') }}"
                       class="mt-1 w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm" />
                @error('durationSeconds') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="button"
                        @click="$wire.save({ posX: x, posY: y, scale: s })"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    {{ __('Save scene') }}
                </button>
                <span x-show="$wire.justSaved" x-transition x-init="$watch('$wire.justSaved', v => v && setTimeout(() => $wire.justSaved = false, 1500))"
                      class="text-sm text-green-600">{{ __('Saved') }}</span>
            </div>
        </div>
    </div>
</div>
