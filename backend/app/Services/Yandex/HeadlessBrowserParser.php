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
 * Production parser. It does NOT talk to Yandex directly — it delegates to the
 * Node/Playwright scraper micro-service (see /scraper), which drives a real
 * headless Chromium.
 *
 * Why a headless browser and not the internal JSON endpoint? Yandex's
 * fetchReviews call is signed (an `s=` parameter + rotating csrfToken/sessionId
 * computed in-page). Reproducing that signature server-side is brittle and
 * breaks whenever Yandex tweaks their anti-bot code. Letting the real page
 * compute the signature and simply harvesting the responses is far more robust.
 * Full trade-off analysis is in the README.
 *
 * The scraper streams newline-delimited JSON: many {type:"progress"} events
 * followed by one {type:"result"} (or {type:"error"}).
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
