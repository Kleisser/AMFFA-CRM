<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->default('#6B7280');
            $table->timestamps();
        });

        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('partido')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();

            $table->unique(['zone_id', 'name']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('address')->constrained()->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->after('zone_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locality_id');
            $table->dropConstrainedForeignId('zone_id');
        });

        Schema::dropIfExists('localities');
        Schema::dropIfExists('zones');
    }
};
