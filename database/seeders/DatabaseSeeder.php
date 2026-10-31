<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\NumberingPolicy;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed initial organizational foundation and templates for P4I Office.
     */
    public function run(): void
    {
        // 1. Organisasi Induk
        $org = Organization::firstOrCreate(
            ['acronym' => 'Yayasan P4I'],
            [
                'legal_name' => 'Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia',
                'address_placeholder' => 'Gedung P4I Lantai 3, Jl. Pembangunan Nasional, Jakarta',
            ]
        );

        // 2. Unit Kerja
        $sekretariat = OrgUnit::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Sekretariat Utama'],
            ['active' => true]
        );

        $divisiRiset = OrgUnit::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Divisi Riset dan Kebijakan Pembangunan'],
            ['active' => true]
        );

        // 3. Peran Sistem
        $roleAdmin = Role::firstOrCreate(['role_key' => 'SYSTEM_ADMIN'], ['label' => 'Administrator Sistem']);
        $rolePengurus = Role::firstOrCreate(['role_key' => 'PENGURUS_YAYASAN'], ['label' => 'Pengurus Yayasan']);
        $roleReviewer = Role::firstOrCreate(['role_key' => 'REVIEWER'], ['label' => 'Reviewer / Penelaah Naskah']);
        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf Administrasi Persuratan']);

        // 4. Pengguna Utama
        $admin = User::firstOrCreate(
            ['email' => 'admin@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $sekretariat->id,
                'display_name' => 'Administrator Yayasan',
                'password' => Hash::make('AdminP4I_2026!'),
                'status' => 'ACTIVE',
            ]
        );
        $admin->roles()->syncWithoutDetaching([$roleAdmin->id]);

        $staf = User::firstOrCreate(
            ['email' => 'staf@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $sekretariat->id,
                'display_name' => 'Staf Sekretariat P4I',
                'password' => Hash::make('StafP4I_2026!'),
                'status' => 'ACTIVE',
            ]
        );
        $staf->roles()->syncWithoutDetaching([$roleStaff->id]);

        $reviewer = User::firstOrCreate(
            ['email' => 'reviewer@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $divisiRiset->id,
                'display_name' => 'Dr. Penelaah Riset, M.Si.',
                'password' => Hash::make('ReviewerP4I_2026!'),
                'status' => 'ACTIVE',
            ]
        );
        $reviewer->roles()->syncWithoutDetaching([$roleReviewer->id]);

        // 5. Kebijakan Penomoran (Safety: Mutlak is_active = false)
        $policy = NumberingPolicy::firstOrCreate(
            ['organization_id' => $org->id, 'series_key' => 'UMUM_P4I'],
            [
                'code' => 'P4I-SK',
                'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
                'reset_period' => 'YEAR',
                'padding' => 3,
                'is_active' => false, // SAFETY: Wajib FALSE
                'legacy_reconciled' => false,
            ]
        );

        // 6. Jenis Surat
        $typeMagang = LetterType::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'KET-MAGANG'],
            [
                'label' => 'Surat Keterangan Magang & Riset',
                'priority' => 'P0',
                'numbering_policy_id' => $policy->id,
                'is_active' => true,
            ]
        );

        $typeUndangan = LetterType::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'UND-RESMI'],
            [
                'label' => 'Surat Undangan Rapat / Kegiatan',
                'priority' => 'P1',
                'numbering_policy_id' => $policy->id,
                'is_active' => true,
            ]
        );

        $typeTugas = LetterType::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'TUG-RISET'],
            [
                'label' => 'Surat Tugas Penelitian Lapangan',
                'priority' => 'P1',
                'numbering_policy_id' => $policy->id,
                'is_active' => true,
            ]
        );

        // 7. Templat Surat
        LetterTemplate::firstOrCreate(
            ['organization_id' => $org->id, 'letter_type_id' => $typeMagang->id, 'version' => 1],
            [
                'title' => 'Templat Standar Keterangan Magang Riset',
                'description' => 'Format baku surat keterangan penyelesaian magang riset mahasiswa.',
                'form_schema' => [
                    'fields' => ['nama_mahasiswa', 'universitas', 'periode', 'keterangan'],
                ],
                'content_template' => '<p>Yang bertanda tangan di bawah ini, Pengurus Pusat Pendidikan dan Penelitian Pembangunan Indonesia (P4I), menerangkan bahwa:</p>
<p style="margin-left: 20px;">
<strong>Nama Mahasiswa / Peneliti:</strong> {{nama_mahasiswa}}<br>
<strong>Asal Perguruan Tinggi / Instansi:</strong> {{universitas}}<br>
<strong>Periode Pelaksanaan:</strong> {{periode}}
</p>
<p>Telah menyelesaikan seluruh rangkaian kegiatan magang dan penelitian pembangunan dengan predikat: <strong>{{keterangan}}</strong>.</p>
<p>Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>',
                'is_active' => true,
            ]
        );

        LetterTemplate::firstOrCreate(
            ['organization_id' => $org->id, 'letter_type_id' => $typeUndangan->id, 'version' => 1],
            [
                'title' => 'Templat Undangan Rapat Koordinasi Yayasan',
                'description' => 'Format standar undangan rapat internal yayasan dan mitra.',
                'form_schema' => [
                    'fields' => ['nama_kegiatan', 'hari_tanggal', 'waktu', 'tempat'],
                ],
                'content_template' => '<p>Dengan hormat,</p>
<p>Sehubungan dengan pelaksanaan agenda strategis yayasan, kami mengundang Bapak/Ibu untuk hadir pada:</p>
<p style="margin-left: 20px;">
<strong>Agenda / Kegiatan:</strong> {{nama_kegiatan}}<br>
<strong>Hari / Tanggal:</strong> {{hari_tanggal}}<br>
<strong>Waktu:</strong> {{waktu}}<br>
<strong>Tempat:</strong> {{tempat}}
</p>
<p>Atas perhatian dan kehadiran Bapak/Ibu, kami ucapkan terima kasih.</p>',
                'is_active' => true,
            ]
        );

        LetterTemplate::firstOrCreate(
            ['organization_id' => $org->id, 'letter_type_id' => $typeTugas->id, 'version' => 1],
            [
                'title' => 'Templat Penugasan Peneliti Lapangan (Nonaktif)',
                'description' => 'Templat draft percobaan yang sengaja dinonaktifkan untuk pengujian.',
                'form_schema' => ['fields' => ['lokasi', 'durasi']],
                'content_template' => '<p>Memberikan tugas penelitian di {{lokasi}} selama {{durasi}}.</p>',
                'is_active' => false, // NONAKTIF: untuk menguji M1-11
            ]
        );
    }
}
