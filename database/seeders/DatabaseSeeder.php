<?php

namespace Database\Seeders;

use App\Models\AgendaPolicy;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\IncomingLetter;
use App\Models\LegacyRegisterBatch;
use App\Models\LegacyRegisterRow;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterStatusHistory;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\NumberingPolicy;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\ReconciliationAuditLog;
use App\Models\Role;
use App\Models\SignatoryMandate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed initial organizational foundation, templates, users, mandates, and sample workflow for P4I Office.
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

        $pengurus = User::firstOrCreate(
            ['email' => 'pengurus@p4i.or.id'],
            [
                'organization_id' => $org->id,
                'unit_id' => $sekretariat->id,
                'display_name' => 'Drs. H. Mulyadi, M.M. (Ketua Yayasan)',
                'password' => Hash::make('PengurusP4I_2026!'),
                'status' => 'ACTIVE',
            ]
        );
        $pengurus->roles()->syncWithoutDetaching([$rolePengurus->id, $roleReviewer->id]);

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
        $templateMagang = LetterTemplate::firstOrCreate(
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

        // 8. Mandat Pejabat (Kewenangan Reviewer & Penandatangan)
        // PENTING: Mandat lokal berlabel [SIMULASI] (M2-FR-17)
        $mandateReviewer = SignatoryMandate::firstOrCreate(
            ['organization_id' => $org->id, 'decree_number' => '[SIMULASI] SK-P4I/MANDAT/2026/001'],
            [
                'user_id' => $reviewer->id,
                'letter_type_id' => null, // Semua jenis surat
                'unit_id' => $divisiRiset->id,
                'can_review' => true,
                'can_approve' => false,
                'can_sign' => false,
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
                'status' => 'ACTIVE',
                'is_simulation' => true,
                'notes' => 'Mandat simulasi penelaahan naskah ilmiah dan draf umum yayasan.',
            ]
        );

        $mandatePengurus = SignatoryMandate::firstOrCreate(
            ['organization_id' => $org->id, 'decree_number' => '[SIMULASI] SK-P4I/MANDAT/2026/002'],
            [
                'user_id' => $pengurus->id,
                'letter_type_id' => null,
                'unit_id' => null,
                'can_review' => true,
                'can_approve' => true,
                'can_sign' => false, // Tanda tangan dimatikan di Sprint M2
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
                'status' => 'ACTIVE',
                'is_simulation' => true,
                'notes' => 'Mandat simulasi persetujuan substansi naskah surat dinas oleh Pengurus.',
            ]
        );

        // Mandat kadaluarsa (Expired) untuk pengujian
        SignatoryMandate::firstOrCreate(
            ['organization_id' => $org->id, 'decree_number' => '[SIMULASI] SK-P4I/MANDAT/2025/EXPIRED'],
            [
                'user_id' => $reviewer->id,
                'letter_type_id' => $typeTugas->id,
                'unit_id' => null,
                'can_review' => true,
                'can_approve' => true,
                'can_sign' => false,
                'valid_from' => '2025-01-01',
                'valid_to' => '2025-12-31',
                'status' => 'EXPIRED',
                'is_simulation' => true,
                'notes' => 'Mandat kadaluarsa tahun lalu.',
            ]
        );

        // 9. Draf Surat Contoh (Sample Letters Across Workflow States)
        // A. Surat 1: DRAFT
        if (! Letter::where('subject', 'Surat Keterangan Magang Ahmad Fauzi')->exists()) {
            $letter1 = Letter::create([
                'organization_id' => $org->id,
                'unit_id' => $sekretariat->id,
                'letter_type_id' => $typeMagang->id,
                'template_id' => $templateMagang->id,
                'author_id' => $staf->id,
                'subject' => 'Surat Keterangan Magang Ahmad Fauzi',
                'recipient_name' => 'Ahmad Fauzi (Universitas Indonesia)',
                'classification' => 'INTERNAL',
                'planned_date' => '2026-11-15',
                'status' => 'DRAFT',
                'current_revision_no' => 1,
                'official_number' => null,
            ]);

            $content1 = [
                'variables' => [
                    'nama_mahasiswa' => 'Ahmad Fauzi',
                    'universitas' => 'Universitas Indonesia',
                    'periode' => '1 Agustus 2026 – 31 Oktober 2026',
                    'keterangan' => 'Sangat Memuaskan (A)',
                ],
                'rendered_body' => '<p>Yang bertanda tangan di bawah ini menerangkan bahwa Ahmad Fauzi telah menyelesaikan magang.</p>',
            ];

            LetterRevision::create([
                'letter_id' => $letter1->id,
                'version' => 1,
                'template_id' => $templateMagang->id,
                'subject' => $letter1->subject,
                'recipient_name' => $letter1->recipient_name,
                'classification' => 'INTERNAL',
                'planned_date' => $letter1->planned_date,
                'content' => $content1,
                'content_sha256' => hash('sha256', json_encode($content1)),
                'change_summary' => 'Draf awal surat keterangan magang',
                'created_by' => $staf->id,
            ]);

            LetterStatusHistory::create([
                'letter_id' => $letter1->id,
                'from_status' => 'INIT',
                'to_status' => 'DRAFT',
                'actor_id' => $staf->id,
                'reason' => 'Pembuatan draf awal surat.',
            ]);
        }

        // B. Surat 2: SUBMITTED (Menunggu telaah di antrean reviewer)
        if (! Letter::where('subject', 'Surat Keterangan Magang Siti Nurhaliza')->exists()) {
            $letter2 = Letter::create([
                'organization_id' => $org->id,
                'unit_id' => $sekretariat->id,
                'letter_type_id' => $typeMagang->id,
                'template_id' => $templateMagang->id,
                'author_id' => $staf->id,
                'subject' => 'Surat Keterangan Magang Siti Nurhaliza',
                'recipient_name' => 'Siti Nurhaliza (Institut Teknologi Bandung)',
                'classification' => 'INTERNAL',
                'planned_date' => '2026-11-20',
                'status' => 'SUBMITTED',
                'current_revision_no' => 1,
                'official_number' => null,
            ]);

            $content2 = [
                'variables' => [
                    'nama_mahasiswa' => 'Siti Nurhaliza',
                    'universitas' => 'Institut Teknologi Bandung',
                    'periode' => '1 September 2026 – 30 November 2026',
                    'keterangan' => 'Sangat Memuaskan (A)',
                ],
                'rendered_body' => '<p>Menerangkan bahwa Siti Nurhaliza telah menyelesaikan magang riset dengan predikat Sangat Memuaskan.</p>',
            ];

            $rev2 = LetterRevision::create([
                'letter_id' => $letter2->id,
                'version' => 1,
                'template_id' => $templateMagang->id,
                'subject' => $letter2->subject,
                'recipient_name' => $letter2->recipient_name,
                'classification' => 'INTERNAL',
                'planned_date' => $letter2->planned_date,
                'content' => $content2,
                'content_sha256' => hash('sha256', json_encode($content2)),
                'change_summary' => 'Inisialisasi draf keterangan magang',
                'created_by' => $staf->id,
            ]);

            LetterStatusHistory::create([
                'letter_id' => $letter2->id,
                'from_status' => 'DRAFT',
                'to_status' => 'SUBMITTED',
                'actor_id' => $staf->id,
                'reason' => 'Diajukan untuk pemeriksaan reviewer.',
            ]);

            ApprovalRequest::create([
                'organization_id' => $org->id,
                'letter_id' => $letter2->id,
                'revision_id' => $rev2->id,
                'revision_version' => 1,
                'content_sha256' => $rev2->content_sha256,
                'submitted_by' => $staf->id,
                'submitted_at' => now()->subHours(2),
                'status' => 'PENDING',
                'submission_notes' => 'Mohon ditelaah kelayakan surat keterangan.',
                'workflow_version' => 1,
            ]);
        }

        // C. Surat 3: APPROVED (Telah disetujui, official_number tetap NULL)
        if (! Letter::where('subject', 'Surat Keterangan Magang Budi Santoso')->exists()) {
            $letter3 = Letter::create([
                'organization_id' => $org->id,
                'unit_id' => $sekretariat->id,
                'letter_type_id' => $typeMagang->id,
                'template_id' => $templateMagang->id,
                'author_id' => $staf->id,
                'subject' => 'Surat Keterangan Magang Budi Santoso',
                'recipient_name' => 'Budi Santoso (Universitas Gadjah Mada)',
                'classification' => 'INTERNAL',
                'planned_date' => '2026-11-10',
                'status' => 'APPROVED',
                'current_revision_no' => 1,
                'official_number' => null, // SAFETY: NULL
            ]);

            $content3 = [
                'variables' => [
                    'nama_mahasiswa' => 'Budi Santoso',
                    'universitas' => 'Universitas Gadjah Mada',
                    'periode' => '1 Juli 2026 – 30 September 2026',
                    'keterangan' => 'Pujian (Cum Laude)',
                ],
                'rendered_body' => '<p>Menerangkan bahwa Budi Santoso telah menyelesaikan riset kebijakan publik.</p>',
            ];

            $rev3 = LetterRevision::create([
                'letter_id' => $letter3->id,
                'version' => 1,
                'template_id' => $templateMagang->id,
                'subject' => $letter3->subject,
                'recipient_name' => $letter3->recipient_name,
                'classification' => 'INTERNAL',
                'planned_date' => $letter3->planned_date,
                'content' => $content3,
                'content_sha256' => hash('sha256', json_encode($content3)),
                'change_summary' => 'Naskah final keterangan magang Budi Santoso',
                'created_by' => $staf->id,
            ]);

            $req3 = ApprovalRequest::create([
                'organization_id' => $org->id,
                'letter_id' => $letter3->id,
                'revision_id' => $rev3->id,
                'revision_version' => 1,
                'content_sha256' => $rev3->content_sha256,
                'submitted_by' => $staf->id,
                'submitted_at' => now()->subDays(1),
                'status' => 'APPROVED',
                'workflow_version' => 1,
            ]);

            ApprovalStep::create([
                'approval_request_id' => $req3->id,
                'letter_id' => $letter3->id,
                'revision_id' => $rev3->id,
                'step_no' => 1,
                'workflow_version' => 1,
                'reviewer_id' => $pengurus->id,
                'mandate_id' => $mandatePengurus->id,
                'decision' => 'APPROVED',
                'decision_content_sha256' => $rev3->content_sha256,
                'comments' => 'Substansi surat disetujui sesuai laporan magang.',
                'decided_at' => now()->subHours(12),
            ]);

            LetterStatusHistory::create([
                'letter_id' => $letter3->id,
                'from_status' => 'IN_REVIEW',
                'to_status' => 'APPROVED',
                'actor_id' => $pengurus->id,
                'reason' => 'Substansi surat disetujui sesuai laporan magang.',
            ]);
        }

        // =========================================================================
        // 9. SPRINT M3A SEEDER: POLICIES, LEGACY REGISTER STAGING, & INCOMING AGENDA
        // =========================================================================

        // 9.1 Kebijakan Penomoran Simulasi (NR-01, NR-02, NR-03, NR-04)
        $policySK = NumberingPolicy::firstOrCreate(
            ['organization_id' => $org->id, 'series_key' => 'SK_PENGURUS'],
            [
                'code' => 'P4I-SK',
                'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
                'reset_period' => 'YEAR',
                'padding' => 3,
                'is_active' => false,
                'status' => 'DRAFT',
                'scope_type' => 'SERIES',
                'description' => 'Draf format nomor Surat Keputusan Pengurus Yayasan P4I',
                'legacy_reconciled' => false,
            ]
        );

        $policyTugas = NumberingPolicy::firstOrCreate(
            ['organization_id' => $org->id, 'series_key' => 'SURAT_TUGAS'],
            [
                'code' => 'P4I-ST',
                'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
                'reset_period' => 'YEAR',
                'padding' => 3,
                'is_active' => false,
                'status' => 'DRAFT',
                'scope_type' => 'SERIES',
                'description' => 'Draf format nomor Surat Tugas Pelaksanaan Riset / Advokasi',
                'legacy_reconciled' => false,
            ]
        );

        $policyKet = NumberingPolicy::firstOrCreate(
            ['organization_id' => $org->id, 'series_key' => 'SURAT_KETERANGAN'],
            [
                'code' => 'P4I-KET',
                'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
                'reset_period' => 'YEAR',
                'padding' => 3,
                'is_active' => false,
                'status' => 'DRAFT',
                'scope_type' => 'SERIES',
                'description' => 'Draf format nomor Surat Keterangan Magang / Rekomendasi',
                'legacy_reconciled' => false,
            ]
        );

        // 9.2 Staging Register Historis dengan Anomali/Konflik Nyata (NR-05, NR-06, NR-07)
        $batch = LegacyRegisterBatch::firstOrCreate(
            [
                'organization_id' => $org->id,
                'source_filename' => 'rekap_register_persuratan_p4i_2023_2025.csv',
            ],
            [
                'id' => '01a19361-b54a-7140-8b01-9a7e6b2c3d4e',
                'source_hash' => hash('sha256', 'rekap_register_persuratan_p4i_2023_2025.csv-seed-p4i'),
                'total_rows' => 5,
                'valid_rows' => 3,
                'conflict_rows' => 2,
                'status' => 'HAS_CONFLICTS',
                'is_sample' => true,
                'imported_by' => $staf->id,
                'notes' => "BATCH REGISTER SAMPEL PENGEMBANGAN TEKNIS (BUKAN REKONSILIASI ARSIP FISIK ASLI P4I).\nRekap Buku Register Fisik Sekretariat Yayasan P4I Periode 2023-2025.\nDitemukan 2 anomali: 1 nomor duplikat dan 1 nomor kosong pada buku manual.",
            ]
        );

        if ($batch->rows()->count() === 0) {
            $row1 = LegacyRegisterRow::create([
                'batch_id' => $batch->id,
                'row_index' => 1,
                'raw_number' => '001/P4I/I/2024',
                'normalized_number' => '001/P4I/I/2024',
                'document_date' => '2024-01-10',
                'letter_type_code' => 'SURAT_KELUAR',
                'recipient' => 'Dinas Pendidikan Provinsi Jawa Barat',
                'subject' => 'Permohonan Kerjasama Riset Analisis Kebijakan Daerah',
                'source_location' => 'Buku Register Fisik No. 04/2024 Hal. 12',
                'provenance_year' => 2024,
                'provenance_series' => 'SURAT_KELUAR',
                'is_valid' => true,
                'verification_status' => 'VERIFIED',
                'reconciliation_action' => 'RETAIN_AS_IS',
                'reconciliation_notes' => 'Fisik naskah terarsip utuh dan sah sesuai verifikasi panitia arsip.',
                'board_resolution_ref' => 'BA-REKONSILIASI-01/P4I/2026',
            ]);

            ReconciliationAuditLog::create([
                'row_id' => $row1->id,
                'user_id' => $admin->id,
                'previous_status' => 'UNVERIFIED',
                'new_status' => 'VERIFIED',
                'action_taken' => 'RETAIN_AS_IS',
                'notes' => 'Fisik naskah terarsip utuh dan sah sesuai verifikasi panitia arsip.',
            ]);

            LegacyRegisterRow::create([
                'batch_id' => $batch->id,
                'row_index' => 2,
                'raw_number' => '002/P4I/I/2024',
                'normalized_number' => '002/P4I/I/2024',
                'document_date' => '2024-01-15',
                'letter_type_code' => 'SURAT_TUGAS',
                'recipient' => 'Dr. Hendra Wijaya',
                'subject' => 'Surat Tugas Pendampingan Evaluasi Pembangunan Daerah',
                'source_location' => 'Buku Register Fisik No. 04/2024 Hal. 14',
                'provenance_year' => 2024,
                'provenance_series' => 'SURAT_TUGAS',
                'is_valid' => true,
                'verification_status' => 'UNVERIFIED',
            ]);

            $row3 = LegacyRegisterRow::create([
                'batch_id' => $batch->id,
                'row_index' => 3,
                'raw_number' => '002/P4I/I/2024',
                'normalized_number' => '002/P4I/I/2024',
                'document_date' => '2024-01-18',
                'letter_type_code' => 'SURAT_KELUAR',
                'recipient' => 'Universitas Padjadjaran',
                'subject' => 'Undangan FGD Diseminasi Riset Kebijakan',
                'source_location' => 'Buku Register Fisik No. 04/2024 Hal. 15',
                'provenance_year' => 2024,
                'provenance_series' => 'SURAT_KELUAR',
                'is_valid' => false,
                'conflict_type' => 'DUPLICATE_NUMBER',
                'conflict_details' => "Nomor '002/P4I/I/2024' terduplikasi dalam batch impor yang sama (baris 2).",
                'verification_status' => 'CONFLICT',
                'reconciliation_action' => 'FLAGGED_FOR_BOARD',
                'reconciliation_notes' => 'Duplikasi nomor register fisik 002/P4I/I/2024 dilaporkan ke Dewan Pengurus untuk penetapan surat resmi.',
                'board_resolution_ref' => 'MEMO-PENGURUS-2026-03',
            ]);

            ReconciliationAuditLog::create([
                'row_id' => $row3->id,
                'user_id' => $admin->id,
                'previous_status' => 'UNVERIFIED',
                'new_status' => 'CONFLICT',
                'action_taken' => 'FLAGGED_FOR_BOARD',
                'notes' => 'Duplikasi nomor register fisik 002/P4I/I/2024 dilaporkan ke Dewan Pengurus untuk penetapan surat resmi.',
            ]);

            LegacyRegisterRow::create([
                'batch_id' => $batch->id,
                'row_index' => 4,
                'raw_number' => '004/P4I/II/2024',
                'normalized_number' => '004/P4I/II/2024',
                'document_date' => '2024-02-01',
                'letter_type_code' => 'SURAT_KEPUTUSAN',
                'recipient' => 'Internal Yayasan P4I',
                'subject' => 'SK Pengangkatan Peneliti Madya Bidang Tata Kelola',
                'source_location' => 'Buku Register Fisik No. 04/2024 Hal. 18',
                'provenance_year' => 2024,
                'provenance_series' => 'SURAT_KEPUTUSAN',
                'is_valid' => true,
                'verification_status' => 'UNVERIFIED',
            ]);

            $row5 = LegacyRegisterRow::create([
                'batch_id' => $batch->id,
                'row_index' => 5,
                'raw_number' => '',
                'normalized_number' => null,
                'document_date' => '2024-02-10',
                'letter_type_code' => 'SURAT_KELUAR',
                'recipient' => 'Bappeda Kota Bandung',
                'subject' => 'Penyampaian Policy Brief Pembangunan Berkelanjutan',
                'source_location' => 'Buku Register Fisik No. 04/2024 Hal. 21',
                'provenance_year' => 2024,
                'provenance_series' => 'SURAT_KELUAR',
                'is_valid' => false,
                'conflict_type' => 'EMPTY_NUMBER',
                'conflict_details' => 'Nomor surat pada baris fisik ini kosong atau tidak terisi pada register.',
                'verification_status' => 'CONFLICT',
                'reconciliation_action' => 'FLAGGED_FOR_BOARD',
                'reconciliation_notes' => 'Baris fisik kosong tanpa nomor; diajukan ketetapan VOID atau penetapan nomor susulan oleh Dewan Pembina.',
            ]);

            ReconciliationAuditLog::create([
                'row_id' => $row5->id,
                'user_id' => $admin->id,
                'previous_status' => 'UNVERIFIED',
                'new_status' => 'CONFLICT',
                'action_taken' => 'FLAGGED_FOR_BOARD',
                'notes' => 'Baris fisik kosong tanpa nomor; diajukan ketetapan VOID atau penetapan nomor susulan oleh Dewan Pembina.',
            ]);
        }

        // 9.3 Agenda Policies DRAFT (BR-10)
        AgendaPolicy::firstOrCreate(
            ['direction' => 'OUTGOING'],
            [
                'organization_id' => $org->id,
                'code_prefix' => 'AG-OUT',
                'format_pattern' => '{PREFIX}/{YEAR}/{SEQ:4}',
                'reset_period' => 'YEAR',
                'status' => 'DRAFT',
                'is_active' => false,
                'approved_decree_ref' => null,
                'description' => 'Format Buku Agenda Surat Keluar (Menunggu Pengesahan Dewan Pengurus Yayasan)',
            ]
        );

        AgendaPolicy::firstOrCreate(
            ['direction' => 'INCOMING'],
            [
                'organization_id' => $org->id,
                'code_prefix' => 'AG-IN',
                'format_pattern' => '{PREFIX}/{YEAR}/{SEQ:4}',
                'reset_period' => 'YEAR',
                'status' => 'DRAFT',
                'is_active' => false,
                'approved_decree_ref' => null,
                'description' => 'Format Buku Agenda Surat Masuk (Menunggu Pengesahan Dewan Pengurus Yayasan)',
            ]
        );

        // 9.3 Buku Agenda Surat Masuk dengan Nomor Terpisah (NR-12)
        IncomingLetter::firstOrCreate(
            ['internal_agenda_number' => 'AG-IN-2026-0001'],
            [
                'organization_id' => $org->id,
                'sender_name' => 'Kementerian Perencanaan Pembangunan Nasional / Bappenas',
                'sender_letter_number' => 'B-142/D.1/PPN/10/2026',
                'letter_date' => '2026-10-25',
                'received_date' => '2026-10-28',
                'subject' => 'Undangan Rapat Koordinasi Rencana Aksi Nasional Riset Kebijakan 2027',
                'classification' => 'INTERNAL',
                'recipient_unit_id' => $sekretariat->id,
                'recorded_by' => $staf->id,
                'status' => 'REGISTERED',
                'notes' => 'Diterima via kurir dinas bersama dokumen TOR rapat.',
            ]
        );

        IncomingLetter::firstOrCreate(
            ['internal_agenda_number' => 'AG-IN-2026-0002'],
            [
                'organization_id' => $org->id,
                'sender_name' => 'Badan Riset dan Inovasi Nasional (BRIN)',
                'sender_letter_number' => '1089/BRIN/II.3/2026',
                'letter_date' => '2026-10-27',
                'received_date' => '2026-10-29',
                'subject' => 'Pemberitahuan Kelolosan Hibah Penelitian Terapan Multidisiplin Tahun Anggaran 2027',
                'classification' => 'INTERNAL',
                'recipient_unit_id' => $divisiRiset->id,
                'recorded_by' => $staf->id,
                'status' => 'REGISTERED',
                'notes' => 'Tembusan telah diteruskan ke Direktur Riset.',
            ]
        );

        IncomingLetter::firstOrCreate(
            ['internal_agenda_number' => 'AG-IN-2026-0003'],
            [
                'organization_id' => $org->id,
                'sender_name' => 'Pemerintah Provinsi DKI Jakarta',
                'sender_letter_number' => '821/DKI/BAPEDA/X/2026',
                'letter_date' => '2026-10-30',
                'received_date' => '2026-10-31',
                'subject' => 'Permohonan Narasumber Ahli Evaluasi Dampak Sosial Pembangunan Perkotaan',
                'classification' => 'INTERNAL',
                'recipient_unit_id' => $divisiRiset->id,
                'recorded_by' => $staf->id,
                'status' => 'REGISTERED',
                'notes' => 'Acara dijadwalkan tanggal 12 November 2026 di Balai Kota.',
            ]
        );
    }
}
