<?php

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GitHubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        Auth::user()->connections()->updateOrCreate(
            ['type' => ConnectionType::GitHub],
            [
                'name' => 'GitHub',
                'credentials' => $githubUser->token,
                'metadata' => [
                    'github_user_id' => $githubUser->getId(),
                    'github_username' => $githubUser->getNickname(),
                    'scopes' => ['repo'],
                ],
                'is_active' => true,
            ]
        );

        return redirect('/admin')
            ->with('success', 'GitHub connected successfully!');
    }

    public function disconnect(): RedirectResponse
    {
        Auth::user()->connections()->where('type', ConnectionType::GitHub)->delete();

        return redirect('/admin')
            ->with('success', 'GitHub disconnected.');
    }
}
