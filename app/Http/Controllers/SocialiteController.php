<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)
            ->stateless()
            ->redirect();
    }

    public function callback(string $provider)
    {
        $socialUser = Socialite::driver($provider)
            ->stateless()
            ->user();

        $user = User::firstWhere('email', $socialUser->getEmail());

        if ($user) {
            $existingProvider = $user->providers()
                ->where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if (! $existingProvider) {
                $user->providers()->create([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);

                // if (! $user->avatar) {
                    $user->update([
                        'avatar' => $socialUser->getAvatar(),
                    ]);
                // }
            }
        } else {
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
            ]);

            $user->providers()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
        }

        Auth::login($user);

        return redirect()->intended('/');
    }
}