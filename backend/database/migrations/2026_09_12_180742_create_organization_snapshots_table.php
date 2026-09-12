<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per successful parse of an organisation. Keeping the aggregate
     * numbers over time is what lets us answer "что изменилось между
     * парсингами" (было → стало) without storing full copies of every review.
     */
    public function up(): void
    {
        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            // How many reviews we actually stored, and how many were new /
            // changed relative to the previous snapshot.
            $table->unsignedInteger('reviews_scraped')->default(0);
            $table->unsignedInteger('reviews_added')->default(0);
            $table->unsignedInteger('reviews_updated')->default(0);

            // Fingerprint of the whole review set for cheap change detection.
            $table->string('reviews_hash', 40)->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
    }
};
