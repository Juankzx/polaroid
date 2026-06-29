<?php

namespace App\Observers;

use App\Models\Memory;
use Illuminate\Support\Facades\Storage;

class MemoryObserver
{
    /**
     * Al crear o actualizar un recuerdo, intenta leer la fecha EXIF de la foto.
     */
    public function saving(Memory $memory): void
    {
        // Solo auto-detectar si no se puso fecha manualmente y hay imagen
        if (!empty($memory->image_path) && empty($memory->date)) {
            $memory->date = $this->extractDateFromExif($memory->image_path);
        }
    }

    /**
     * Lee los metadatos EXIF de una imagen para extraer la fecha original.
     */
    private function extractDateFromExif(string $imagePath): ?string
    {
        try {
            $fullPath = Storage::disk('public')->path($imagePath);

            if (!file_exists($fullPath)) {
                return null;
            }

            // Intentar leer EXIF (solo funciona con JPEG/TIFF)
            $exif = @exif_read_data($fullPath, 'EXIF', true);

            if ($exif === false) {
                return $this->extractDateFromFilename($imagePath);
            }

            // Buscar la fecha en varios campos EXIF comunes
            $dateFields = [
                $exif['EXIF']['DateTimeOriginal'] ?? null,   // Fecha original de la foto
                $exif['EXIF']['DateTimeDigitized'] ?? null,   // Fecha de digitalización
                $exif['IFD0']['DateTime'] ?? null,             // Fecha de modificación
            ];

            foreach ($dateFields as $dateStr) {
                if ($dateStr) {
                    // EXIF usa formato "2023:06:15 14:30:22"
                    $parsed = \DateTime::createFromFormat('Y:m:d H:i:s', $dateStr);
                    if ($parsed) {
                        return $parsed->format('Y-m-d');
                    }
                }
            }

            // Si no hay EXIF, intentar extraer del nombre del archivo (WhatsApp usa IMG-20230615-WA0001)
            return $this->extractDateFromFilename($imagePath);

        } catch (\Throwable $e) {
            return $this->extractDateFromFilename($imagePath);
        }
    }

    /**
     * Intenta extraer la fecha del nombre del archivo.
     * Funciona con formatos comunes:
     * - WhatsApp: IMG-20230615-WA0001.jpg
     * - iPhone:   IMG_20230615_143022.jpg
     * - General:  2023-06-15_photo.jpg, 20230615.jpg
     */
    private function extractDateFromFilename(string $imagePath): ?string
    {
        $filename = basename($imagePath);

        // Patrón: 8 dígitos seguidos (20230615)
        if (preg_match('/(\d{4})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])/', $filename, $matches)) {
            $year  = (int) $matches[1];
            $month = (int) $matches[2];
            $day   = (int) $matches[3];

            // Validar que sea un año razonable (2000-2030)
            if ($year >= 2000 && $year <= 2030 && checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Patrón: YYYY-MM-DD
        if (preg_match('/(\d{4})[-_](0[1-9]|1[0-2])[-_](0[1-9]|[12]\d|3[01])/', $filename, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        return null;
    }

    /**
     * Al borrar un recuerdo, elimina la foto de Cloudinary para liberar espacio.
     */
    public function deleted(Memory $memory): void
    {
        if (!empty($memory->image_path) && str_contains($memory->image_path, 'cloudinary.com')) {
            try {
                $path = parse_url($memory->image_path, PHP_URL_PATH);
                $parts = explode('/', $path);
                $filename = end($parts);
                $publicId = pathinfo($filename, PATHINFO_FILENAME);
                
                $cloudinaryUrl = env('CLOUDINARY_URL', '');
                if (!$cloudinaryUrl) return;
                
                $parsedUrl = parse_url(str_replace('cloudinary://', 'http://', $cloudinaryUrl));
                $apiKey = $parsedUrl['user'] ?? '';
                $apiSecret = $parsedUrl['pass'] ?? '';
                $cloudName = $parsedUrl['host'] ?? '';
                
                if (!$apiKey || !$apiSecret || !$cloudName) return;

                $timestamp = time();
                $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");
                
                $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'public_id' => $publicId,
                    'api_key' => $apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Throwable $e) {
                // Silencioso por si falla la conexión
            }
        }
    }
}
