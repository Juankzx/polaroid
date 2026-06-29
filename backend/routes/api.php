<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Memory;

Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/memories', function () {
        // Retorna las memorias ordenadas por fecha para la línea de tiempo
        return Memory::orderBy('date', 'asc')->get();
    });

    Route::get('/settings', function () {
        return \App\Models\SiteSetting::firstOrCreate(['id' => 1]);
    });

});
