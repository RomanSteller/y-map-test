<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // id отзыва из Яндекса — по нему upsert, чтобы не плодить дубли.
            $table->string('external_id');
            $table->string('author')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->longText('text')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'reviewed_at']); // листаем по 50, от новых к старым
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
