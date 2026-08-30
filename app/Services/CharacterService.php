<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterPose;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class CharacterService
{
    private const DISK = 'public';
    private const MAX_WIDTH = 720; // transparent pose PNGs on the 1080-wide canvas

    public function createSystemCharacter(string $name, ?UploadedFile $thumbnail = null): Character
    {
        $slug = Str::slug($name) ?: Str::lower(Str::random(6));

        return Character::create([
            'user_id' => null,
            'name' => $name,
            'is_public' => true,
            'thumbnail_path' => $thumbnail
                ? $this->storePng($thumbnail, "characters/system/{$slug}/thumbnail.png", 256)
                : null,
        ]);
    }

    public function updateCharacter(Character $character, string $name, ?UploadedFile $thumbnail = null): Character
    {
        $slug = Str::slug($name) ?: $character->id;

        $character->name = $name;

        if ($thumbnail) {
            $this->deleteFile($character->thumbnail_path);
            $character->thumbnail_path = $this->storePng($thumbnail, "characters/system/{$slug}/thumbnail-".Str::random(6).'.png', 256);
        }

        $character->save();

        return $character;
    }

    public function addPose(Character $character, string $poseName, UploadedFile $image): CharacterPose
    {
        $slug = Str::slug($character->name) ?: $character->id;
        $path = "characters/system/{$slug}/".Str::slug($poseName).'-'.Str::random(6).'.png';

        return $character->poses()->updateOrCreate(
            ['pose_name' => $poseName],
            ['image_path' => $this->storePng($image, $path, self::MAX_WIDTH)],
        );
    }

    public function deletePose(CharacterPose $pose): void
    {
        $this->deleteFile($pose->image_path);
        $pose->delete();
    }

    public function deleteCharacter(Character $character): void
    {
        foreach ($character->poses as $pose) {
            $this->deleteFile($pose->image_path);
        }
        $this->deleteFile($character->thumbnail_path);

        $character->delete();
    }

    private function storePng(UploadedFile $file, string $path, int $maxWidth): string
    {
        $image = Image::decodePath($file->getRealPath());

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        Storage::disk(self::DISK)->put($path, (string) $image->encodeUsingFileExtension('png'));

        return $path;
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
