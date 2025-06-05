<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['generated', 'analyzed']);
            $table->string('judul');
            $table->string('perjanjian');
            $table->string('pihak1');
            $table->string('pihak2');
            $table->text('deskripsi');
            $table->date('tanggal');
            $table->longText('content');
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
