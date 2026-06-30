<?php

namespace App\Filament\Resources\Memories\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MemoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Título de la Foto')
                    ->required(),
                \Filament\Forms\Components\Select::make('category')
                    ->label('Categoría')
                    ->options([
                        'Viajes ✈️' => 'Viajes ✈️',
                        'Fechas Especiales 🎂' => 'Fechas Especiales 🎂',
                        'Momentos Divertidos 🤪' => 'Momentos Divertidos 🤪',
                        'Logros 🏆' => 'Logros 🏆',
                        'Momentos Random 📸' => 'Momentos Random 📸',
                    ])
                    ->default('Momentos Random 📸')
                    ->required(),
                Textarea::make('description')
                    ->label('Dedicatoria / Descripción')
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Sube tu Foto')
                    ->disk('cloudinary')
                    ->formatStateUsing(function ($state) {
                        if ($state && is_string($state) && str_starts_with($state, 'http')) {
                            $parts = explode('/', parse_url($state, PHP_URL_PATH));
                            return end($parts);
                        }
                        return $state;
                    })
                    ->image()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, \Filament\Forms\Components\FileUpload $component) {
                        if (!$state) {
                            \Illuminate\Support\Facades\Log::info("No state on file upload");
                            return;
                        }

                        // Obtener el archivo temporal real
                        $files = $component->getState();
                        $file = is_array($files) ? reset($files) : $files;
                        
                        // Si por alguna razón sigue siendo un string, usamos el string como filename para intentar sacar la fecha (ej: WhatsApp).
                        if (!($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) && !($file instanceof \Illuminate\Http\UploadedFile)) {
                            $filename = is_string($state) ? $state : '';
                            if ($filename && preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $matches)) {
                                $year  = (int) $matches[1];
                                $month = (int) $matches[2];
                                $day   = (int) $matches[3];
                                if ($year >= 2000 && $year <= 2030 && checkdate($month, $day, $year)) {
                                    $set('date', sprintf('%04d-%02d-%02d', $year, $month, $day));
                                    \Illuminate\Support\Facades\Log::info("Date set from string filename: " . sprintf('%04d-%02d-%02d', $year, $month, $day));
                                }
                            }
                            return;
                        }

                        try {
                            $path = $file->getRealPath();
                            $filename = $file->getClientOriginalName();
                            \Illuminate\Support\Facades\Log::info("Analyzing uploaded file: " . $filename . " at path " . $path);
                            
                            $exif = @exif_read_data($path, 'EXIF', true);
                            \Illuminate\Support\Facades\Log::info("EXIF data result: " . ($exif !== false ? 'Found' : 'Not Found'));

                            // 1. Extraer Fecha
                            if ($exif !== false) {
                                $dateStr = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
                                if ($dateStr) {
                                    $parsed = \DateTime::createFromFormat('Y:m:d H:i:s', $dateStr);
                                    if ($parsed) {
                                        $set('date', $parsed->format('Y-m-d'));
                                        \Illuminate\Support\Facades\Log::info("Date set from EXIF: " . $parsed->format('Y-m-d'));
                                    }
                                }

                                // 2. Extraer GPS
                                if (isset($exif['GPS'])) {
                                    $gps = $exif['GPS'];
                                    if (isset($gps['GPSLatitude'], $gps['GPSLongitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitudeRef'])) {
                                        $lat = self::getGps($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
                                        $lng = self::getGps($gps['GPSLongitude'], $gps['GPSLongitudeRef']);

                                        // Geocoding inverso — Nominatim primero, BigDataCloud como fallback
                                        $locationFound = null;

                                        // Intento 1: Nominatim (OpenStreetMap)
                                        try {
                                            $response = Http::timeout(8)->withHeaders([
                                                'User-Agent' => 'PolaroidMemories/2.0 (contact@example.com)',
                                                'Accept-Language' => 'es',
                                            ])->get("https://nominatim.openstreetmap.org/reverse", [
                                                'lat' => $lat, 'lon' => $lng, 'format' => 'json',
                                            ]);
                                            if ($response->successful()) {
                                                $addr = $response->json()['address'] ?? [];
                                                $city    = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? '';
                                                $country = $addr['country'] ?? '';
                                                $parts   = array_filter([$city, $country]);
                                                if (!empty($parts)) $locationFound = implode(', ', $parts);
                                            }
                                        } catch (\Throwable $e) {
                                            \Illuminate\Support\Facades\Log::warning("[Nominatim] " . $e->getMessage());
                                        }

                                        // Intento 2: BigDataCloud (sin API key, más tolerante con servidores cloud)
                                        if (!$locationFound) {
                                            try {
                                                $response2 = Http::timeout(8)->get(
                                                    "https://api.bigdatacloud.net/data/reverse-geocode-client",
                                                    ['latitude' => $lat, 'longitude' => $lng, 'localityLanguage' => 'es']
                                                );
                                                if ($response2->successful()) {
                                                    $json2   = $response2->json();
                                                    $city    = $json2['city'] ?? $json2['locality'] ?? $json2['principalSubdivision'] ?? '';
                                                    $country = $json2['countryName'] ?? '';
                                                    $parts   = array_filter([$city, $country]);
                                                    if (!empty($parts)) $locationFound = implode(', ', $parts);
                                                }
                                            } catch (\Throwable $e) {
                                                \Illuminate\Support\Facades\Log::warning("[BigDataCloud] " . $e->getMessage());
                                            }
                                        }

                                        if ($locationFound) {
                                            $set('location', $locationFound);
                                            \Illuminate\Support\Facades\Log::info("Location set from GPS: " . $locationFound);
                                        }
                                    }
                                }
                            } else {
                                // Fallback a leer del nombre del archivo
                                if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $matches)) {
                                    $year  = (int) $matches[1];
                                    $month = (int) $matches[2];
                                    $day   = (int) $matches[3];
                                    if ($year >= 2000 && $year <= 2030 && checkdate($month, $day, $year)) {
                                        $set('date', sprintf('%04d-%02d-%02d', $year, $month, $day));
                                        \Illuminate\Support\Facades\Log::info("Date set from filename: " . sprintf('%04d-%02d-%02d', $year, $month, $day));
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error("EXIF Error: " . $e->getMessage());
                        }
                    }),
                DatePicker::make('date')
                    ->label('Fecha del Recuerdo')
                    ->helperText('Se auto-rellenará mágicamente si subes una foto original con fecha 🕰️'),
                TextInput::make('location')
                    ->label('Lugar del Recuerdo')
                    ->helperText('Se auto-rellenará mágicamente si la foto tiene coordenadas GPS (iPhone/Android) 🌍'),
                Toggle::make('is_locked')
                    ->label('¿Bloquear con candado secreto?')
                    ->required(),
                TextInput::make('unlock_question')
                    ->label('Pregunta Secreta (Si está bloqueada)'),
                TextInput::make('unlock_answer')
                    ->label('Respuesta Secreta'),
            ]);
    }

    public static function getGps($exifCoord, $hemi)
    {
        $degrees = count($exifCoord) > 0 ? self::gps2Num($exifCoord[0]) : 0;
        $minutes = count($exifCoord) > 1 ? self::gps2Num($exifCoord[1]) : 0;
        $seconds = count($exifCoord) > 2 ? self::gps2Num($exifCoord[2]) : 0;
        
        $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;
        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }

    public static function gps2Num($coordPart)
    {
        $parts = explode('/', $coordPart);
        if (count($parts) <= 0) return 0;
        if (count($parts) == 1) return $parts[0];
        return floatval($parts[0]) / floatval($parts[1]);
    }
}
