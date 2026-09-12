<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * По одной строке на каждый удачный парсинг организации. Храня агрегаты
     * во времени, мы можем ответить на вопрос «что изменилось между
     * парсингами» (было → стало), не копируя целиком все отзывы каждый раз.
     */
    public function up(): void
    {
        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            // Сколько отзывов реально сохранили и сколько из них новых /
            // изменившихся по сравнению с прошлым снимком.
            $table->unsignedInteger('reviews_scraped')->default(0);
            $table->unsignedInteger('reviews_added')->default(0);
            $table->unsignedInteger('reviews_updated')->default(0);

            // Отпечаток всего набора отзывов — чтобы дёшево ловить изменения.
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
