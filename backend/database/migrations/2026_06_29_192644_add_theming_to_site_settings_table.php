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
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('theme_color')->default('#f43f5e')->after('id');
            $table->string('theme_type')->default('love')->after('theme_color');
            $table->string('hero_title')->default('Nuestra Historia')->after('theme_type');
            $table->string('hero_subtitle')->default('Un recorrido por nuestros mejores momentos')->after('hero_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['theme_color', 'theme_type', 'hero_title', 'hero_subtitle']);
        });
    }
};
