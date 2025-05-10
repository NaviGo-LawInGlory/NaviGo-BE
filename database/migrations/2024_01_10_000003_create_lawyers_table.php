<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lawyers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('specialization');
            $table->string('location');
            $table->decimal('rating', 3, 2);
            $table->integer('experience_years');
            $table->string('image_url');
            $table->string('email');
            $table->string('phone');
            $table->boolean('available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyers');
    }
};
