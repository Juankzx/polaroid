<?php

namespace App\Filament\Resources\Memories;

use App\Filament\Resources\Memories\Pages\CreateMemory;
use App\Filament\Resources\Memories\Pages\EditMemory;
use App\Filament\Resources\Memories\Pages\ListMemories;
use App\Filament\Resources\Memories\Schemas\MemoryForm;
use App\Filament\Resources\Memories\Tables\MemoriesTable;
use App\Models\Memory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemoryResource extends Resource
{
    protected static ?string $model = Memory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Recuerdo';
    protected static ?string $pluralModelLabel = 'Recuerdos';
    protected static ?string $navigationLabel = 'Mis Recuerdos';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return MemoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemories::route('/'),
            'create' => CreateMemory::route('/create'),
            'edit' => EditMemory::route('/{record}/edit'),
        ];
    }
}
