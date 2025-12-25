<?php

namespace App\Http\Controllers;

use App\Models\GitHubConnection;
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

        GitHubConnection::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'access_token' => $githubUser->token,
                'github_user_id' => $githubUser->getId(),
                'github_username' => $githubUser->getNickname(),
                'scopes' => ['repo'],
            ]
        );

        return redirect('/admin')
            ->with('success', 'GitHub connected successfully!');
    }

    public function disconnect(): RedirectResponse
    {
        GitHubConnection::where('user_id', Auth::id())->delete();

        return redirect('/admin')
            ->with('success', 'GitHub disconnected.');
    }
}
