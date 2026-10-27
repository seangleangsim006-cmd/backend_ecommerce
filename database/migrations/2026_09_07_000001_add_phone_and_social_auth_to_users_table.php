<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 30)->nullable()->unique()->after('email');
            $table->string('social_provider')->nullable()->after('profile_image');
            $table->string('social_id')->nullable()->after('social_provider');
            $table->unique(['social_provider', 'social_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['social_provider', 'social_id']);
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'social_provider', 'social_id']);
            $table->string('email')->nullable(false)->change();
        });
    }
};