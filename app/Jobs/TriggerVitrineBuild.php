<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TriggerVitrineBuild implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function handle(): void
    {
        Cache::forget('wodi.vitrine-rebuild-pending');
        $url = config('wodi.vitrine_webhook.url');
        if (! $url) {
            return;
        }
        $body = json_encode(['event' => 'content.updated', 'at' => now()->toIso8601String()]);
        $signature = hash_hmac('sha256', $body, (string) config('wodi.vitrine_webhook.secret'));
        $response = Http::timeout(15)->withHeaders(['X-Wodi-Signature' => $signature])
            ->withBody($body, 'application/json')->post($url);
        if ($response->failed()) {
            throw new RuntimeException('Webhook de build vitrine : HTTP '.$response->status());
        }
    }
}
