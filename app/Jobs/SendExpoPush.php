<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Envoi d'une notification push via le service Expo Push. */
class SendExpoPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly string $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(): void
    {
        if (! config('wodi.expo.enabled')) {
            return;
        }
        $tokens = DeviceToken::query()->where('user_id', $this->userId)->pluck('token')->all();
        if ($tokens === []) {
            return;
        }
        $messages = array_map(fn ($token) => [
            'to' => $token, 'title' => $this->title, 'body' => $this->body, 'data' => $this->data, 'sound' => 'default',
        ], $tokens);

        $request = Http::acceptJson()->timeout(15);
        if ($token = config('wodi.expo.access_token')) {
            $request = $request->withToken($token);
        }
        $response = $request->post(config('wodi.expo.url'), $messages);
        if ($response->failed()) {
            // Échec du fournisseur : le job est rejoué, la notification n'est pas considérée comme envoyée.
            throw new RuntimeException('Expo Push a répondu '.$response->status());
        }

        foreach ((array) $response->json('data', []) as $index => $ticket) {
            if (($ticket['status'] ?? null) === 'error' && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                DeviceToken::query()->where('token', $tokens[$index] ?? null)->delete();
            }
        }
    }
}
