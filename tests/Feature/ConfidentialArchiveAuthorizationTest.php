<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\SignatoryMandate;
use App\Models\User;
use App\Services\DocumentExportService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfidentialArchiveAuthorizationTest extends TestCase
{
    private Organization $orgA;

    private Organization $orgB;

    private OrgUnit $unitA1;

    private OrgUnit $unitA2;

    private User $authorA;

    private User $reviewerNoMandate;

    private User $reviewerWithMandate;

    private User $adminA;

    private LetterType $typeNota;

    private LetterType $typeSk;

    private LetterTemplate $templateA;

    private DocumentExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->exportService = app(DocumentExportService::class);

        // Organisasi A
        $this->orgA = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Pusat',
            'acronym' => 'P4I-PUSAT',
        ]);

        // Organisasi B
        $this->orgB = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Mitra Yayasan Wilayah B',
            'acronym' => 'MITRA-B',
        ]);

        $this->unitA1 = OrgUnit::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Divisi Keuangan dan Hukum',
        ]);

        $this->unitA2 = OrgUnit::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Divisi Sumber Daya Manusia',
        ]);

        $roleAdmin = Role::firstOrCreate(['role_key' => 'SYSTEM_ADMIN'], ['label' => 'Admin']);
        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf']);
        $roleReviewer = Role::firstOrCreate(['role_key' => 'REVIEWER'], ['label' => 'Reviewer']);

        // Author A (Konseptor)
        $this->authorA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $this->unitA1->id,
            'email' => 'author_conf@p4i.or.id',
            'display_name' => 'Dra. Endang (Konseptor)',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->authorA->roles()->attach($roleStaff->id);

        // Reviewer tanpa mandat
        $this->reviewerNoMandate = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $this->unitA1->id,
            'email' => 'rev_nomandate@p4i.or.id',
            'display_name' => 'Budi M.Si (Reviewer Tanpa Mandat)',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->reviewerNoMandate->roles()->attach($roleReviewer->id);

        // Reviewer dengan mandat sah
        $this->reviewerWithMandate = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $this->unitA1->id,
            'email' => 'rev_mandate@p4i.or.id',
            'display_name' => 'Prof. Dr. Hendra (Reviewer Bermandat)',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->reviewerWithMandate->roles()->attach($roleReviewer->id);

        // System Admin
        $this->adminA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $this->unitA1->id,
            'email' => 'admin_conf@p4i.or.id',
            'display_name' => 'Admin IT Pusat',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->adminA->roles()->attach($roleAdmin->id);

        // Tipe Surat
        $this->typeNota = LetterType::create([
            'organization_id' => $this->orgA->id,
            'code' => 'ND-RAHASIA',
            'label' => 'Nota Dinas Rahasia',
            'is_active' => true,
        ]);

        $this->typeSk = LetterType::create([
            'organization_id' => $this->orgA->id,
            'code' => 'SK-RAHASIA',
            'label' => 'Surat Keputusan Rahasia',
            'is_active' => true,
        ]);

        $this->templateA = LetterTemplate::create([
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->typeNota->id,
            'version' => 1,
            'title' => 'Templat Nota Rahasia',
            'form_schema' => ['fields' => ['perihal']],
            'content_template' => '<p>Nota Rahasia: {{perihal}}</p>',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $tables = [
            'document_exports',
            'audit_events',
            'signatory_mandates',
            'letter_revisions',
            'letters',
            'letter_templates',
            'letter_types',
            'user_roles',
            'users',
            'roles',
            'org_units',
            'organizations',
        ];
        foreach ($tables as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createConfidentialLetter(string $classification = 'CONFIDENTIAL', ?LetterType $type = null, ?OrgUnit $unit = null): Letter
    {
        $type = $type ?: $this->typeNota;
        $letter = Letter::create([
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $type->id,
            'author_id' => $this->authorA->id,
            'subject' => 'Investigasi Internal Keuangan dan Tata Kelola',
            'recipient_name' => 'Dewan Pembina Yayasan',
            'classification' => $classification,
            'status' => 'DRAFT',
            'planned_date' => Carbon::now()->format('Y-m-d'),
            'official_number' => null,
        ]);

        $content = [
            'body' => [
                'Dokumen ini memuat data rahasia hasil audit keuangan internal Yayasan P4I.',
                'Distribusi dokumen terbatas hanya bagi konseptor dan pejabat yang memiliki mandat penandatanganan sah.',
            ],
        ];

        LetterRevision::create([
            'letter_id' => $letter->id,
            'version' => 1,
            'created_by' => $this->authorA->id,
            'subject' => $letter->subject,
            'recipient_name' => $letter->recipient_name,
            'classification' => $letter->classification,
            'planned_date' => $letter->planned_date,
            'content' => $content,
            'content_sha256' => hash('sha256', json_encode($content)),
        ]);

        return $letter;
    }

    /**
     * M4B-02: Reviewer tanpa mandat aktif dilarang mengakses dokumen berklasifikasi CONFIDENTIAL / RAHASIA.
     */
    public function test_m4b_02_reviewer_without_mandate_denied_access(): void
    {
        $letter = $this->createConfidentialLetter('CONFIDENTIAL');

        // 1. Kebijakan LetterPolicy melarang akses view
        $this->assertFalse(Gate::forUser($this->reviewerNoMandate)->allows('view', $letter));
        $this->assertFalse(Gate::forUser($this->reviewerNoMandate)->allows('export', $letter));
        $this->assertFalse(Gate::forUser($this->reviewerNoMandate)->allows('download', $letter));

        // 2. Ekspor ditolak via DocumentExportService
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Akses ditolak: Dokumen berklasifikasi RAHASIA');
        $this->exportService->exportLetter($this->reviewerNoMandate, $letter, 'PDF');
    }

    /**
     * M4B-02: Reviewer tanpa mandat dilarang mengunduh dokumen rahasia yang diekspor konseptor.
     */
    public function test_m4b_02_reviewer_without_mandate_denied_download(): void
    {
        $letter = $this->createConfidentialLetter('RAHASIA');
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Akses ditolak: Dokumen rahasia hanya dapat diunduh');
        $this->exportService->downloadExport($this->reviewerNoMandate, $export);
    }

    /**
     * M4B-03: Negative tests - Mandat kadaluarsa (expired) ditolak.
     */
    public function test_m4b_03_expired_mandate_denied_access(): void
    {
        $letter = $this->createConfidentialLetter('CONFIDENTIAL');

        // Mandat kadaluarsa kemarin
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerWithMandate->id,
            'unit_id' => $this->unitA1->id,
            'letter_type_id' => $this->typeNota->id,
            'decree_number' => 'SK-MANDAT/P4I/2026/001',
            'status' => 'ACTIVE',
            'valid_from' => Carbon::now()->subMonths(2),
            'valid_to' => Carbon::now()->subDay(), // KADALUARSA
            'can_review' => true,
            'can_approve' => true,
        ]);

        $this->assertFalse(Gate::forUser($this->reviewerWithMandate)->allows('view', $letter));

        $this->expectException(DomainException::class);
        $this->exportService->exportLetter($this->reviewerWithMandate, $letter, 'PDF');
    }

    /**
     * M4B-03: Negative tests - Mandat dicabut (revoked) ditolak.
     */
    public function test_m4b_03_revoked_mandate_denied_access(): void
    {
        $letter = $this->createConfidentialLetter('RAHASIA');

        // Mandat dicabut
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerWithMandate->id,
            'unit_id' => $this->unitA1->id,
            'letter_type_id' => $this->typeNota->id,
            'decree_number' => 'SK-MANDAT/P4I/2026/002',
            'status' => 'REVOKED', // DICABUT
            'revoked_at' => Carbon::now()->subDay(),
            'valid_from' => Carbon::now()->subMonth(),
            'valid_to' => Carbon::now()->addMonths(6),
            'can_review' => true,
            'can_approve' => true,
        ]);

        $this->assertFalse(Gate::forUser($this->reviewerWithMandate)->allows('view', $letter));

        $this->expectException(DomainException::class);
        $this->exportService->exportLetter($this->reviewerWithMandate, $letter, 'PDF');
    }

    /**
     * M4B-03: Negative tests - Mandat organisasi berbeda ditolak.
     */
    public function test_m4b_03_wrong_organization_mandate_denied_access(): void
    {
        $letter = $this->createConfidentialLetter('CONFIDENTIAL');

        // Mandat di organisasi B
        SignatoryMandate::create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->reviewerWithMandate->id,
            'decree_number' => 'SK-MANDAT/MITRA-B/2026/001',
            'status' => 'ACTIVE',
            'valid_from' => Carbon::now()->subMonth(),
            'valid_to' => Carbon::now()->addMonths(6),
            'can_review' => true,
            'can_approve' => true,
        ]);

        $this->assertFalse(Gate::forUser($this->reviewerWithMandate)->allows('view', $letter));
    }

    /**
     * M4B-03: Negative tests - Mandat tipe surat tidak cocok ditolak.
     */
    public function test_m4b_03_wrong_letter_type_mandate_denied_access(): void
    {
        $letter = $this->createConfidentialLetter('CONFIDENTIAL', $this->typeNota);

        // Mandat hanya berlaku untuk Surat Keputusan (SK), bukan Nota Dinas
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerWithMandate->id,
            'letter_type_id' => $this->typeSk->id, // BEDA TIPE SURAT
            'decree_number' => 'SK-MANDAT/P4I/2026/003',
            'status' => 'ACTIVE',
            'valid_from' => Carbon::now()->subMonth(),
            'valid_to' => Carbon::now()->addMonths(6),
            'can_review' => true,
            'can_approve' => true,
        ]);

        $this->assertFalse(Gate::forUser($this->reviewerWithMandate)->allows('view', $letter));
    }

    /**
     * M4B-03: Negative tests - Mandat unit kerja tidak cocok ditolak.
     */
    public function test_m4b_03_wrong_unit_mandate_denied_access(): void
    {
        $letter = $this->createConfidentialLetter('CONFIDENTIAL', $this->typeNota);

        // Mandat untuk unit Divisi SDM (unitA2), sedangkan surat dibuat di Divisi Keuangan (unitA1)
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerWithMandate->id,
            'unit_id' => $this->unitA2->id, // BEDA UNIT KERJA
            'decree_number' => 'SK-MANDAT/P4I/2026/004',
            'status' => 'ACTIVE',
            'valid_from' => Carbon::now()->subMonth(),
            'valid_to' => Carbon::now()->addMonths(6),
            'can_review' => true,
            'can_approve' => true,
        ]);

        $this->assertFalse(Gate::forUser($this->reviewerWithMandate)->allows('view', $letter));
    }

    /**
     * M4B-04: Reviewer dengan mandat aktif yang sah diberikan akses penuh (view, export, download).
     */
    public function test_m4b_04_valid_active_mandate_granted_access(): void
    {
        $letter = $this->createConfidentialLetter('CONFIDENTIAL', $this->typeNota);

        // Mandat aktif dan sah untuk organisasi A dan tipe surat Nota
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerWithMandate->id,
            'unit_id' => $this->unitA1->id,
            'letter_type_id' => $this->typeNota->id,
            'decree_number' => 'SK-MANDAT/P4I/2026/005',
            'status' => 'ACTIVE',
            'valid_from' => Carbon::now()->subMonth(),
            'valid_to' => Carbon::now()->addMonths(6),
            'can_review' => true,
            'can_approve' => true,
        ]);

        // Kebijakan mengizinkan
        $this->assertTrue(Gate::forUser($this->reviewerWithMandate)->allows('view', $letter));
        $this->assertTrue(Gate::forUser($this->reviewerWithMandate)->allows('export', $letter));

        // Ekspor berhasil
        $export = $this->exportService->exportLetter($this->reviewerWithMandate, $letter, 'PDF');
        $this->assertEquals('READY', $export->status);

        // Unduh berhasil
        $response = $this->exportService->downloadExport($this->reviewerWithMandate, $export);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * M4B-04: Konseptor pembuat dokumen selalu memiliki akses penuh terhadap draf rahasianya sendiri.
     */
    public function test_m4b_04_author_always_granted_access_to_own_confidential_letter(): void
    {
        $letter = $this->createConfidentialLetter('RAHASIA');

        $this->assertTrue(Gate::forUser($this->authorA)->allows('view', $letter));
        $this->assertTrue(Gate::forUser($this->authorA)->allows('export', $letter));

        $export = $this->exportService->exportLetter($this->authorA, $letter, 'DOCX');
        $this->assertEquals('READY', $export->status);

        $response = $this->exportService->downloadExport($this->authorA, $export);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * M4B-04: Administrator teknis tanpa mandat tidak boleh membocorkan dokumen rahasia (Prinsip Need-to-Know).
     */
    public function test_m4b_04_system_admin_without_mandate_denied_confidential_document(): void
    {
        $letter = $this->createConfidentialLetter('CONFIDENTIAL');

        // Admin tanpa mandat aktif ditolak
        $this->assertFalse(Gate::forUser($this->adminA)->allows('view', $letter));

        $this->expectException(DomainException::class);
        $this->exportService->exportLetter($this->adminA, $letter, 'PDF');
    }

    /**
     * STG-04: Seluruh rute HTTP kearsipan dan daftar surat menegakkan isolasi unit kerja pada mandat dokumen rahasia.
     */
    public function test_stg_04_http_archive_routes_enforce_confidential_and_unit_isolation(): void
    {
        $letter = $this->createConfidentialLetter('RAHASIA', $this->typeNota);
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');

        // Berikan mandat untuk unitA2 (sedangkan surat milik authorA di unitA1)
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerWithMandate->id,
            'unit_id' => $this->unitA2->id,
            'letter_type_id' => $this->typeNota->id,
            'decree_number' => 'SK-MANDAT/P4I/2026/STG04',
            'status' => 'ACTIVE',
            'valid_from' => Carbon::now()->subMonth(),
            'valid_to' => Carbon::now()->addMonths(6),
            'can_review' => true,
            'can_approve' => true,
        ]);

        // 1. POST /letters/{letter}/export ditolak 403
        $resExport = $this->actingAs($this->reviewerWithMandate)
            ->post(route('letters.export', $letter), ['format' => 'PDF']);
        $resExport->assertStatus(403);

        // 2. GET /archives/{export}/download ditolak 403
        $resDownload = $this->actingAs($this->reviewerWithMandate)
            ->get(route('archives.download', $export));
        $resDownload->assertStatus(403);

        // 3. GET /archives (index) menyembunyikan dokumen rahasia beda unit kerja
        $resArchivesIndex = $this->actingAs($this->reviewerWithMandate)
            ->get(route('archives.index'));
        $resArchivesIndex->assertStatus(200);
        $resArchivesIndex->assertDontSee('Investigasi Internal Keuangan dan Tata Kelola');

        // 4. GET /letters (index) menyembunyikan surat rahasia beda unit kerja
        $resLettersIndex = $this->actingAs($this->reviewerWithMandate)
            ->get(route('letters.index'));
        $resLettersIndex->assertStatus(200);
        $resLettersIndex->assertDontSee('Investigasi Internal Keuangan dan Tata Kelola');
    }
}
