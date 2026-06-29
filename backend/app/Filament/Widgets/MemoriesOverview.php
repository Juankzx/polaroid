<?php

namespace App\Filament\Widgets;

use App\Models\Memory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemoriesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total de Recuerdos', Memory::count())
                ->description('Momentos inolvidables guardados')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger'),
            Stat::make('Recuerdos Protegidos', Memory::where('is_locked', true)->count())
                ->description('Con preguntas secretas')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('warning'),
        ];
    }
}
