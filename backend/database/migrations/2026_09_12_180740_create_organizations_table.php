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

            // Source link the user pasted plus the identifiers we parse out of it.
            $table->text('url');
            $table->string('yandex_id')->unique();   // permalink / businessId
            $table->string('slug')->nullable();

            // Card data pulled from Yandex.
            $table->string('title')->nullable();
            $table->string('address')->nullable();
            $table->json('categories')->nullable();

            // Rating & the two distinct counters the spec asks to separate.
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);  // «оценки»
            $table->unsignedInteger('reviews_count')->default(0);  // «отзывы»

            // Parsing lifecycle so the UI can show progress / errors.
            $table->string('parse_status')->default('pending'); // pending|queued|parsing|completed|failed
            $table->unsignedTinyInteger('parse_progress')->default(0); // 0..100
            $table->string('parse_error_reason')->nullable();
            $table->text('parse_error')->nullable();
            $table->timestamp('last_parsed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
