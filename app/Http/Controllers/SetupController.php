<?php

namespace App\Http\Controllers;

use App\Models\SetupToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function show(string $token): Response
    {
        $this->authorizeSetup($token);

        return Inertia::render('auth/setup', ['token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->authorizeSetup($token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($token, $data): User {
            $setupToken = SetupToken::findUsable($token);

            abort_if($setupToken === null || User::query()->exists(), 404);

            $setupToken->forceFill(['used_at' => now()])->save();

            return User::query()->create([...$data, 'email_verified_at' => now()]);
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->route('settings.security');
    }

    private function authorizeSetup(string $token): void
    {
        abort_if(User::query()->exists() || SetupToken::findUsable($token) === null, 404);
    }
}
