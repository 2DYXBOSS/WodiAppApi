<?php

namespace App\Services;

use App\Jobs\TriggerVitrineBuild;
use Illuminate\Support\Facades\Cache;

/**
 * Le site vitrine est prégénéré au build : après toute modification publique (catalogue, équipe,
 * galerie, contenus, paramètres), un webhook relance la génération (regroupé sur 2 minutes).
 */
class VitrineRebuild
{
    public function __construct(private readonly SettingsService $settings) {}

    public function request(): void
    {
        $this->settings->forgetPublicCache();
        if (! config('wodi.vitrine_webhook.url')) {
            return;
        }
        if (Cache::add('wodi.vitrine-rebuild-pending', true, 120)) {
            TriggerVitrineBuild::dispatch()->delay(now()->addSeconds(120))->afterCommit();
        }
    }
}
