<?php

namespace App\Filament\Resources\Memories\Pages;

use App\Filament\Resources\Memories\MemoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Filament\Resources\Memories\Schemas\MemoryForm;

class ListMemories extends ListRecords
{
    protected static string $resource = MemoryResource::class;

    // ─── Helper: resolver path real del archivo temporal de Filament/Livewire ───
    protected static function resolveFilePath(string $tempPath): ?string
    {
        $candidates = [
            // 1. Disk 'public' (storage/app/public/...)  ← lo que usa FileUpload disk('public')
            Storage::disk('public')->path($tempPath),
            // 2. Livewire temp dir
            storage_path('app/livewire-tmp/' . basename($tempPath)),
            // 3. storage/app directo
            storage_path('app/' . $tempPath),
            // 4. PHP sys temp dir
            sys_get_temp_dir() . '/' . basename($tempPath),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_readable($path)) {
                Log::info("[EXIF] Archivo encontrado: " . $path);
                return $path;
            }
        }

        Log::warning("[EXIF] Archivo NO encontrado para: " . $tempPath . " | Candidatos: " . implode(', ', $candidates));
        return null;
    }

    // ─── Helper: extraer EXIF (fecha + GPS) de un archivo ───────────────────────
    protected static function extractExifData(string $path): array
    {
        $result = ['date' => null, 'location' => null];

        if (!function_exists('exif_read_data')) {
            Log::warning("[EXIF] exif_read_data NO está disponible en este servidor.");
            return $result;
        }

        try {
            $exif = @exif_read_data($path, 'EXIF', true);
            Log::info("[EXIF] Leyendo: " . $path . " | EXIF: " . ($exif !== false ? 'OK' : 'NO ENCONTRADO'));

            if ($exif === false) {
                // Fallback: leer fecha del nombre del archivo
                $filename = basename($path);
                if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $m)) {
                    $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
                    if ($y >= 2000 && $y <= 2035 && checkdate($mo, $d, $y)) {
                        $result['date'] = sprintf('%04d-%02d-%02d', $y, $mo, $d);
                        Log::info("[EXIF] Fecha del nombre de archivo: " . $result['date']);
                    }
                }
                return $result;
            }

            // ── Fecha ──────────────────────────────────────────────────────────
            $dateStr = $exif['EXIF']['DateTimeOriginal']
                ?? $exif['EXIF']['DateTimeDigitized']
                ?? $exif['IFD0']['DateTime']
                ?? null;

            if ($dateStr) {
                $parsed = \DateTime::createFromFormat('Y:m:d H:i:s', $dateStr);
                if ($parsed) {
                    $result['date'] = $parsed->format('Y-m-d');
                    Log::info("[EXIF] Fecha extraída: " . $result['date']);
                }
            }

            // Si no hay fecha EXIF, intentar del nombre del archivo
            if (!$result['date']) {
                $filename = basename($path);
                if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $m)) {
                    $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
                    if ($y >= 2000 && $y <= 2035 && checkdate($mo, $d, $y)) {
                        $result['date'] = sprintf('%04d-%02d-%02d', $y, $mo, $d);
                        Log::info("[EXIF] Fecha del nombre de archivo: " . $result['date']);
                    }
                }
            }

            // ── GPS / Ubicación ────────────────────────────────────────────────
            if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'],
                      $exif['GPS']['GPSLatitudeRef'], $exif['GPS']['GPSLongitudeRef'])) {
                $lat = MemoryForm::getGps($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
                $lng = MemoryForm::getGps($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
                Log::info("[EXIF] GPS encontrado: lat={$lat}, lng={$lng}");

                $location = self::reverseGeocode($lat, $lng);
                if ($location) {
                    $result['location'] = $location;
                    Log::info("[EXIF] Ubicación: " . $location);
                }
            } else {
                Log::info("[EXIF] Sin datos GPS en la foto.");
            }

        } catch (\Throwable $e) {
            Log::error("[EXIF] Error: " . $e->getMessage());
        }

        return $result;
    }

    // ─── Helper: geocoding inverso con fallback de APIs ──────────────────────
    protected static function reverseGeocode(float $lat, float $lng): ?string
    {
        $apis = [
            // Nominatim (OpenStreetMap)
            [
                'url' => "https://nominatim.openstreetmap.org/reverse",
                'params' => ['lat' => $lat, 'lon' => $lng, 'format' => 'json'],
                'headers' => ['User-Agent' => 'PolaroidMemories/2.0 (contact@example.com)', 'Accept-Language' => 'es'],
                'parse' => function ($json) {
                    $a = $json['address'] ?? [];
                    $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['county'] ?? '';
                    $country = $a['country'] ?? '';
                    $parts = array_filter([$city, $country]);
                    return !empty($parts) ? implode(', ', $parts) : null;
                },
            ],
            // BigDataCloud (sin API key, muy permisivo)
            [
                'url' => "https://api.bigdatacloud.net/data/reverse-geocode-client",
                'params' => ['latitude' => $lat, 'longitude' => $lng, 'localityLanguage' => 'es'],
                'headers' => [],
                'parse' => function ($json) {
                    $city = $json['city'] ?? $json['locality'] ?? $json['principalSubdivision'] ?? '';
                    $country = $json['countryName'] ?? '';
                    $parts = array_filter([$city, $country]);
                    return !empty($parts) ? implode(', ', $parts) : null;
                },
            ],
        ];

        foreach ($apis as $api) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders($api['headers'])
                    ->get($api['url'], $api['params']);

                if ($response->successful()) {
                    $result = ($api['parse'])($response->json());
                    if ($result) return $result;
                }
            } catch (\Throwable $e) {
                Log::warning("[GEOCODE] API falló: " . $api['url'] . " | " . $e->getMessage());
            }
        }

        return null;
    }

    // ─── Helper: subir a Cloudinary ──────────────────────────────────────────
    protected static function uploadToCloudinary(string $fullPath): string
    {
        $cloudUrlConfig = env('CLOUDINARY_URL');
        if (!$cloudUrlConfig) return env('APP_URL', '');

        $parsed = parse_url($cloudUrlConfig);
        $apiKey    = $parsed['user'] ?? '';
        $apiSecret = $parsed['pass'] ?? '';
        $cloudName = $parsed['host'] ?? '';

        if (!$apiKey || !$apiSecret || !$cloudName || $apiSecret === 'PON_TU_API_SECRET_AQUI') {
            return env('APP_URL', '');
        }

        $timestamp = time();
        $signature = sha1("timestamp={$timestamp}" . $apiSecret);

        $response = Http::attach('file', file_get_contents($fullPath), basename($fullPath))
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['public_id'] . '.' . $data['format'];
        }

        throw new \Exception("Cloudinary Upload Error: " . $response->body());
    }

    // ─── Header Actions ──────────────────────────────────────────────────────
    protected function getHeaderActions(): array
    {
        return [
            // ═══════════════════════════════════════════════════════════════
            // CARGA RÁPIDA (30 de golpe)
            // ═══════════════════════════════════════════════════════════════
            \Filament\Actions\Action::make('cargaRapida')
                ->label('Carga Rápida (30 de golpe)')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('fotos')
                        ->label('Sube tus fotos aquí')
                        ->multiple()
                        ->image()
                        ->disk('public')
                        ->directory('temp_bulk')
                        ->preserveFilenames()
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('titulo_general')
                        ->label('Título General')
                        ->helperText('Ej: "Viaje a Uyuni". Se guardarán como "Viaje a Uyuni 1", etc.')
                        ->required(),
                    \Filament\Forms\Components\Select::make('categoria')
                        ->label('Categoría')
                        ->options([
                            'Viajes ✈️'            => 'Viajes ✈️',
                            'Fechas Especiales 🎂'  => 'Fechas Especiales 🎂',
                            'Momentos Divertidos 🤪' => 'Momentos Divertidos 🤪',
                            'Logros 🏆'             => 'Logros 🏆',
                            'Momentos Random 📸'    => 'Momentos Random 📸',
                        ])
                        ->default('Momentos Random 📸')
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('fecha_fallback')
                        ->label('Fecha General (fallback)')
                        ->helperText('🕰️ Se extraerá automáticamente del EXIF de cada foto. Solo se usa si la foto no tiene fecha interna.')
                        ->default(now()->format('Y-m-d')),
                    \Filament\Forms\Components\TextInput::make('ubicacion_fallback')
                        ->label('Ubicación General (fallback)')
                        ->helperText('🌍 Se extraerá del GPS de la foto. Solo se usa si la foto no tiene coordenadas.')
                        ->placeholder('Ej: Santa Cruz, Bolivia'),
                    \Filament\Forms\Components\Toggle::make('is_locked')
                        ->label('¿Bloquear todas con candado?')
                        ->reactive(),
                    \Filament\Forms\Components\TextInput::make('unlock_question')
                        ->label('Pregunta Secreta')
                        ->visible(fn ($get) => $get('is_locked')),
                    \Filament\Forms\Components\TextInput::make('unlock_answer')
                        ->label('Respuesta Secreta')
                        ->visible(fn ($get) => $get('is_locked')),
                ])
                ->action(function (array $data) {
                    $contador = 1;
                    foreach ($data['fotos'] as $tempPath) {
                        Log::info("[BULK-QUICK] Procesando: " . $tempPath);

                        $fullPath = self::resolveFilePath($tempPath);

                        // Fecha/ubicación: empezar con el fallback del formulario
                        $fecha    = $data['fecha_fallback'] ?? now()->format('Y-m-d');
                        $ubicacion = $data['ubicacion_fallback'] ?? null;

                        // Intentar EXIF (sobreescribe el fallback si encuentra datos)
                        if ($fullPath) {
                            $exif = self::extractExifData($fullPath);
                            if ($exif['date'])     $fecha    = $exif['date'];
                            if ($exif['location']) $ubicacion = $exif['location'];
                        }

                        $cloudinaryUrl = $fullPath ? self::uploadToCloudinary($fullPath) : env('APP_URL', '');

                        \App\Models\Memory::create([
                            'title'          => $data['titulo_general'] . ' ' . $contador,
                            'category'       => $data['categoria'],
                            'image_path'     => $cloudinaryUrl,
                            'date'           => $fecha,
                            'location'       => $ubicacion,
                            'is_locked'      => $data['is_locked'],
                            'unlock_question' => $data['unlock_question'] ?? null,
                            'unlock_answer'  => $data['unlock_answer'] ?? null,
                        ]);

                        if ($fullPath) @unlink($fullPath);
                        $contador++;
                    }
                })
                ->successNotificationTitle('¡Fotos subidas rápido!'),

            // ═══════════════════════════════════════════════════════════════
            // CARGA DETALLADA (Grilla / Repeater)
            // ═══════════════════════════════════════════════════════════════
            \Filament\Actions\Action::make('cargaMasiva')
                ->label('Carga Detallada (Grilla)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Repeater::make('recuerdos')
                        ->label('Fotos a subir')
                        ->schema([
                            \Filament\Forms\Components\FileUpload::make('foto')
                                ->label('Foto')
                                ->image()
                                ->disk('public')
                                ->directory('temp_bulk')
                                ->preserveFilenames()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, \Filament\Forms\Components\FileUpload $component) {
                                    if (!$state) return;

                                    $files = $component->getState();
                                    $file  = is_array($files) ? reset($files) : $files;

                                    // Caso 1: el archivo todavía es un objeto UploadedFile en memoria
                                    if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                                        || $file instanceof \Illuminate\Http\UploadedFile) {
                                        try {
                                            $path     = $file->getRealPath();
                                            $filename = $file->getClientOriginalName();

                                            if ($path && file_exists($path)) {
                                                $exif = self::extractExifData($path);
                                                if ($exif['date'])     $set('date', $exif['date']);
                                                if ($exif['location']) $set('location', $exif['location']);
                                                return;
                                            }

                                            // Sin path real → intentar por nombre de archivo
                                            if ($filename && preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $m)) {
                                                $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
                                                if ($y >= 2000 && $y <= 2035 && checkdate($mo, $d, $y)) {
                                                    $set('date', sprintf('%04d-%02d-%02d', $y, $mo, $d));
                                                }
                                            }
                                        } catch (\Throwable $e) {
                                            Log::error("[EXIF live] " . $e->getMessage());
                                        }
                                        return;
                                    }

                                    // Caso 2: el state es un string (path relativo en disco)
                                    $pathStr = is_string($file) ? $file : (is_string($state) ? $state : '');
                                    if ($pathStr) {
                                        // Intentar EXIF desde el disco
                                        $resolved = self::resolveFilePath($pathStr);
                                        if ($resolved) {
                                            $exif = self::extractExifData($resolved);
                                            if ($exif['date'])     $set('date', $exif['date']);
                                            if ($exif['location']) $set('location', $exif['location']);
                                            return;
                                        }

                                        // Fallback: leer fecha del nombre
                                        $filename = basename($pathStr);
                                        if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $m)) {
                                            $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
                                            if ($y >= 2000 && $y <= 2035 && checkdate($mo, $d, $y)) {
                                                $set('date', sprintf('%04d-%02d-%02d', $y, $mo, $d));
                                            }
                                        }
                                    }
                                })
                                ->columnSpanFull(),
                            \Filament\Forms\Components\TextInput::make('title')
                                ->label('Título')
                                ->required(),
                            \Filament\Forms\Components\Select::make('category')
                                ->label('Categoría')
                                ->options([
                                    'Viajes ✈️'            => 'Viajes ✈️',
                                    'Fechas Especiales 🎂'  => 'Fechas Especiales 🎂',
                                    'Momentos Divertidos 🤪' => 'Momentos Divertidos 🤪',
                                    'Logros 🏆'             => 'Logros 🏆',
                                    'Momentos Random 📸'    => 'Momentos Random 📸',
                                ])
                                ->default('Momentos Random 📸')
                                ->required(),
                            \Filament\Forms\Components\DatePicker::make('date')
                                ->label('Fecha 🕰️')
                                ->helperText('Se extrae automáticamente del EXIF al subir la foto'),
                            \Filament\Forms\Components\TextInput::make('location')
                                ->label('Ubicación 🌍')
                                ->helperText('Se extrae del GPS de la foto si está disponible')
                                ->placeholder('Ej: Santa Cruz, Bolivia'),
                            \Filament\Forms\Components\Textarea::make('description')
                                ->label('Descripción / Dedicatoria')
                                ->columnSpanFull()
                                ->rows(2),
                        ])
                        ->columns(2)
                        ->addActionLabel('Agregar otra foto')
                        ->defaultItems(1)
                        ->collapsible(),
                    \Filament\Forms\Components\Toggle::make('is_locked')
                        ->label('¿Bloquear todas con candado?')
                        ->reactive(),
                    \Filament\Forms\Components\TextInput::make('unlock_question')
                        ->label('Pregunta Secreta')
                        ->visible(fn ($get) => $get('is_locked')),
                    \Filament\Forms\Components\TextInput::make('unlock_answer')
                        ->label('Respuesta Secreta')
                        ->visible(fn ($get) => $get('is_locked')),
                ])
                ->action(function (array $data) {
                    foreach ($data['recuerdos'] as $item) {
                        $tempPath = $item['foto'];
                        Log::info("[BULK-DETAIL] Procesando: " . $tempPath);

                        $fullPath  = self::resolveFilePath($tempPath);

                        // Fecha y ubicación: usar lo que ya llegó del formulario (afterStateUpdated)
                        $fecha    = $item['date'] ?? null;
                        $ubicacion = $item['location'] ?? null;

                        // Si faltan datos, intentar EXIF en el momento del guardado
                        if ((!$fecha || !$ubicacion) && $fullPath) {
                            $exif = self::extractExifData($fullPath);
                            if (!$fecha     && $exif['date'])     $fecha    = $exif['date'];
                            if (!$ubicacion && $exif['location']) $ubicacion = $exif['location'];
                        }

                        $fecha = $fecha ?: now()->format('Y-m-d');

                        $cloudinaryUrl = $fullPath ? self::uploadToCloudinary($fullPath) : env('APP_URL', '');

                        \App\Models\Memory::create([
                            'title'          => $item['title'],
                            'category'       => $item['category'],
                            'description'    => $item['description'] ?? null,
                            'image_path'     => $cloudinaryUrl,
                            'date'           => $fecha,
                            'location'       => $ubicacion,
                            'is_locked'      => $data['is_locked'],
                            'unlock_question' => $data['unlock_question'] ?? null,
                            'unlock_answer'  => $data['unlock_answer'] ?? null,
                        ]);

                        if ($fullPath) @unlink($fullPath);
                    }
                })
                ->successNotificationTitle('¡Fotos procesadas y subidas a Cloudinary!'),

            CreateAction::make(),
        ];
    }
}
