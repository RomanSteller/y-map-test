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
 * Parses one organisation in the background. This is deliberately a queued job
 * and not something done inside the HTTP request: pulling up to ~600 reviews
 * (and, at the scale the spec describes, ~50 branches at once) is far too slow
 * and fragile to hold a web request open for. The controller just dispatches
 * this and returns immediately; the UI polls the organisation's status.
 */
class ParseOrganizationReviews implements ShouldQueue
{
    use Queueable;

    /** Retry a few times — network blips and soft anti-bot walls are transient. */
    public int $tries = 3;

    /** Exponential-ish backoff between attempts (seconds). */
    public array $backoff = [30, 120, 300];

    /** A full ~600-review pull with polite pauses can take minutes. */
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
                // Persist progress so the polling endpoint can report it.
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
            // A markup change is not worth retrying — fail fast and loudly.
            if (! $e->isRetryable()) {
                $this->markFailed($organization, $e->reason(), $e->getMessage());
                $this->fail($e);

                return;
            }

            // Retryable: record the reason, then rethrow so the queue retries.
            $organization->update([
                'parse_error_reason' => $e->reason(),
                'parse_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** Called by the queue when all retries are exhausted (or on fail()). */
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
