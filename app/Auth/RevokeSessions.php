<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Signs a user out everywhere: after a password change or when the account is deleted.
 */
class RevokeSessions
{
    public function __invoke(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
