<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El modo demo (APP_DEMO=true) protege la cuenta compartida y no cambia nada
 * en la productiva.
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private function enDemo(): void
    {
        config(['demo.enabled' => true]);
    }

    public function test_sin_el_flag_no_cambia_nada(): void
    {
        $this->get(route('register'))->assertOk();
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('demo', null));
    }

    public function test_registro_google_y_recuperar_clave_van_al_login(): void
    {
        $this->enDemo();

        foreach (['register', 'google.redirect', 'password.request'] as $ruta) {
            $this->get(route($ruta))->assertRedirect(route('login'));
        }

        $this->post(route('register.store'), [
            'name' => 'Intruso', 'email' => 'intruso@example.com',
            'password' => 'una-clave-larga-1', 'password_confirmation' => 'una-clave-larga-1',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_el_login_muestra_las_credenciales(): void
    {
        $this->enDemo();

        $this->get(route('login'))->assertInertia(fn ($page) => $page
            ->where('demo.email', 'demo@pablomandile.com.ar')
            ->where('demo.password', 'demo1234'));
    }

    public function test_la_cuenta_no_se_puede_cambiar_ni_borrar(): void
    {
        $this->enDemo();
        $user = User::factory()->admin()->create([
            'name' => 'Usuario Demo',
            'email' => 'demo@pablomandile.com.ar',
            'password' => Hash::make('demo1234'),
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), ['name' => 'Otro', 'email' => 'otro@example.com'])
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'demo1234'])
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'demo1234',
                'password' => 'nueva-clave-larga-1',
                'password_confirmation' => 'nueva-clave-larga-1',
            ]);

        $user->refresh();
        $this->assertSame('Usuario Demo', $user->name);
        $this->assertSame('demo@pablomandile.com.ar', $user->email);
        $this->assertTrue(Hash::check('demo1234', $user->password));
    }

    public function test_el_2fa_se_rechaza_tambien_por_fetch(): void
    {
        $this->enDemo();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('two-factor.enable'))
            ->assertForbidden();

        $this->assertNull($user->fresh()?->two_factor_secret);
    }

    public function test_demo_reset_sin_el_flag_no_borra_nada(): void
    {
        $user = User::factory()->create();

        $this->artisan('demo:reset')->assertExitCode(1);

        $this->assertModelExists($user);
    }
}
