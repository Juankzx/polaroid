<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Seguridad y Cuenta Regresiva')
                    ->description('Configura el bloqueo de la página antes de la fecha.')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('is_locked')
                            ->label('Sitio Bloqueado')
                            ->helperText('Si está activo, se mostrará la cuenta regresiva antes de la fecha.')
                            ->default(true),
                        \Filament\Forms\Components\DateTimePicker::make('target_date')
                            ->label('Fecha de Desbloqueo')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('countdown_title')
                            ->label('Título de Cuenta Regresiva')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('countdown_subtitle')
                            ->label('Subtítulo de Cuenta Regresiva')
                            ->required(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Animación de Regalo')
                    ->description('La caja de regalo sorpresa que aparece al entrar.')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('is_gift_enabled')
                            ->label('Mostrar Animación del Regalo')
                            ->helperText('Si está desactivado, el usuario entrará directamente a los recuerdos.')
                            ->default(true)
                            ->columnSpanFull(),
                        \Filament\Forms\Components\TextInput::make('gift_title')
                            ->label('Título de la Caja de Regalo')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('birthday_title')
                            ->label('Título de Felicitación')
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('birthday_message')
                            ->label('Mensaje Especial')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Música de Fondo')
                    ->description('Personaliza la canción que suena de fondo en la página.')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('custom_audio_path')
                            ->label('Sube tu archivo de Música')
                            ->helperText('Sube un archivo MP3 o WAV (Máx. 10MB)')
                            ->disk('cloudinary')
                            ->directory('polaroid_audio')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-m4a'])
                            ->maxSize(10240) // 10 MB
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Apariencia y Textos Principales')
                    ->description('Personaliza el color, el estilo de las animaciones y los textos de bienvenida.')
                    ->schema([
                        \Filament\Forms\Components\ColorPicker::make('theme_color')
                            ->label('Color del Tema')
                            ->default('#f43f5e')
                            ->required(),
                        \Filament\Forms\Components\Select::make('theme_type')
                            ->label('Estilo de Animación')
                            ->options([
                                'love' => 'Amor (Corazones)',
                                'birthday' => 'Cumpleaños (Confeti)',
                                'anniversary' => 'Aniversario (Estrellas doradas)',
                                'classic' => 'Clásico (Luces)',
                            ])
                            ->default('love')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('hero_title')
                            ->label('Título Principal')
                            ->default('Nuestra Historia')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('hero_subtitle')
                            ->label('Subtítulo')
                            ->default('Un recorrido por nuestros mejores momentos')
                            ->required(),
                    ])->columns(2),
            ]);
    }
}
