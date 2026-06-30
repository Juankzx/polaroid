<?php

namespace App\Filament\Resources\Memories\Pages;

use App\Filament\Resources\Memories\MemoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ListMemories extends ListRecords
{
    protected static string $resource = MemoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ══════════════════════════════════════════════════════════════
            // CARGA RÁPIDA (30 de golpe)
            // ══════════════════════════════════════════════════════════════
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
                            'Viajes ✈️'             => 'Viajes ✈️',
                            'Fechas Especiales 🎂'  => 'Fechas Especiales 🎂',
                            'Momentos Divertidos 🤪' => 'Momentos Divertidos 🤪',
                            'Logros 🏆'             => 'Logros 🏆',
                            'Momentos Random 📸'    => 'Momentos Random 📸',
                        ])
                        ->default('Momentos Random 📸')
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('fecha_fallback')
                        ->label('Fecha General (fallback)')
                        ->helperText('🕰️ Se extrae del EXIF automáticamente. Solo se usa si la foto no tiene fecha interna.')
                        ->default(now()->format('Y-m-d')),
                    \Filament\Forms\Components\TextInput::make('ubicacion_fallback')
                        ->label('Ubicación General (fallback)')
                        ->helperText('🌍 Se extrae del GPS automáticamente. Solo se usa si la foto no tiene coordenadas.')
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
                        // ── Resolver path real del archivo ─────────────────
                        $fullPath = $this->findFilePath($tempPath);

                        // ── EXIF: fecha y ubicación ─────────────────────────
                        $fecha    = $data['fecha_fallback'] ?? now()->format('Y-m-d');
                        $ubicacion = $data['ubicacion_fallback'] ?? null;

                        if ($fullPath) {
                            [$exifDate, $exifLocation] = $this->readExif($fullPath);
                            if ($exifDate)     $fecha    = $exifDate;
                            if ($exifLocation) $ubicacion = $exifLocation;
                        }

                        // ── Subir a Cloudinary ──────────────────────────────
                        $cloudinaryUrl = $this->uploadCloudinary($fullPath ?? '');

                        \App\Models\Memory::create([
                            'title'           => $data['titulo_general'] . ' ' . $contador,
                            'category'        => $data['categoria'],
                            'image_path'      => $cloudinaryUrl,
                            'date'            => $fecha,
                            'location'        => $ubicacion,
                            'is_locked'       => $data['is_locked'],
                            'unlock_question' => $data['unlock_question'] ?? null,
                            'unlock_answer'   => $data['unlock_answer'] ?? null,
                        ]);

                        if ($fullPath) @unlink($fullPath);
                        $contador++;
                    }
                })
                ->successNotificationTitle('¡Fotos subidas rápido!'),

            // ══════════════════════════════════════════════════════════════
            // CARGA DETALLADA (Grilla / Repeater)
            // ══════════════════════════════════════════════════════════════
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
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (!$state) return;

                                    // $state puede ser un string (path) o un objeto TemporaryUploadedFile
                                    try {
                                        // Caso 1: objeto con getRealPath (archivo en memoria de Livewire)
                                        if (is_object($state) && method_exists($state, 'getRealPath')) {
                                            $path     = $state->getRealPath();
                                            $filename = method_exists($state, 'getClientOriginalName')
                                                ? $state->getClientOriginalName()
                                                : basename($path ?? '');

                                            if ($path && file_exists($path)) {
                                                $exif = @exif_read_data($path, 'EXIF', true);
                                                if ($exif) {
                                                    // Fecha
                                                    $ds = $exif['EXIF']['DateTimeOriginal']
                                                        ?? $exif['EXIF']['DateTimeDigitized']
                                                        ?? $exif['IFD0']['DateTime']
                                                        ?? null;
                                                    if ($ds) {
                                                        $d = \DateTime::createFromFormat('Y:m:d H:i:s', $ds);
                                                        if ($d) $set('date', $d->format('Y-m-d'));
                                                    }
                                                    // GPS
                                                    if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'])) {
                                                        $lat = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps(
                                                            $exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']
                                                        );
                                                        $lng = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps(
                                                            $exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']
                                                        );
                                                        $loc = $this->geocode($lat, $lng);
                                                        if ($loc) $set('location', $loc);
                                                    }
                                                    return;
                                                }
                                            }
                                            // Fallback: fecha del nombre del archivo
                                            if ($filename && preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $m)) {
                                                [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
                                                if ($y >= 2000 && $y <= 2035 && checkdate($mo, $d, $y)) {
                                                    $set('date', sprintf('%04d-%02d-%02d', $y, $mo, $d));
                                                }
                                            }
                                            return;
                                        }

                                        // Caso 2: string (path relativo en disco)
                                        $pathStr = is_string($state) ? $state : '';
                                        if ($pathStr) {
                                            $fullPath = Storage::disk('public')->path($pathStr);
                                            if (file_exists($fullPath)) {
                                                $exif = @exif_read_data($fullPath, 'EXIF', true);
                                                if ($exif) {
                                                    $ds = $exif['EXIF']['DateTimeOriginal']
                                                        ?? $exif['EXIF']['DateTimeDigitized']
                                                        ?? $exif['IFD0']['DateTime']
                                                        ?? null;
                                                    if ($ds) {
                                                        $d = \DateTime::createFromFormat('Y:m:d H:i:s', $ds);
                                                        if ($d) $set('date', $d->format('Y-m-d'));
                                                    }
                                                    if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'])) {
                                                        $lat = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps(
                                                            $exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']
                                                        );
                                                        $lng = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps(
                                                            $exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']
                                                        );
                                                        $loc = $this->geocode($lat, $lng);
                                                        if ($loc) $set('location', $loc);
                                                    }
                                                }
                                            }
                                            // Fecha del nombre de archivo
                                            $fn = basename($pathStr);
                                            if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $fn, $m)) {
                                                [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
                                                if ($y >= 2000 && $y <= 2035 && checkdate($mo, $d, $y)) {
                                                    $set('date', sprintf('%04d-%02d-%02d', $y, $mo, $d));
                                                }
                                            }
                                        }
                                    } catch (\Throwable $e) {
                                        Log::error('[EXIF afterStateUpdated] ' . $e->getMessage());
                                    }
                                })
                                ->columnSpanFull(),
                            \Filament\Forms\Components\TextInput::make('title')
                                ->label('Título')
                                ->required(),
                            \Filament\Forms\Components\Select::make('category')
                                ->label('Categoría')
                                ->options([
                                    'Viajes ✈️'             => 'Viajes ✈️',
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
                        $fullPath = $this->findFilePath($tempPath);

                        $fecha    = $item['date'] ?? null;
                        $ubicacion = $item['location'] ?? null;

                        // Si faltan datos, leer EXIF en el save
                        if ((!$fecha || !$ubicacion) && $fullPath) {
                            [$exifDate, $exifLoc] = $this->readExif($fullPath);
                            if (!$fecha    && $exifDate) $fecha    = $exifDate;
                            if (!$ubicacion && $exifLoc) $ubicacion = $exifLoc;
                        }

                        $fecha = $fecha ?: now()->format('Y-m-d');

                        $cloudinaryUrl = $this->uploadCloudinary($fullPath ?? '');

                        \App\Models\Memory::create([
                            'title'           => $item['title'],
                            'category'        => $item['category'],
                            'description'     => $item['description'] ?? null,
                            'image_path'      => $cloudinaryUrl,
                            'date'            => $fecha,
                            'location'        => $ubicacion,
                            'is_locked'       => $data['is_locked'],
                            'unlock_question' => $data['unlock_question'] ?? null,
                            'unlock_answer'   => $data['unlock_answer'] ?? null,
                        ]);

                        if ($fullPath) @unlink($fullPath);
                    }
                })
                ->successNotificationTitle('¡Fotos procesadas y subidas a Cloudinary!'),

            CreateAction::make(),
        ];
    }

    // ── Encontrar el path real del archivo temporal ──────────────────────────
    private function findFilePath(string $tempPath): ?string
    {
        $candidates = [
            Storage::disk('public')->path($tempPath),
            storage_path('app/livewire-tmp/' . basename($tempPath)),
            storage_path('app/' . $tempPath),
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($tempPath),
        ];

        foreach ($candidates as $p) {
            if ($p && file_exists($p) && is_readable($p)) {
                Log::info('[EXIF] Archivo hallado: ' . $p);
                return $p;
            }
        }

        Log::warning('[EXIF] Archivo NO hallado para: ' . $tempPath);
        return null;
    }

    // ── Leer EXIF y devolver [fecha, ubicacion] ──────────────────────────────
    private function readExif(string $path): array
    {
        $date     = null;
        $location = null;

        if (!function_exists('exif_read_data')) {
            Log::warning('[EXIF] exif_read_data no disponible.');
            return [$date, $location];
        }

        try {
            $exif = @exif_read_data($path, 'EXIF', true);
            Log::info('[EXIF] Leyendo: ' . $path . ' | resultado: ' . ($exif !== false ? 'OK' : 'sin EXIF'));

            if ($exif) {
                // Fecha
                $ds = $exif['EXIF']['DateTimeOriginal']
                    ?? $exif['EXIF']['DateTimeDigitized']
                    ?? $exif['IFD0']['DateTime']
                    ?? null;
                if ($ds) {
                    $d = \DateTime::createFromFormat('Y:m:d H:i:s', $ds);
                    if ($d) $date = $d->format('Y-m-d');
                }

                // GPS
                if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'],
                           $exif['GPS']['GPSLatitudeRef'], $exif['GPS']['GPSLongitudeRef'])) {
                    $lat = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps(
                        $exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']
                    );
                    $lng = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps(
                        $exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']
                    );
                    Log::info("[EXIF] GPS: lat=$lat lng=$lng");
                    $location = $this->geocode($lat, $lng);
                }
            }

            // Fallback: fecha del nombre de archivo
            if (!$date) {
                $fn = basename($path);
                if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $fn, $m)) {
                    [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
                    if ($y >= 2000 && $y <= 2035 && checkdate($mo, $d, $y)) {
                        $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
                        Log::info('[EXIF] Fecha del nombre: ' . $date);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('[EXIF] Error: ' . $e->getMessage());
        }

        return [$date, $location];
    }

    // ── Geocoding inverso con doble API ──────────────────────────────────────
    private function geocode(float $lat, float $lng): ?string
    {
        // API 1: BigDataCloud (gratis, sin límites estrictos, funciona en servers cloud)
        try {
            $r = Http::timeout(8)->get('https://api.bigdatacloud.net/data/reverse-geocode-client', [
                'latitude'        => $lat,
                'longitude'       => $lng,
                'localityLanguage' => 'es',
            ]);
            if ($r->successful()) {
                $j    = $r->json();
                $city = $j['city'] ?? $j['locality'] ?? $j['principalSubdivision'] ?? '';
                $country = $j['countryName'] ?? '';
                $parts = array_filter([$city, $country]);
                if (!empty($parts)) {
                    Log::info('[GEOCODE] BigDataCloud: ' . implode(', ', $parts));
                    return implode(', ', $parts);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[GEOCODE] BigDataCloud error: ' . $e->getMessage());
        }

        // API 2: Nominatim (fallback)
        try {
            $r = Http::timeout(8)->withHeaders([
                'User-Agent'      => 'PolaroidMemories/2.0',
                'Accept-Language' => 'es',
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'lat'    => $lat,
                'lon'    => $lng,
                'format' => 'json',
            ]);
            if ($r->successful()) {
                $addr = $r->json()['address'] ?? [];
                $city    = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? '';
                $country = $addr['country'] ?? '';
                $parts   = array_filter([$city, $country]);
                if (!empty($parts)) {
                    Log::info('[GEOCODE] Nominatim: ' . implode(', ', $parts));
                    return implode(', ', $parts);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[GEOCODE] Nominatim error: ' . $e->getMessage());
        }

        return null;
    }

    // ── Subir a Cloudinary ───────────────────────────────────────────────────
    private function uploadCloudinary(string $fullPath): string
    {
        $fallback       = env('APP_URL', '');
        $cloudUrlConfig = env('CLOUDINARY_URL');

        if (!$cloudUrlConfig || !$fullPath || !file_exists($fullPath)) {
            return $fallback;
        }

        $parsed    = parse_url($cloudUrlConfig);
        $apiKey    = $parsed['user'] ?? '';
        $apiSecret = $parsed['pass'] ?? '';
        $cloudName = $parsed['host'] ?? '';

        if (!$apiKey || !$apiSecret || !$cloudName || $apiSecret === 'PON_TU_API_SECRET_AQUI') {
            return $fallback;
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

        throw new \Exception('Cloudinary Upload Error: ' . $response->body());
    }
}
