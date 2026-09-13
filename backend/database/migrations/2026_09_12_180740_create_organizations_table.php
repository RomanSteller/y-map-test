<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->string('yandex_id')->unique();   // permalink / businessId
            $table->string('slug')->nullable();

            $table->string('title')->nullable();
            $table->string('address')->nullable();

            // Рейтинг и два РАЗНЫХ счётчика, которые ТЗ просит не путать.
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);  // оценки
            $table->unsignedInteger('reviews_count')->default(0);  // отзывы

            $table->text('parse_error')->nullable();       // текст последней ошибки парсинга
            $table->timestamp('last_parsed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
