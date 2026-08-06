<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ventas reales del equipo comercial, sincronizadas desde Google Sheets.
     * Una fila por (fuente, asesor, mes). El asesor se guarda con el nombre
     * original de la planilla y, si matchea con un usuario del CRM, su user_id.
     * La "fuente" es el ID de la planilla de origen: permite sumar varias
     * hojas de cálculo sin que se pisen entre sí.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('asesor', 191);
            $table->char('mes', 7);
            $table->decimal('monto', 14, 2)->default(0);
            $table->string('fuente', 191)->default('planilla');
            $table->timestamp('sincronizada_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['asesor', 'mes', 'fuente']);
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
