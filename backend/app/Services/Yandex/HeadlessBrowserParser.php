<?php

namespace App\Services\Yandex;

use App\Services\Yandex\Contracts\ReviewParser;
use App\Services\Yandex\DTO\OrganizationData;
use App\Services\Yandex\Exceptions\BlockedException;
use App\Services\Yandex\Exceptions\ParserException;
use App\Services\Yandex\Exceptions\SourceUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Боевой парсер. С Яндексом напрямую НЕ разговаривает — перекладывает работу на
 * Node/Playwright-сервис-скрапер (см. /scraper), который рулит настоящим
 * headless-хромиумом.
 *
 * Почему headless-браузер, а не внутренний JSON-эндпоинт? Запрос fetchReviews у
 * Яндекса подписан (параметр `s=` плюс ротирующиеся csrfToken/sessionId,
 * которые считаются прямо на странице). Воспроизводить эту подпись на бэке —
 * хрупко: ломается каждый раз, когда Яндекс подкручивает свой антибот. Гораздо
 * надёжнее дать реальной странице самой посчитать подпись, а нам — просто
 * собрать ответы. Полный разбор компромиссов — в README.
 *
 * Скрапер стримит JSON построчно: пачка событий {type:"progress"}, а в конце
 * одно {type:"result"} (или {type:"error"}).
 */
final class HeadlessBrowserParser implements ReviewParser
{
    public function __construct(
        private readonly ResponseValidator $validator,
        private readonly string $scraperUrl,
        private readonly int $timeout,
        private readonly int $maxReviews,
    ) {
    }

    public function parse(YandexUrl $url, ?callable $onProgress = null): OrganizationData
    {
        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout(15)
                ->withOptions(['stream' => true])
                ->post(rtrim($this->scraperUrl, '/').'/scrape', [
                    'url' => $url->normalized,
                    'orgId' => $url->orgId,
                    'maxReviews' => $this->maxReviews,
                ]);
        } catch (Throwable $e) {
            throw new SourceUnavailableException('Сервис парсинга недоступен: '.$e->getMessage(), previous: $e);
        }

        // 429/403 — почти наверняка антибот сработал.
        if ($response->status() === 429 || $response->status() === 403) {
            throw new BlockedException('Похоже, нас временно заблокировал Яндекс (HTTP '.$response->status().').');
        }

        if ($response->serverError()) {
            throw new SourceUnavailableException('Сервис парсинга вернул ошибку '.$response->status().'.');
        }

        return $this->consumeStream($response->toPsrResponse()->getBody(), $onProgress);
    }

    private function consumeStream($body, ?callable $onProgress): OrganizationData
    {
        $buffer = '';
        $result = null;

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($nl = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $nl));
                $buffer = substr($buffer, $nl + 1);
                if ($line === '') {
                    continue;
                }

                $event = json_decode($line, true);
                if (! is_array($event) || ! isset($event['type'])) {
                    continue;
                }

                // Прогресс — прокидываем в колбэк, ошибку — превращаем в
                // типизированное исключение, результат — запоминаем.
                match ($event['type']) {
                    'progress' => $onProgress && $onProgress(
                        (int) ($event['progress'] ?? 0),
                        (string) ($event['message'] ?? '')
                    ),
                    'error' => throw $this->mapError($event),
                    'result' => $result = $event['data'] ?? null,
                    default => null,
                };
            }
        }

        if (! is_array($result)) {
            throw new SourceUnavailableException('Парсер не вернул результат.');
        }

        return $this->validator->validate($result);
    }

    private function mapError(array $event): ParserException
    {
        $reason = $event['reason'] ?? 'source_unavailable';
        $message = $event['message'] ?? 'Ошибка парсинга.';
        Log::warning('Scraper reported error', $event);

        return match ($reason) {
            'blocked' => new BlockedException($message),
            'markup_changed' => new Exceptions\MarkupChangedException($message),
            'empty_result' => new Exceptions\EmptyResultException($message),
            'invalid_url' => new Exceptions\InvalidUrlException($message),
            default => new SourceUnavailableException($message),
        };
    }
}
