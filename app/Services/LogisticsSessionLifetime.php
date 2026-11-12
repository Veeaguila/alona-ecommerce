<?php

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class LogisticsSessionLifetime
{
    public const IDLE_SECONDS = 30 * 60;
    public const ACTIVITY_KEY = 'logistics.last_activity_at';

    public function validToken(PersonalAccessToken $token, bool $otherwiseValid = true): bool
    {
        if (!$otherwiseValid || $token->expires_at?->isPast()) return false;
        if (!$token->tokenable instanceof User || $token->tokenable->usertype !== 'sorting_center') return $otherwiseValid;

        // Sanctum invokes this before updating last_used_at. A rejected request must not revive a token.
        $lastActivity = $token->last_used_at ?? $token->created_at;
        return $lastActivity && $lastActivity->timestamp > now()->timestamp - self::IDLE_SECONDS;
    }

    public function ownedWebToken(User $user, ?string $plainTextToken): ?PersonalAccessToken
    {
        $token = $plainTextToken ? PersonalAccessToken::findToken($plainTextToken) : null;
        return $token && (int) $token->tokenable_id === (int) $user->id && $token->tokenable_type === $user->getMorphClass() ? $token : null;
    }
}
