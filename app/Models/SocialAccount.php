<?php

namespace App\Models;

use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['user_id', 'provider', 'provider_account_id', 'name', 'access_token', 'token_expires_at', 'meta'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    public const PROVIDER_FACEBOOK_PAGE = 'facebook_page';
    public const PROVIDER_INSTAGRAM = 'instagram';

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** Tokens are encrypted at rest. */
    protected function accessToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return $this->provider === self::PROVIDER_INSTAGRAM
            ? "Instagram · {$this->name}"
            : "Facebook · {$this->name}";
    }
}
