<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->json('structure');
            $table->foreignId('created_by')->constrained('users');
            $table->unique(['plan_id', 'period']);
            $table->timestamps();
        });

        Schema::create('plan_increases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('percentage', 6, 2);
            $table->string('from_period', 7);
            $table->string('to_period', 7);
            $table->json('plan_ids');
            $table->timestamps();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('relation'); // titular, conyuge, hijo
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('age');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('locality_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('plan_increases');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
