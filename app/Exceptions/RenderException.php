<?php

namespace App\Exceptions;

use RuntimeException;

class RenderException extends RuntimeException
{
    public static function missingApiKey(): self
    {
        return new self('SHOTSTACK_API_KEY is not configured.');
    }

    public static function apiError(int $status, string $body): self
    {
        return new self("Shotstack API returned HTTP {$status}: {$body}");
    }
}
