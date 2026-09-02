<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La raíz no muestra nada: es una app de gestión interna, así que manda al panel
 * o al login según haya sesión.
 */
class PortadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visitante_sin_sesion_va_al_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_un_usuario_con_sesion_va_al_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
