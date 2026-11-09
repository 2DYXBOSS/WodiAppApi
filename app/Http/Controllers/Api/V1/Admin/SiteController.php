<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Http\Requests\Admin\SiteContentRequest;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use App\Services\VitrineRebuild;
use Illuminate\Http\JsonResponse;

/** `GET/PUT /admin/site-content` (content.manage) et `GET/PUT /admin/settings` (settings.manage). */
class SiteController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogger $logger,
        private readonly VitrineRebuild $vitrine,
    ) {}

    public function content(): JsonResponse
    {
        return response()->json($this->settings->adminSiteContent());
    }

    public function updateContent(SiteContentRequest $request): JsonResponse
    {
        $result = $this->settings->saveAdminSiteContent($request->content());
        $this->logger->log('site_content.updated', null, array_keys($request->validated()));
        $this->vitrine->request();

        return response()->json($result);
    }

    public function settings(): JsonResponse
    {
        return response()->json($this->settings->adminSettings());
    }

    public function updateSettings(SettingsRequest $request): JsonResponse
    {
        $result = $this->settings->saveAdminSettings($request->validated());
        $this->logger->log('settings.updated', null, array_keys($request->validated()));
        $this->vitrine->request();

        return response()->json($result);
    }
}
