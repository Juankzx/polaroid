<?php

namespace App\Filament\Resources\Memories\Pages;

use App\Filament\Resources\Memories\MemoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMemories extends ListRecords
{
    protected static string $resource = MemoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                            'Viajes ✈️' => 'Viajes ✈️',
                            'Fechas Especiales 🎂' => 'Fechas Especiales 🎂',
                            'Momentos Divertidos 🤪' => 'Momentos Divertidos 🤪',
                            'Logros 🏆' => 'Logros 🏆',
                            'Momentos Random 📸' => 'Momentos Random 📸',
                        ])
                        ->default('Momentos Random 📸')
                        ->required(),
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
                        $fullPath = storage_path('app/public/' . $tempPath);
                        $fecha = now()->format('Y-m-d');
                        $ubicacion = null;

                        try {
                            $exif = @exif_read_data($fullPath, 'EXIF', true);
                            if ($exif !== false) {
                                $dateStr = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
                                if ($dateStr) {
                                    $parsed = \DateTime::createFromFormat('Y:m:d H:i:s', $dateStr);
                                    if ($parsed) $fecha = $parsed->format('Y-m-d');
                                }
                                if (isset($exif['GPS'])) {
                                    $gps = $exif['GPS'];
                                    if (isset($gps['GPSLatitude'], $gps['GPSLongitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitudeRef'])) {
                                        $lat = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
                                        $lng = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
                                        $response = \Illuminate\Support\Facades\Http::timeout(4)->withHeaders(['User-Agent' => 'NuestraHistoriaApp/1.0'])->get("https://nominatim.openstreetmap.org/reverse", ['lat' => $lat, 'lon' => $lng, 'format' => 'json']);
                                        if ($response->successful()) {
                                            $address = $response->json()['address'] ?? [];
                                            $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['county'] ?? '';
                                            $country = $address['country'] ?? '';
                                            $locArr = array_filter([$city, $country]);
                                            if (!empty($locArr)) $ubicacion = implode(', ', $locArr);
                                        }
                                    }
                                }
                            }
                            // Fallback al nombre del archivo si no se extrajo fecha del EXIF
                            if ($fecha === now()->format('Y-m-d')) {
                                $filename = basename($fullPath);
                                if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $matches)) {
                                    $year  = (int) $matches[1];
                                    $month = (int) $matches[2];
                                    $day   = (int) $matches[3];
                                    if ($year >= 2000 && $year <= 2030 && checkdate($month, $day, $year)) {
                                        $fecha = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}

                        $cloudinaryUrl = env('APP_URL');
                        $cloudUrlConfig = env('CLOUDINARY_URL');
                        if ($cloudUrlConfig) {
                            $parsed = parse_url($cloudUrlConfig);
                            $apiKey = $parsed['user'] ?? '';
                            $apiSecret = $parsed['pass'] ?? '';
                            $cloudName = $parsed['host'] ?? '';
                            if ($apiKey && $apiSecret && $cloudName && $apiSecret !== 'PON_TU_API_SECRET_AQUI') {
                                $timestamp = time();
                                $signature = sha1("timestamp={$timestamp}" . $apiSecret);
                                $responseCloud = \Illuminate\Support\Facades\Http::attach('file', file_get_contents($fullPath), basename($fullPath))->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", ['api_key' => $apiKey, 'timestamp' => $timestamp, 'signature' => $signature]);
                                if ($responseCloud->successful()) {
                                    $resData = $responseCloud->json();
                                    $cloudinaryUrl = $resData['public_id'] . '.' . $resData['format'];
                                } else {
                                    \Illuminate\Support\Facades\Log::error("Cloudinary Upload Error: " . $responseCloud->body());
                                }
                            }
                        }

                        \App\Models\Memory::create([
                            'title' => $data['titulo_general'] . ' ' . $contador,
                            'category' => $data['categoria'],
                            'image_path' => $cloudinaryUrl,
                            'date' => $fecha,
                            'location' => $ubicacion,
                            'is_locked' => $data['is_locked'],
                            'unlock_question' => $data['unlock_question'] ?? null,
                            'unlock_answer' => $data['unlock_answer'] ?? null,
                        ]);
                        @unlink($fullPath);
                        $contador++;
                    }
                })
                ->successNotificationTitle('¡Fotos subidas rápido!'),

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
                                ->columnSpanFull(),
                            \Filament\Forms\Components\TextInput::make('title')
                                ->label('Título')
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
                        $fullPath = storage_path('app/public/' . $tempPath);
                        
                        $fecha = now()->format('Y-m-d');
                        $ubicacion = null;

                        // Intentar extraer EXIF
                        try {
                            $exif = @exif_read_data($fullPath, 'EXIF', true);
                            if ($exif !== false) {
                                $dateStr = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
                                if ($dateStr) {
                                    $parsed = \DateTime::createFromFormat('Y:m:d H:i:s', $dateStr);
                                    if ($parsed) $fecha = $parsed->format('Y-m-d');
                                }
                                
                                if (isset($exif['GPS'])) {
                                    $gps = $exif['GPS'];
                                    if (isset($gps['GPSLatitude'], $gps['GPSLongitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitudeRef'])) {
                                        $lat = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
                                        $lng = \App\Filament\Resources\Memories\Schemas\MemoryForm::getGps($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
                                        
                                        $response = \Illuminate\Support\Facades\Http::timeout(4)->withHeaders(['User-Agent' => 'NuestraHistoriaApp/1.0'])->get("https://nominatim.openstreetmap.org/reverse", ['lat' => $lat, 'lon' => $lng, 'format' => 'json']);
                                        if ($response->successful()) {
                                            $address = $response->json()['address'] ?? [];
                                            $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['county'] ?? '';
                                            $country = $address['country'] ?? '';
                                            $locArr = array_filter([$city, $country]);
                                            if (!empty($locArr)) $ubicacion = implode(', ', $locArr);
                                        }
                                    }
                                }
                            }
                            
                            // Fallback al nombre del archivo si no se extrajo fecha del EXIF
                            if ($fecha === now()->format('Y-m-d')) {
                                $filename = basename($fullPath);
                                if (preg_match('/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/', $filename, $matches)) {
                                    $year  = (int) $matches[1];
                                    $month = (int) $matches[2];
                                    $day   = (int) $matches[3];
                                    if ($year >= 2000 && $year <= 2030 && checkdate($month, $day, $year)) {
                                        $fecha = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error("EXIF Error Bulk: " . $e->getMessage());
                        }

                        // Subir a Cloudinary nativamente (Signed Upload sin paquetes)
                        $cloudinaryUrl = env('APP_URL'); // fallback
                        $cloudUrlConfig = env('CLOUDINARY_URL');
                        if ($cloudUrlConfig) {
                            $parsed = parse_url($cloudUrlConfig);
                            $apiKey = $parsed['user'] ?? '';
                            $apiSecret = $parsed['pass'] ?? '';
                            $cloudName = $parsed['host'] ?? '';

                            if ($apiKey && $apiSecret && $cloudName && $apiSecret !== 'PON_TU_API_SECRET_AQUI') {
                                $timestamp = time();
                                $signature = sha1("timestamp={$timestamp}" . $apiSecret);

                                $responseCloud = \Illuminate\Support\Facades\Http::attach(
                                    'file', file_get_contents($fullPath), basename($fullPath)
                                )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                                    'api_key' => $apiKey,
                                    'timestamp' => $timestamp,
                                    'signature' => $signature,
                                ]);

                                if ($responseCloud->successful()) {
                                    $resData = $responseCloud->json();
                                    $cloudinaryUrl = $resData['public_id'] . '.' . $resData['format'];
                                } else {
                                    \Illuminate\Support\Facades\Log::error("Cloudinary Error: " . $responseCloud->body());
                                }
                            }
                        }

                        // Crear el registro
                        \App\Models\Memory::create([
                            'title' => $item['title'],
                            'category' => $item['category'],
                            'description' => $item['description'] ?? null,
                            'image_path' => $cloudinaryUrl,
                            'date' => $fecha,
                            'location' => $ubicacion,
                            'is_locked' => $data['is_locked'],
                            'unlock_question' => $data['unlock_question'] ?? null,
                            'unlock_answer' => $data['unlock_answer'] ?? null,
                        ]);

                        // Eliminar archivo temporal local
                        @unlink($fullPath);
                    }
                })
                ->successNotificationTitle('¡Fotos procesadas y subidas a Cloudinary!'),
            CreateAction::make(),
        ];
    }
}
