<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\SignatoryMandate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StagingUatSeeder extends Seeder
{
    /**
     * Seed synthetic UAT data for Hostinger Staging verification.
     */
    public function run(): void
    {
        // 1. Jalankan seeder fondasi utama terlebih dahulu
        $this->call(DatabaseSeeder::class);

        $org = Organization::where('acronym', 'Yayasan P4I')->firstOrFail();
        $unitRiset = OrgUnit::where('organization_id', $org->id)->where('name', 'Divisi Riset dan Kebijakan Pembangunan')->first();
        if (! $unitRiset) {
            $unitRiset = OrgUnit::firstOrCreate(
                ['organization_id' => $org->id, 'name' => 'Divisi Riset dan Inovasi'],
                ['active' => true]
            );
        }

        $unitSdm = OrgUnit::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Divisi SDM dan Umum'],
            ['active' => true]
        );

        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf Konseptor']);
        $roleReviewer = Role::firstOrCreate(['role_key' => 'REVIEWER'], ['label' => 'Pejabat Pemeriksa']);
        $roleAdmin = Role::firstOrCreate(['role_key' => 'SYSTEM_ADMIN'], ['label' => 'Administrator Sistem']);

        // 2. Akun Sintetis Khusus UAT
        $staffUat = User::firstOrCreate(
            ['email' => 'staf.uat@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $unitRiset->id,
                'display_name' => 'Rina Kartika (Staf UAT)',
                'password' => Hash::make('UatPassword123!'),
                'status' => 'ACTIVE',
            ]
        );
        $staffUat->roles()->syncWithoutDetaching([$roleStaff->id]);

        $reviewerUat = User::firstOrCreate(
            ['email' => 'reviewer.uat@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $unitRiset->id,
                'display_name' => 'Dr. Bambang Sutrisno (Pemeriksa Bermandat UAT)',
                'password' => Hash::make('UatPassword123!'),
                'status' => 'ACTIVE',
            ]
        );
        $reviewerUat->roles()->syncWithoutDetaching([$roleReviewer->id]);

        // Mandat aktif untuk reviewer UAT
        SignatoryMandate::firstOrCreate(
            [
                'organization_id' => $org->id,
                'user_id' => $reviewerUat->id,
                'decree_number' => 'SK-SIMULASI-UAT/P4I/2026/01',
            ],
            [
                'unit_id' => $unitRiset->id,
                'status' => 'ACTIVE',
                'valid_from' => Carbon::now()->subDays(10)->toDateString(),
                'valid_to' => Carbon::now()->addDays(90)->toDateString(),
                'can_review' => true,
                'can_approve' => true,
            ]
        );

        $unauthUat = User::firstOrCreate(
            ['email' => 'unauth.uat@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $unitSdm->id,
                'display_name' => 'Dedi Kurniawan (Reviewer Tanpa Mandat UAT)',
                'password' => Hash::make('UatPassword123!'),
                'status' => 'ACTIVE',
            ]
        );
        $unauthUat->roles()->syncWithoutDetaching([$roleReviewer->id]);

        $adminUat = User::firstOrCreate(
            ['email' => 'admin.uat@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $unitRiset->id,
                'display_name' => 'Agus Setiawan (Admin UAT)',
                'password' => Hash::make('UatPassword123!'),
                'status' => 'ACTIVE',
            ]
        );
        $adminUat->roles()->syncWithoutDetaching([$roleAdmin->id]);

        // 3. Jenis Surat & Templat Khusus UAT
        $typeUat = LetterType::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'KET-UAT'],
            [
                'label' => 'Surat Keterangan Riset UAT',
                'priority' => 'P0',
                'is_active' => true,
            ]
        );

        LetterTemplate::firstOrCreate(
            ['organization_id' => $org->id, 'letter_type_id' => $typeUat->id, 'version' => 1],
            [
                'title' => 'Templat Surat Keterangan UAT',
                'description' => 'Templat pengujian UAT staging.',
                'form_schema' => ['fields' => ['nama_kegiatan', 'lokasi']],
                'content_template' => '<p>Menerangkan pelaksanaan <strong>{{nama_kegiatan}}</strong> yang berlokasi di <strong>{{lokasi}}</strong> dalam rangka pengujian sistem P4I Office v1.0.</p>',
                'is_active' => true,
            ]
        );
    }
}
