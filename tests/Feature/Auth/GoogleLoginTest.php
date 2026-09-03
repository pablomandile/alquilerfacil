<?php

namespace Tests\Feature\Auth;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    /** @param  array<string, mixed>  $profile */
    private function fakeGoogle(array $profile = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response(array_merge([
                'sub' => '11822', 'email' => 'dueno@gmail.com', 'email_verified' => true,
                'name' => 'Ana Dueña', 'picture' => 'https://lh3.googleusercontent.com/a/ana',
            ], $profile)),
        ]);
    }

    /** @return TestResponse */
    private function hitCallback(string $state = 'estado-valido', array $query = [])
    {
        return $this->withSession(['google_oauth_state' => 'estado-valido'])
            ->get(route('google.callback', array_merge(['code' => 'auth-code', 'state' => $state], $query)));
    }

    public function test_redirect_manda_a_google_y_guarda_el_state()
    {
        $response = $this->get(route('google.redirect'));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $response->assertRedirectContains('client_id=test-client-id');
        $response->assertRedirectContains('scope=openid');
        $this->assertNotEmpty(session('google_oauth_state'));
    }

    public function test_crea_un_usuario_propietario_nuevo_y_lo_loguea()
    {
        $this->fakeGoogle();

        $response = $this->hitCallback();

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'dueno@gmail.com')->sole();
        $this->assertSame(RolUsuario::Propietario, $user->rol);
        $this->assertSame('11822', $user->google_id);
        $this->assertSame('Ana Dueña', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
    }

    public function test_vincula_una_cuenta_que_ya_existia_por_email_sin_duplicar()
    {
        $existente = User::factory()->create([
            'email' => 'dueno@gmail.com',
            'rol' => RolUsuario::Admin,
        ]);

        $this->fakeGoogle();

        $this->hitCallback();

        $this->assertAuthenticatedAs($existente);
        $this->assertSame(1, User::where('email', 'dueno@gmail.com')->count());

        $existente->refresh();
        $this->assertSame('11822', $existente->google_id);
        $this->assertSame(RolUsuario::Admin, $existente->rol, 'no le cambia el rol al que ya existía');
    }

    public function test_rechaza_el_callback_si_el_state_no_coincide()
    {
        $this->fakeGoogle();

        $response = $this->hitCallback(state: 'estado-falso');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('oauth');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rechaza_un_email_de_google_sin_verificar()
    {
        $this->fakeGoogle(['email_verified' => false]);

        $response = $this->hitCallback();

        $response->assertSessionHasErrors('oauth');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rechaza_si_google_devuelve_error()
    {
        $response = $this->hitCallback(query: ['error' => 'access_denied']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('oauth');
        $this->assertGuest();
    }
}
