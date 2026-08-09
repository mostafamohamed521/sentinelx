<?php

namespace App\Modules\Authentication\Identity\Application;

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Illuminate\Auth\Events\Verified;

class VerifyEmailAction
{
    /**
     * The `signed` route middleware already validated the URL's signature
     * and expiry before this Action runs — see 03-authentication-flow.md's
     * "signed, expiring URL" mechanism. This Action only checks that the
     * hash matches the target user's current email, then persists the
     * result to email_verified_at (ADR-006).
     */
    public function handle(string $id, string $hash): string
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            return 'Email already verified.';
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return 'Email verified successfully.';
    }
}
