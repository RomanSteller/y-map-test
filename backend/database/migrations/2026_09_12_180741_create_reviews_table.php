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

            // Stable id from Yandex — the key we upsert on to avoid duplicates.
            $table->string('external_id');

            $table->string('author')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->longText('text')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Hash of the meaningful fields; lets us detect an edited review
            // between two parses without diffing every column.
            $table->string('content_hash', 40)->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            // Reviews are listed newest-first and paginated 50 at a time.
            $table->index(['organization_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
