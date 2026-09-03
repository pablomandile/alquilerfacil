<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Login con Google por OAuth2 (authorization code). Se hace a mano con el cliente
 * HTTP de Laravel en vez de con Socialite: Socialite todavía pide guzzle ^7 y el
 * proyecto va con guzzle 8. Son dos llamadas, no se gana nada trayendo la
 * librería y media docena de dependencias más.
 */
class GoogleController extends Controller
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    /** Manda al usuario a Google para que autorice. */
    public function redirect(Request $request): SymfonyRedirect
    {
        $request->session()->put('google_oauth_state', $state = Str::random(40));

        return redirect(self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    /**
     * Google vuelve acá con el código. Buscamos al usuario por google_id o por
     * email; si no existe, lo creamos como Propietario (igual que el registro
     * por email, que hoy está abierto). El email de Google ya viene verificado.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');

        if ($request->filled('error') || ! $request->filled('code')
            || ! hash_equals((string) $expectedState, (string) $request->input('state'))) {
            return $this->fail('No se pudo completar el ingreso con Google. Probá de nuevo.');
        }

        $token = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
            'code' => $request->input('code'),
        ]);

        $profile = $token->successful()
            ? Http::withToken($token->json('access_token'))->get(self::USERINFO_URL)
            : null;

        if ($profile === null || $profile->failed() || ! $profile->json('email')) {
            return $this->fail('Google no devolvió los datos de la cuenta. Probá de nuevo.');
        }

        if ($profile->json('email_verified') === false) {
            return $this->fail('Tu email de Google no está verificado, no podemos usarlo para entrar.');
        }

        $user = User::query()
            ->where('google_id', $profile->json('sub'))
            ->orWhere('email', $profile->json('email'))
            ->first();

        if ($user === null) {
            $user = new User;
            $user->name = $profile->json('name') ?: $profile->json('email');
            $user->email = $profile->json('email');
            $user->rol = RolUsuario::Propietario;
        }

        // Primer ingreso con Google (o cuenta que ya existía por email): vinculamos
        // la cuenta y damos por verificado el email, que Google ya confirmó.
        $user->google_id = $profile->json('sub');
        $user->avatar = $profile->json('picture');

        if (! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        }

        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function fail(string $mensaje): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['oauth' => $mensaje]);
    }
}
