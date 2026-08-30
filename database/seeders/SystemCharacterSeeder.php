<?php

namespace Database\Seeders;

use App\Models\Character;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemCharacterSeeder extends Seeder
{
    /**
     * System-default characters. Art is a generated placeholder — replace the
     * PNGs in storage/app/public/characters/system/* once real cartoon art
     * is ready (keep the same file names) and re-run this seeder.
     */
    private const CHARACTERS = [
        ['name' => 'Rumi', 'color' => [99, 102, 241]],   // indigo
        ['name' => 'Bela', 'color' => [236, 72, 153]],    // pink
        ['name' => 'Joy', 'color' => [16, 185, 129]],     // emerald
        ['name' => 'Tuhin', 'color' => [245, 158, 11]],   // amber
    ];

    private const POSES = ['idle', 'smiling', 'surprised'];

    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach (self::CHARACTERS as $definition) {
            $slug = Str::slug($definition['name']) ?: Str::lower(Str::random(6));
            $baseDir = "characters/system/{$slug}";

            $thumbPath = "{$baseDir}/thumbnail.png";
            $disk->put($thumbPath, $this->placeholderPng($definition['name'], 'idle', $definition['color'], 256));

            $character = Character::updateOrCreate(
                ['user_id' => null, 'name' => $definition['name']],
                ['thumbnail_path' => $thumbPath, 'is_public' => true],
            );

            foreach (self::POSES as $pose) {
                $posePath = "{$baseDir}/{$pose}.png";
                $disk->put($posePath, $this->placeholderPng($definition['name'], $pose, $definition['color'], 512));

                $character->poses()->updateOrCreate(
                    ['pose_name' => $pose],
                    ['image_path' => $posePath],
                );
            }
        }
    }

    /**
     * Build a transparent PNG with a simple face whose expression varies by pose.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    private function placeholderPng(string $name, string $pose, array $rgb, int $size): string
    {
        $img = imagecreatetruecolor($size, $size);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        $body = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        $dark = imagecolorallocate($img, 30, 30, 40);
        $white = imagecolorallocate($img, 255, 255, 255);

        $cx = $size / 2;
        $cy = $size / 2;
        $head = (int) ($size * 0.7);

        imagefilledellipse($img, (int) $cx, (int) $cy, $head, $head, $body);

        // eyes — wider when surprised
        $eyeR = $pose === 'surprised' ? (int) ($size * 0.09) : (int) ($size * 0.06);
        $eyeOffsetX = (int) ($size * 0.15);
        $eyeY = (int) ($cy - $size * 0.05);
        imagefilledellipse($img, (int) ($cx - $eyeOffsetX), $eyeY, $eyeR, $eyeR, $white);
        imagefilledellipse($img, (int) ($cx + $eyeOffsetX), $eyeY, $eyeR, $eyeR, $white);
        imagefilledellipse($img, (int) ($cx - $eyeOffsetX), $eyeY, (int) ($eyeR / 2), (int) ($eyeR / 2), $dark);
        imagefilledellipse($img, (int) ($cx + $eyeOffsetX), $eyeY, (int) ($eyeR / 2), (int) ($eyeR / 2), $dark);

        // mouth — smile arc, small O for surprised, flat line for idle
        $mouthY = (int) ($cy + $size * 0.16);
        $mouthW = (int) ($size * 0.3);
        imagesetthickness($img, max(2, (int) ($size * 0.015)));
        if ($pose === 'smiling') {
            imagearc($img, (int) $cx, $mouthY, $mouthW, (int) ($mouthW * 0.8), 20, 160, $dark);
        } elseif ($pose === 'surprised') {
            imagefilledellipse($img, (int) $cx, $mouthY, (int) ($mouthW * 0.35), (int) ($mouthW * 0.45), $dark);
        } else {
            imageline($img, (int) ($cx - $mouthW / 3), $mouthY, (int) ($cx + $mouthW / 3), $mouthY, $dark);
        }

        imagestring($img, 3, 8, $size - 20, "{$name} · {$pose}", $dark);

        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }
}
