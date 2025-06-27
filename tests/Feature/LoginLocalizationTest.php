<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginLocalizationTest extends TestCase
{
    /** @test */
    public function it_shows_login_page_in_english_by_default()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        // Assuming the English login page contains 'Email Address' and 'Password'
        $response->assertSee('Email Address');
        $response->assertSee('Password');
        $response->assertSee('Log in');
    }

    /** @test */
    public function it_shows_login_page_in_spanish_when_requested()
    {
        $response = $this->withMiddleware(\App\Http\Middleware\ExtractLocalizationStrings::class)->get('/login', [
            'Accept-Language' => 'es',
        ]);
        $response->assertStatus(200);

        // Assuming the Spanish login page contains 'Correo electrónico' and 'Contraseña'
        $response->assertSee('Correo electrónico');
        $response->assertSee('Contraseña');
        $response->assertSee('Iniciar sesión');
    }
}

