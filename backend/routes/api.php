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
                if (str_starts_with($memory->image_path, 'http')) {
                    $data['image_url'] = $memory->image_path;
                } else {
                    try {
                        $data['image_url'] = \Illuminate\Support\Facades\Storage::disk('cloudinary')->url($memory->image_path);
                    } catch (\Exception $e) {
                        $data['image_url'] = asset('storage/' . $memory->image_path);
                    }
                }
            }
            return $data;
        });
    });

    Route::get('/settings', function () {
        $settings = \App\Models\SiteSetting::firstOrCreate(['id' => 1]);
        $data = $settings->toArray();
        
        if ($settings->custom_audio_path) {
            if (str_starts_with($settings->custom_audio_path, 'http')) {
                $data['custom_audio_url'] = $settings->custom_audio_path;
            } else {
                try {
                    $data['custom_audio_url'] = \Illuminate\Support\Facades\Storage::disk('cloudinary')->url($settings->custom_audio_path);
                } catch (\Exception $e) {
                    $data['custom_audio_url'] = asset('storage/' . $settings->custom_audio_path);
                }
            }
        }
        
        return $data;
    });

    Route::get('/test-cloudinary', function () {
        try {
            $config = config('cloudinary.cloud_url');
            if (!$config) {
                return 'Error: CLOUDINARY_URL is missing or empty on Railway.';
            }
            return 'Cloudinary URL is set to: ' . substr($config, 0, 15) . '... (truncated for security). Please check if it is correct.';
        } catch (\Exception $e) {
            return 'Cloudinary Error: ' . $e->getMessage();
        }
    });

    Route::get('/debug-logs', function () {
        $logPath = storage_path('logs/debug.txt');
        if (!file_exists($logPath)) {
            return 'No log file found.';
        }
        $logs = file_get_contents($logPath);
        return response(substr($logs, -10000))->header('Content-Type', 'text/plain');
    });

});
