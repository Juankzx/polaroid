<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_locked')->default(true);
            $table->dateTime('target_date')->default('2026-06-30 00:00:00');
            $table->string('countdown_title')->default('Próximamente');
            $table->string('countdown_subtitle')->default('Estará disponible desde el 30 de junio 2026');
            
            $table->string('gift_title')->default('Tengo un regalo para ti...');
            $table->string('birthday_title')->default('¡Feliz Cumpleaños!');
            $table->text('birthday_message')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
