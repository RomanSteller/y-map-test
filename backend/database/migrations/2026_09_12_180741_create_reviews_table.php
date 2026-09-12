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

            // Стабильный id из Яндекса — по нему делаем upsert, чтобы не плодить дубли.
            $table->string('external_id');

            $table->string('author')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->longText('text')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Хэш значимых полей — так ловим отредактированный отзыв между двумя
            // парсингами, не сравнивая каждую колонку по отдельности.
            $table->string('content_hash', 40)->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            // Отзывы показываем от новых к старым и листаем по 50 штук.
            $table->index(['organization_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
