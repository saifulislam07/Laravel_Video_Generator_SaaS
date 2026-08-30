<?php

namespace App\Services\Social;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Meta Graph API client for OAuth + publishing video to a Facebook Page
 * and Instagram (Reels).
 *
 * @see https://developers.facebook.com/docs/instagram-api/guides/content-publishing
 * @see https://developers.facebook.com/docs/graph-api/reference/page/videos
 */
class MetaGraph
{
    /** OAuth scopes needed to list pages and publish video. */
    public const SCOPES = [
        'pages_show_list', 'pages_manage_posts', 'pages_read_engagement',
        'business_management', 'instagram_basic', 'instagram_content_publish',
    ];

    public function isConfigured(): bool
    {
        return filled($this->config('client_id'))
            && filled($this->config('client_secret'))
            && filled($this->config('redirect'));
    }

    public function authUrl(string $state): string
    {
        return "https://www.facebook.com/{$this->version()}/dialog/oauth?".http_build_query([
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->config('redirect'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', self::SCOPES),
        ]);
    }

    public function exchangeCodeForToken(string $code): string
    {
        $data = $this->graph()->get('oauth/access_token', [
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'redirect_uri' => $this->config('redirect'),
            'code' => $code,
        ])->json();

        return $data['access_token'] ?? throw new RuntimeException('Facebook code exchange failed.');
    }

    public function longLivedToken(string $shortToken): array
    {
        $data = $this->graph()->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'fb_exchange_token' => $shortToken,
        ])->json();

        return [
            'token' => $data['access_token'] ?? throw new RuntimeException('Facebook long-lived token exchange failed.'),
            'expires_in' => $data['expires_in'] ?? null,
        ];
    }

    /**
     * @return array<int, array{id:string,name:string,access_token:string,instagram?:array{id:string,username:string}}>
     */
    public function pages(string $userToken): array
    {
        $data = $this->graph()->get('me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            'access_token' => $userToken,
        ])->json('data', []);

        return collect($data)->map(fn ($page) => [
            'id' => $page['id'],
            'name' => $page['name'],
            'access_token' => $page['access_token'],
            'instagram' => isset($page['instagram_business_account'])
                ? ['id' => $page['instagram_business_account']['id'], 'username' => $page['instagram_business_account']['username'] ?? '']
                : null,
        ])->all();
    }

    public function publishPageVideo(string $pageId, string $pageToken, string $videoUrl, string $caption): string
    {
        $data = $this->graph()->post("{$pageId}/videos", [
            'file_url' => $videoUrl,
            'description' => $caption,
            'access_token' => $pageToken,
        ])->json();

        return $data['id'] ?? throw new RuntimeException('Facebook video publish failed: '.json_encode($data));
    }

    public function publishInstagramReel(string $igUserId, string $token, string $videoUrl, string $caption): string
    {
        $container = $this->graph()->post("{$igUserId}/media", [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'caption' => $caption,
            'access_token' => $token,
        ])->json('id') ?? throw new RuntimeException('Instagram media container creation failed.');

        $this->waitForContainer($container, $token);

        $data = $this->graph()->post("{$igUserId}/media_publish", [
            'creation_id' => $container,
            'access_token' => $token,
        ])->json();

        return $data['id'] ?? throw new RuntimeException('Instagram publish failed: '.json_encode($data));
    }

    private function waitForContainer(string $containerId, string $token, int $attempts = 10): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->graph()->get($containerId, [
                'fields' => 'status_code',
                'access_token' => $token,
            ])->json('status_code');

            if ($status === 'FINISHED') {
                return;
            }
            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new RuntimeException("Instagram media container {$status}.");
            }

            usleep(app()->runningUnitTests() ? 0 : 3_000_000);
        }

        throw new RuntimeException('Instagram media container did not finish in time.');
    }

    private function graph(): PendingRequest
    {
        return Http::baseUrl("https://graph.facebook.com/{$this->version()}")->acceptJson()->timeout(60);
    }

    private function version(): string
    {
        return $this->config('graph_version', 'v21.0');
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("services.facebook.{$key}", $default);
    }
}
