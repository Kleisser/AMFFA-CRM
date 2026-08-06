<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Altas del equipo comercial sincronizadas desde Google Sheets.
     * Una fila por alta (afiliado cargado). El asesor se guarda con el
     * nombre original de la planilla y, si matchea con un usuario del CRM,
     * su user_id. La "fuente" es el ID de la planilla de origen y "tab" la
     * pestaña mensual donde se registró el alta.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('asesor', 191);
            $table->string('afiliado', 191)->nullable();
            $table->unsignedInteger('capitas')->nullable();
            $table->string('plan', 100)->nullable();
            $table->char('mes', 7);
            $table->string('tab', 100)->nullable();
            $table->string('fuente', 191)->default('planilla');
            $table->timestamp('sincronizada_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['mes', 'fuente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
