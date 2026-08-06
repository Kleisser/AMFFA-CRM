<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('dni', 20);
            $table->string('status', 20)->default('found');
            $table->json('response')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index('dni');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_checks');
    }
};
