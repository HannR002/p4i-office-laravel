<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Role $roleStaff;
    private Role $roleReviewer;
    private Role $roleAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'legal_name' => 'Yayasan P4I',
            'acronym' => 'P4I',
        ]);

        $this->roleStaff = Role::create([
            'role_key' => 'STAFF',
            'label' => 'Staf Administrasi',
        ]);

        $this->roleReviewer = Role::create([
            'role_key' => 'REVIEWER',
            'label' => 'Reviewer Pejabat',
        ]);

        $this->roleAdmin = Role::create([
            'role_key' => 'SYSTEM_ADMIN',
            'label' => 'Administrator Sistem',
        ]);
    }

    public function test_staff_cannot_access_reviewer_approval_queue(): void
    {
        $staff = User::create([
            'organization_id' => $this->org->id,
            'email' => 'staf@p4i.local',
            'display_name' => 'Staf Biasa',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $staff->roles()->attach($this->roleStaff->id);

        $response = $this->actingAs($staff)->get('/approvals');

        $response->assertStatus(403);
    }

    public function test_reviewer_can_access_reviewer_approval_queue(): void
    {
        $reviewer = User::create([
            'organization_id' => $this->org->id,
            'email' => 'reviewer@p4i.local',
            'display_name' => 'Reviewer Pejabat',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $reviewer->roles()->attach($this->roleReviewer->id);

        $response = $this->actingAs($reviewer)->get('/approvals');

        $response->assertStatus(200);
        $response->assertSee('Antrean Pemeriksaan');
    }

    public function test_admin_can_access_reviewer_approval_queue(): void
    {
        $admin = User::create([
            'organization_id' => $this->org->id,
            'email' => 'admin@p4i.local',
            'display_name' => 'Admin Sistem',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $admin->roles()->attach($this->roleAdmin->id);

        $response = $this->actingAs($admin)->get('/approvals');

        $response->assertStatus(200);
    }

    public function test_dashboard_displays_user_roles(): void
    {
        $staff = User::create([
            'organization_id' => $this->org->id,
            'email' => 'staf@p4i.local',
            'display_name' => 'Budi Staf',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $staff->roles()->attach($this->roleStaff->id);

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Budi Staf');
        $response->assertSee('Staf Administrasi');
        $response->assertSee('DRAF — BELUM RESMI');
    }
}
