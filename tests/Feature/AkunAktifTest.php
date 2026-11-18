<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkunAktifTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $user = User::factory()->staf()->create(['aktif' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sesi_akun_yang_dinonaktifkan_langsung_diputus(): void
    {
        $user = User::factory()->staf()->create(['aktif' => true]);

        $this->actingAs($user);
        $user->update(['aktif' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
