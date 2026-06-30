<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Memory;

Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/memories', function () {
        // Retorna las memorias ordenadas por fecha para la línea de tiempo
        $memories = Memory::orderBy('date', 'asc')->get();
        return $memories->map(function ($memory) {
            $data = $memory->toArray();
            if ($memory->image_path) {
                try {
                    $data['image_url'] = \Illuminate\Support\Facades\Storage::disk('cloudinary')->url($memory->image_path);
                } catch (\Exception $e) {
                    $data['image_url'] = asset('storage/' . $memory->image_path);
                }
            }
            return $data;
        });
    });

    Route::get('/settings', function () {
        $settings = \App\Models\SiteSetting::firstOrCreate(['id' => 1]);
        $data = $settings->toArray();
        
        if ($settings->custom_audio_path) {
            try {
                $data['custom_audio_url'] = \Illuminate\Support\Facades\Storage::disk('cloudinary')->url($settings->custom_audio_path);
            } catch (\Exception $e) {
                // Fallback to default storage url if cloudinary fails or is unconfigured locally
                $data['custom_audio_url'] = asset('storage/' . $settings->custom_audio_path);
            }
        }
        
        return $data;
    });

});
