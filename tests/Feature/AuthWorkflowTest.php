<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'legal_name' => 'Yayasan P4I',
            'acronym' => 'P4I',
        ]);

        $this->role = Role::create([
            'role_key' => 'STAFF',
            'label' => 'Staf Administrasi',
        ]);
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('P4I Office');
        $response->assertSee('Hostinger Business Web Hosting');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::create([
            'organization_id' => $this->org->id,
            'email' => 'staf.test@p4i.local',
            'display_name' => 'Staf Pengujian',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $user->roles()->attach($this->role->id);

        $response = $this->post('/login', [
            'email' => 'staf.test@p4i.local',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::create([
            'organization_id' => $this->org->id,
            'email' => 'staf.test@p4i.local',
            'display_name' => 'Staf Pengujian',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->post('/login', [
            'email' => 'staf.test@p4i.local',
            'password' => 'SalahPassword!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_user_is_prevented_from_login(): void
    {
        User::create([
            'organization_id' => $this->org->id,
            'email' => 'disabled.test@p4i.local',
            'display_name' => 'Akun Dinonaktifkan',
            'password' => Hash::make('Password123!'),
            'status' => 'DISABLED',
        ]);

        $response = $this->post('/login', [
            'email' => 'disabled.test@p4i.local',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::create([
            'organization_id' => $this->org->id,
            'email' => 'staf.test@p4i.local',
            'display_name' => 'Staf Pengujian',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_middleware_revokes_session_if_user_becomes_disabled(): void
    {
        $user = User::create([
            'organization_id' => $this->org->id,
            'email' => 'staf.test@p4i.local',
            'display_name' => 'Staf Dinonaktifkan Mendadak',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);

        // Pengguna login dan mengakses dashboard
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Administrator mengubah status pengguna di database menjadi DISABLED
        $user->update(['status' => 'DISABLED']);

        // Request berikutnya harus membatalkan sesi dan meredirect ke login
        $followUpResponse = $this->actingAs($user->fresh())->get('/dashboard');
        $followUpResponse->assertRedirect('/login');
        $this->assertGuest();
    }
}
