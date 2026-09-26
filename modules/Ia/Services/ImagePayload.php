<?php

namespace Modules\Ia\Services;

class ImagePayload
{
    /**
     * Prépare un fichier image pour Gemini : réduit à `image_max_side` px et ré-encode en JPEG
     * (les photos de téléphone font plusieurs Mo, inutiles pour l'identification).
     *
     * @return array{mime: string, data: string}
     */
    public static function fromPath(string $path): array
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw new GeminiException("Impossible de lire l'image envoyée.");
        }

        $source = function_exists('imagecreatefromstring') ? @imagecreatefromstring($raw) : false;

        if ($source === false) {
            throw new GeminiException("Format d'image non pris en charge (utilisez JPEG, PNG ou WebP).");
        }

        // Applique l'orientation EXIF (photos de téléphone prises en portrait).
        if (function_exists('exif_read_data') && ($exif = @exif_read_data($path)) && isset($exif['Orientation'])) {
            $source = match ($exif['Orientation']) {
                3 => imagerotate($source, 180, 0),
                6 => imagerotate($source, -90, 0),
                8 => imagerotate($source, 90, 0),
                default => $source,
            };
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = config('bonbon.gemini.image_max_side');

        if (max($width, $height) > $max) {
            $ratio = $max / max($width, $height);
            $source = imagescale($source, (int) round($width * $ratio), (int) round($height * $ratio));
        }

        ob_start();
        imagejpeg($source, null, 85);
        $jpeg = ob_get_clean();

        return ['mime' => 'image/jpeg', 'data' => base64_encode($jpeg)];
    }
}
