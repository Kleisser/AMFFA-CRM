<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->enum('channel', ['whatsapp', 'email', 'facebook', 'instagram', 'messenger', 'call', 'visit', 'other'])->default('whatsapp');
            $table->enum('status', ['open', 'closed', 'pending'])->default('open');
            $table->string('subject')->nullable();
            $table->text('channel_conversation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming');
            $table->text('content');
            $table->string('type')->default('text');
            $table->json('attachments')->nullable();
            $table->string('channel_message_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->enum('status', ['completed', 'missed', 'busy', 'failed'])->default('completed');
            $table->integer('duration')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('called_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
