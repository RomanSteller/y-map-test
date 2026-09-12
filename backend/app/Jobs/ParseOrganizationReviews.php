<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Yandex\Contracts\ReviewParser;
use App\Services\Yandex\Exceptions\ParserException;
use App\Services\Yandex\ReviewStore;
use App\Services\Yandex\YandexUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Парсит одну организацию в фоне. Это намеренно джоба в очереди, а не работа
 * внутри HTTP-запроса: вытянуть до ~600 отзывов (а в масштабе из ТЗ — ~50
 * филиалов разом) слишком долго и хрупко, чтобы держать ради этого открытым
 * веб-запрос. Контроллер просто ставит джобу и сразу отвечает, а интерфейс
 * опрашивает статус организации.
 */
class ParseOrganizationReviews implements ShouldQueue
{
    use Queueable;

    /** Пробуем несколько раз — сетевые сбои и мягкий антибот обычно временные. */
    public int $tries = 3;

    /** Паузы между попытками (сек), нарастающие. */
    public array $backoff = [30, 120, 300];

    /** Полный сбор ~600 отзывов с вежливыми паузами может занять минуты. */
    public int $timeout = 900;

    public function __construct(public int $organizationId)
    {
    }

    public function handle(ReviewParser $parser, ReviewStore $store): void
    {
        $organization = Organization::find($this->organizationId);
        if ($organization === null) {
            return;
        }

        $organization->update([
            'parse_status' => Organization::STATUS_PARSING,
            'parse_progress' => 0,
            'parse_error' => null,
            'parse_error_reason' => null,
        ]);

        try {
            $url = YandexUrl::parse($organization->url);

            $data = $parser->parse($url, function (int $progress, string $message) use ($organization) {
                // Сохраняем прогресс, чтобы эндпоинт статуса мог его отдать.
                $organization->forceFill([
                    'parse_progress' => max(0, min(100, $progress)),
                ])->save();
                Log::debug("Parse progress [{$organization->id}] {$progress}% — {$message}");
            });

            $snapshot = $store->persist($organization, $data);

            $organization->update([
                'parse_status' => Organization::STATUS_COMPLETED,
                'parse_progress' => 100,
            ]);

            Log::info('Organization parsed', [
                'organization_id' => $organization->id,
                'reviews_scraped' => $snapshot->reviews_scraped,
                'added' => $snapshot->reviews_added,
                'updated' => $snapshot->reviews_updated,
            ]);
        } catch (ParserException $e) {
            // Изменившуюся вёрстку повторять бесполезно — падаем сразу и громко.
            if (! $e->isRetryable()) {
                $this->markFailed($organization, $e->reason(), $e->getMessage());
                $this->fail($e);

                return;
            }

            // Ошибка из повторяемых: фиксируем причину и пробрасываем дальше,
            // чтобы очередь сделала ретрай.
            $organization->update([
                'parse_error_reason' => $e->reason(),
                'parse_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** Очередь зовёт этот метод, когда попытки кончились (или после fail()). */
    public function failed(Throwable $e): void
    {
        $organization = Organization::find($this->organizationId);
        if ($organization === null) {
            return;
        }

        $reason = $e instanceof ParserException ? $e->reason() : 'unknown';
        $this->markFailed($organization, $reason, $e->getMessage());
    }

    private function markFailed(Organization $organization, string $reason, string $message): void
    {
        $organization->update([
            'parse_status' => Organization::STATUS_FAILED,
            'parse_error_reason' => $reason,
            'parse_error' => $message,
        ]);
    }
}
