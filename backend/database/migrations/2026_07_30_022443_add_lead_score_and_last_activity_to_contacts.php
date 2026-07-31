<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->integer('lead_score')->default(0)->after('deal_value');
            $table->timestamp('last_activity_at')->nullable()->after('lead_score');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['lead_score', 'last_activity_at']);
        });
    }
};
