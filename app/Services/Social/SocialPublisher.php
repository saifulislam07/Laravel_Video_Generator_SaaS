<?php

namespace App\Services\Social;

use App\Jobs\PublishRenderJob;
use App\Models\SocialAccount;
use App\Models\SocialPublication;
use App\Models\User;
use App\Models\VideoRender;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class SocialPublisher
{
    public function __construct(private readonly MetaGraph $graph) {}

    public function isConfigured(): bool
    {
        return $this->graph->isConfigured();
    }

    public function connectUrl(User $user): string
    {
        return $this->graph->authUrl(Crypt::encryptString((string) $user->id));
    }

    public function userFromState(string $state): ?User
    {
        try {
            return User::find((int) Crypt::decryptString($state));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Exchange the OAuth code and (re)store the user's Page + Instagram accounts.
     *
     * @return int number of accounts linked
     */
    public function linkAccounts(User $user, string $code): int
    {
        $short = $this->graph->exchangeCodeForToken($code);
        $long = $this->graph->longLivedToken($short);
        $expiresAt = $long['expires_in'] ? now()->addSeconds((int) $long['expires_in']) : null;

        $linked = 0;

        foreach ($this->graph->pages($long['token']) as $page) {
            $user->socialAccounts()->updateOrCreate(
                ['provider' => SocialAccount::PROVIDER_FACEBOOK_PAGE, 'provider_account_id' => $page['id']],
                ['name' => $page['name'], 'access_token' => $page['access_token'], 'token_expires_at' => $expiresAt],
            );
            $linked++;

            if ($page['instagram']) {
                $user->socialAccounts()->updateOrCreate(
                    ['provider' => SocialAccount::PROVIDER_INSTAGRAM, 'provider_account_id' => $page['instagram']['id']],
                    [
                        'name' => $page['instagram']['username'] ?: $page['name'],
                        'access_token' => $page['access_token'], // page token also drives the IG account
                        'token_expires_at' => $expiresAt,
                        'meta' => ['page_id' => $page['id']],
                    ],
                );
                $linked++;
            }
        }

        return $linked;
    }

    /** Queue a publish of a finished render to one connected account. */
    public function publish(VideoRender $render, SocialAccount $account, ?string $caption = null): SocialPublication
    {
        if ($render->status !== VideoRender::STATUS_DONE || blank($render->output_url)) {
            throw new RuntimeException('Only a finished render can be published.');
        }

        $publication = SocialPublication::create([
            'video_render_id' => $render->id,
            'social_account_id' => $account->id,
            'status' => SocialPublication::STATUS_PENDING,
        ]);

        PublishRenderJob::dispatch($publication, $caption ?? $this->defaultCaption($render));

        return $publication;
    }

    public function runPublish(SocialPublication $publication, string $caption): void
    {
        $account = $publication->account;
        $render = $publication->render;

        try {
            $externalId = $account->provider === SocialAccount::PROVIDER_INSTAGRAM
                ? $this->graph->publishInstagramReel($account->provider_account_id, $account->access_token, $render->output_url, $caption)
                : $this->graph->publishPageVideo($account->provider_account_id, $account->access_token, $render->output_url, $caption);

            $publication->update(['status' => SocialPublication::STATUS_PUBLISHED, 'external_id' => $externalId]);
        } catch (\Throwable $e) {
            $publication->update(['status' => SocialPublication::STATUS_FAILED, 'error_message' => $e->getMessage()]);

            throw $e;
        }
    }

    private function defaultCaption(VideoRender $render): string
    {
        return collect($render->project->scenes)
            ->pluck('dialogue_text')
            ->filter()
            ->implode(' ');
    }
}
