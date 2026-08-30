<?php

namespace App\Services;

use App\Models\BackgroundImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class BackgroundImageService
{
    /**
     * Optimise an uploaded background image and persist it for the user.
     *
     * The image is scaled down so its width never exceeds the configured
     * max (aspect ratio preserved) and re-encoded before being written to
     * the public disk under "backgrounds/{userId}/".
     */
    public function store(User $user, UploadedFile $file): BackgroundImage
    {
        $disk = config('video.backgrounds.disk');
        $maxWidth = (int) config('video.backgrounds.max_width');
        $directory = trim(config('video.backgrounds.directory'), '/')."/{$user->id}";

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $extension = in_array($extension, ['jpg', 'jpeg'], true) ? 'jpg' : 'png';
        $relativePath = $directory.'/'.Str::uuid().'.'.$extension;

        $image = Image::decodePath($file->getRealPath());

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $encoded = $image->encodeUsingFileExtension($extension, quality: 82);

        Storage::disk($disk)->put($relativePath, (string) $encoded);

        return $user->backgroundImages()->create([
            'path' => $relativePath,
            'original_name' => $file->getClientOriginalName(),
            'width' => $image->width(),
            'height' => $image->height(),
            'size_bytes' => Storage::disk($disk)->size($relativePath),
        ]);
    }

    public function delete(BackgroundImage $background): void
    {
        Storage::disk(config('video.backgrounds.disk'))->delete($background->path);
        $background->delete();
    }
}
