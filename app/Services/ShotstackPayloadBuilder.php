<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Scene;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Converts a {@see Project} into the JSON structure expected by the
 * Shotstack Edit API (`POST /render`).
 *
 * Shotstack layers tracks front-to-back: the FIRST track in the array is the
 * top-most layer, the LAST track is the bottom layer. We therefore emit:
 *
 *   track 0 → caption (rich-text)   — top
 *   track 1 → character (image)     — middle
 *   track 2 → background (image)    — bottom
 *
 * Scenes are laid out sequentially on a shared timeline; each scene's clips
 * `start` at the running total of the preceding scene durations.
 *
 * @see https://shotstack.io/docs/api/
 * @see https://shotstack.io/learn/how-to-position-clips/
 */
class ShotstackPayloadBuilder
{
    /**
     * @return array<string, mixed> ready to be JSON-encoded and POSTed to Shotstack
     */
    public function build(Project $project): array
    {
        $scenes = $project->scenes()
            ->with('sceneCharacter.characterPose')
            ->orderBy('order')
            ->get();

        /** @var list<array<string,mixed>> $captionClips */
        $captionClips = [];
        /** @var list<array<string,mixed>> $characterClips */
        $characterClips = [];
        /** @var list<array<string,mixed>> $backgroundClips */
        $backgroundClips = [];

        $cursor = 0;

        foreach ($scenes as $scene) {
            $length = max(1, (int) $scene->duration_seconds);
            $start = $cursor;

            if ($scene->background_image_path) {
                $backgroundClips[] = [
                    'asset' => ['type' => 'image', 'src' => $this->publicUrl($scene->background_image_path)],
                    'start' => $start,
                    'length' => $length,
                    'fit' => 'cover',
                    'position' => 'center',
                ];
            }

            if ($scene->sceneCharacter?->characterPose) {
                $characterClips[] = $this->characterClip($scene, $start, $length);
            }

            if ($caption = trim((string) $scene->dialogue_text)) {
                $captionClips[] = $this->captionClip($caption, $start, $length);
            }

            $cursor += $length;
        }

        $tracks = array_values(array_filter([
            $captionClips !== [] ? ['clips' => $captionClips] : null,
            $characterClips !== [] ? ['clips' => $characterClips] : null,
            $backgroundClips !== [] ? ['clips' => $backgroundClips] : null,
        ]));

        if ($tracks === []) {
            throw new RuntimeException("Project [{$project->id}] has no renderable scenes.");
        }

        return [
            'timeline' => [
                'background' => config('video.shotstack.timeline_background'),
                'tracks' => $tracks,
            ],
            'output' => [
                'format' => config('video.shotstack.format'),
                'fps' => config('video.shotstack.fps'),
                'size' => [
                    'width' => (int) config('video.canvas.width'),
                    'height' => (int) config('video.canvas.height'),
                ],
            ],
        ];
    }

    public function totalDurationSeconds(Project $project): int
    {
        return (int) $project->scenes()->sum('duration_seconds');
    }

    /**
     * @return array<string, mixed>
     */
    private function characterClip(Scene $scene, int $start, int $length): array
    {
        $link = $scene->sceneCharacter;

        return [
            'asset' => ['type' => 'image', 'src' => $this->publicUrl($link->characterPose->image_path)],
            'start' => $start,
            'length' => $length,
            'fit' => 'none',
            'scale' => round((float) $link->scale, 3),
            'position' => 'center',
            'offset' => [
                // stored positions are percentages (0..100) of the canvas, character-centred;
                // Shotstack offsets run -0.5..0.5 from centre, +y = up.
                'x' => round(((float) $link->position_x - 50) / 100, 4),
                'y' => round((50 - (float) $link->position_y) / 100, 4),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function captionClip(string $text, int $start, int $length): array
    {
        $caption = config('video.shotstack.caption');

        return [
            'asset' => [
                'type' => 'rich-text',
                'text' => $text,
                'font' => [
                    'family' => $caption['font_family'],
                    'size' => (int) $caption['font_size'],
                    'color' => $caption['font_color'],
                ],
                'align' => ['horizontal' => 'center', 'vertical' => 'bottom'],
                'background' => [
                    'color' => $caption['background_color'],
                    'opacity' => (float) $caption['background_opacity'],
                ],
                'width' => max(200, (int) config('video.canvas.width') - (int) $caption['side_margin']),
                'height' => 400,
            ],
            'start' => $start,
            'length' => $length,
            'position' => 'bottom',
            'offset' => ['x' => 0, 'y' => (float) $caption['bottom_offset']],
        ];
    }

    private function publicUrl(string $path): string
    {
        // Shotstack fetches assets over HTTP, so the URL must be absolute and
        // publicly reachable (APP_URL must point at the deployed host).
        return url(Storage::disk(config('video.backgrounds.disk'))->url($path));
    }
}
