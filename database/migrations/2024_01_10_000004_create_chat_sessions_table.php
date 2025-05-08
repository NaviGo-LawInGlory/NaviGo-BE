<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('chat_session_id');
            $table->text('content');
            $table->boolean('is_user');
            $table->timestamps();
            $table->foreign('chat_session_id')
                  ->references('id')
                  ->on('chat_sessions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('chat_sessions');
    }
};
