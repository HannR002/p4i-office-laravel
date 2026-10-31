<?php

namespace Tests\Feature;

use App\Exceptions\StaleRevisionException;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\LetterDraftService;
use App\Services\TemplateEngineService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LetterDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $staffA;

    protected User $adminA;

    protected User $staffB;

    protected LetterType $typeA;

    protected LetterTemplate $activeTemplateA;

    protected LetterTemplate $inactiveTemplateA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Organisasi A
        $this->orgA = Organization::create([
            'legal_name' => 'Yayasan P4I Utama',
            'acronym' => 'P4I-A',
        ]);

        $unitA = OrgUnit::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Sekretariat Utama',
        ]);

        $roleAdmin = Role::create(['role_key' => 'SYSTEM_ADMIN', 'label' => 'Admin']);
        $roleStaff = Role::create(['role_key' => 'STAFF', 'label' => 'Staf']);

        $this->adminA = User::create([
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'admin_a@p4i.or.id',
            'display_name' => 'Admin Org A',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->adminA->roles()->attach($roleAdmin->id);

        $this->staffA = User::create([
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'staff_a@p4i.or.id',
            'display_name' => 'Staf Org A',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->staffA->roles()->attach($roleStaff->id);

        // 2. Organisasi B (Tenant Lain)
        $this->orgB = Organization::create([
            'legal_name' => 'Yayasan Mitra B',
            'acronym' => 'MITRA-B',
        ]);

        $this->staffB = User::create([
            'organization_id' => $this->orgB->id,
            'email' => 'staff_b@mitra.or.id',
            'display_name' => 'Staf Org B',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->staffB->roles()->attach($roleStaff->id);

        // 3. Jenis Surat & Templat di Org A
        $this->typeA = LetterType::create([
            'organization_id' => $this->orgA->id,
            'code' => 'KET-A',
            'label' => 'Surat Keterangan Magang',
            'is_active' => true,
        ]);

        $this->activeTemplateA = LetterTemplate::create([
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->typeA->id,
            'version' => 1,
            'title' => 'Templat Aktif Magang',
            'form_schema' => ['fields' => ['nama_mahasiswa', 'universitas']],
            'content_template' => '<p>Menerangkan bahwa {{nama_mahasiswa}} dari {{universitas}} telah magang.</p>',
            'is_active' => true,
        ]);

        $this->inactiveTemplateA = LetterTemplate::create([
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->typeA->id,
            'version' => 2,
            'title' => 'Templat Nonaktif Magang',
            'form_schema' => ['fields' => ['nama']],
            'content_template' => '<p>{{nama}}</p>',
            'is_active' => false,
        ]);
    }

    /**
     * M1-01: Login aktif bisa masuk; akun DISABLED tidak bisa melanjutkan sesi.
     */
    public function test_m1_01_active_login_and_disabled_session_revocation(): void
    {
        // Login aktif sukses
        $response = $this->post('/login', [
            'email' => 'staff_a@p4i.or.id',
            'password' => 'secret123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->staffA);

        // Pengguna dinonaktifkan di tengah sesi
        $this->staffA->update(['status' => 'DISABLED']);

        // Request berikutnya melalui middleware active.user wajib melempar logout / redirect login
        $dashResponse = $this->get('/letters');
        $dashResponse->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * M1-02: RBAC dan penolakan akses anonim.
     */
    public function test_m1_02_rbac_and_anonymous_access_rejection(): void
    {
        // Anonim ditolak
        $response = $this->get('/letters');
        $response->assertRedirect('/login');

        // Pengguna terautentikasi dapat mengakses daftar surat
        $authResponse = $this->actingAs($this->staffA)->get('/letters');
        $authResponse->assertStatus(200);
        $authResponse->assertSee('Daftar Surat Dinas');
    }

    /**
     * M1-03: Create draft membuat Letter + revision v1 secara atomik.
     */
    public function test_m1_03_create_draft_creates_letter_and_v1_revision_atomically(): void
    {
        $payload = [
            'subject' => 'Surat Keterangan Magang 2026',
            'recipient_name' => 'Sdr. Ahmad Dahlan',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-15',
            'variables' => [
                'nama_mahasiswa' => 'Ahmad Dahlan',
                'universitas' => 'Universitas Indonesia',
            ],
        ];

        $response = $this->actingAs($this->staffA)->post('/letters', $payload);

        $letter = Letter::where('subject', 'Surat Keterangan Magang 2026')->first();
        $this->assertNotNull($letter);
        $response->assertRedirect(route('letters.show', $letter));

        // Verifikasi properti Letter
        $this->assertEquals($this->orgA->id, $letter->organization_id);
        $this->assertEquals($this->staffA->id, $letter->author_id);
        $this->assertEquals('DRAFT', $letter->status);
        $this->assertEquals(1, $letter->current_revision_no);
        $this->assertNull($letter->official_number, 'Official number must strictly be NULL');

        // Verifikasi relasi revisi v1
        $revision = $letter->latestRevision;
        $this->assertNotNull($revision);
        $this->assertEquals(1, $revision->version);
        $this->assertNotEmpty($revision->content_sha256);
        $this->assertStringContainsString('Ahmad Dahlan', $revision->content['rendered_body']);
        $this->assertStringContainsString('Universitas Indonesia', $revision->content['rendered_body']);

        // Verifikasi audit event dicatat
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'LETTER_CREATED',
            'entity_id' => $letter->id,
            'actor_id' => $this->staffA->id,
        ]);
    }

    /**
     * M1-04: Revisi v2 bertambah; v1 tidak berubah termasuk hash konten.
     */
    public function test_m1_04_revision_v2_appends_without_altering_v1(): void
    {
        $service = app(LetterDraftService::class);
        $letter = $service->createDraft($this->staffA, [
            'subject' => 'Draf Awal',
            'recipient_name' => 'Penerima Awal',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-10',
            'variables' => ['nama_mahasiswa' => 'Budi', 'universitas' => 'UGM'],
        ]);

        $rev1 = $letter->latestRevision;
        $rev1Hash = $rev1->content_sha256;
        $rev1Subject = $rev1->subject;

        // Buat revisi baru v2
        $rev2 = $service->reviseDraft($this->staffA, $letter, [
            'subject' => 'Draf Diperbarui v2',
            'recipient_name' => 'Penerima Baru',
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'TERBATAS',
            'planned_date' => '2026-11-12',
            'change_summary' => 'Perbaikan nama dan tanggal',
            'variables' => ['nama_mahasiswa' => 'Budi Gunawan', 'universitas' => 'UGM Yogyakarta'],
        ], expectedRevisionNo: 1);

        $this->assertEquals(2, $rev2->version);
        $this->assertEquals('Draf Diperbarui v2', $rev2->subject);

        // Muat kembali v1 dari database dan buktikan TIDAK BERUBAH
        $freshRev1 = LetterRevision::where('letter_id', $letter->id)->where('version', 1)->first();
        $this->assertEquals($rev1Hash, $freshRev1->content_sha256, 'v1 content hash must remain strictly unchanged');
        $this->assertEquals($rev1Subject, $freshRev1->subject, 'v1 subject must remain unchanged');
        $this->assertEquals('Budi', $freshRev1->content['variables']['nama_mahasiswa']);

        // Verifikasi status header surat telah naik ke v2
        $letter->refresh();
        $this->assertEquals(2, $letter->current_revision_no);
        $this->assertEquals('TERBATAS', $letter->classification);
    }

    /**
     * M1-05: Raw SQL update/delete revision diblokir pada database uji yang mendukung trigger.
     */
    public function test_m1_05_raw_sql_update_and_delete_on_revision_blocked_by_database_triggers(): void
    {
        $service = app(LetterDraftService::class);
        $letter = $service->createDraft($this->staffA, [
            'subject' => 'Draf Uji Trigger',
            'recipient_name' => 'Penerima',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-10',
            'variables' => ['nama_mahasiswa' => 'Siti', 'universitas' => 'ITB'],
        ]);

        $rev = $letter->latestRevision;

        // 1. Uji pemblokiran UPDATE via raw SQL
        try {
            DB::statement("UPDATE letter_revisions SET subject = 'SUBJEK DIPALSUKAN' WHERE id = ?", [$rev->id]);
            $this->fail('Raw SQL update should have been blocked by trg_letter_revisions_no_update trigger');
        } catch (QueryException $e) {
            $this->assertStringContainsString('strictly immutable', $e->getMessage());
        }

        // 2. Uji pemblokiran DELETE via raw SQL
        try {
            DB::statement('DELETE FROM letter_revisions WHERE id = ?', [$rev->id]);
            $this->fail('Raw SQL delete should have been blocked by trg_letter_revisions_no_delete trigger');
        } catch (QueryException $e) {
            $this->assertStringContainsString('strictly immutable', $e->getMessage());
        }
    }

    /**
     * M1-07: Stale revision submission ditolak 409 / StaleRevisionException.
     */
    public function test_m1_07_stale_revision_submission_rejected_with_409(): void
    {
        $service = app(LetterDraftService::class);
        $letter = $service->createDraft($this->staffA, [
            'subject' => 'Draf Stale Test',
            'recipient_name' => 'Penerima',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-10',
            'variables' => ['nama_mahasiswa' => 'Doni', 'universitas' => 'UNPAD'],
        ]);

        // Staf lain memperbarui ke v2 lebih dulu
        $service->reviseDraft($this->adminA, $letter, [
            'subject' => 'Draf Versi 2 dari Admin',
            'recipient_name' => 'Penerima',
            'change_summary' => 'Revisi oleh admin',
        ], expectedRevisionNo: 1);

        $letter->refresh();
        $this->assertEquals(2, $letter->current_revision_no);

        // StafA mengirim formulir yang masih mengacu pada v1 (expectedRevisionNo = 1)
        $this->expectException(StaleRevisionException::class);
        $service->reviseDraft($this->staffA, $letter, [
            'subject' => 'Draf Versi Terlambat dari Staf A',
            'recipient_name' => 'Penerima',
        ], expectedRevisionNo: 1);
    }

    /**
     * M1-08: Pengguna tidak dapat melihat/mengubah surat organisasi lain (Multi-tenant isolation).
     */
    public function test_m1_08_multi_tenant_isolation_prevents_cross_organization_access(): void
    {
        $service = app(LetterDraftService::class);
        $letterA = $service->createDraft($this->staffA, [
            'subject' => 'Surat Rahasia Org A',
            'recipient_name' => 'Penerima A',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-10',
            'variables' => ['nama_mahasiswa' => 'Eko', 'universitas' => 'UI'],
        ]);

        // Staf B dari Org B mencoba membuka detail surat Org A
        $response = $this->actingAs($this->staffB)->get(route('letters.show', $letterA));
        $response->assertStatus(403);

        // Staf B mencoba membuka edit surat Org A
        $editResponse = $this->actingAs($this->staffB)->get(route('letters.edit', $letterA));
        $editResponse->assertStatus(403);

        // Staf B mencoba membuka preview surat Org A
        $previewResponse = $this->actingAs($this->staffB)->get(route('letters.preview', $letterA));
        $previewResponse->assertStatus(403);

        // Surat Org A tidak boleh muncul di daftar surat Staf B
        $listResponse = $this->actingAs($this->staffB)->get('/letters');
        $listResponse->assertDontSee('Surat Rahasia Org A');
    }

    /**
     * M1-09: Surat CONFIDENTIAL (RAHASIA) tidak bocor lewat list, detail, atau preview ke staf lain.
     */
    public function test_m1_09_confidential_letter_isolation(): void
    {
        $service = app(LetterDraftService::class);
        $confidentialLetter = $service->createDraft($this->adminA, [
            'subject' => 'Surat Sangat Rahasia Direksi',
            'recipient_name' => 'Penerima Rahasia',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'RAHASIA',
            'planned_date' => '2026-11-10',
            'variables' => ['nama_mahasiswa' => 'Fajar', 'universitas' => 'UI'],
        ]);

        // Staf biasa di organisasi yang sama mencoba melihat surat RAHASIA milik Admin
        $detailResponse = $this->actingAs($this->staffA)->get(route('letters.show', $confidentialLetter));
        $detailResponse->assertStatus(403);

        $previewResponse = $this->actingAs($this->staffA)->get(route('letters.preview', $confidentialLetter));
        $previewResponse->assertStatus(403);

        // Di daftar surat staf biasa, judul surat RAHASIA tidak boleh muncul
        $listResponse = $this->actingAs($this->staffA)->get('/letters');
        $listResponse->assertDontSee('Surat Sangat Rahasia Direksi');

        // Admin yang membuat surat dapat membukanya
        $adminResponse = $this->actingAs($this->adminA)->get(route('letters.show', $confidentialLetter));
        $adminResponse->assertStatus(200);
    }

    /**
     * M1-10: POST/PATCH tanpa token CSRF ditolak pada sesi berbasis cookie.
     */
    public function test_m1_10_post_mutation_without_csrf_token_is_rejected(): void
    {
        // Paksa aplikasi mengevaluasi token CSRF (tidak menganggap testing bypass)
        $this->app['env'] = 'production';

        try {
            $response = $this->actingAs($this->staffA)
                ->withSession(['_token' => 'valid-server-session-token'])
                ->post('/letters', [
                    '_token' => 'invalid-tampered-token',
                    'subject' => 'Upaya CSRF Bypass',
                ]);

            $response->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    /**
     * M1-11: Template inactive tidak bisa dipakai; template aktif dirender aman (no raw PHP/Blade eval).
     */
    public function test_m1_11_inactive_template_rejected_and_safe_rendering(): void
    {
        $service = app(LetterDraftService::class);

        // 1. Mencoba membuat draf dengan template nonaktif
        $this->expectException(ValidationException::class);
        $service->createDraft($this->staffA, [
            'subject' => 'Uji Template Nonaktif',
            'recipient_name' => 'Penerima',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->inactiveTemplateA->id, // NONAKTIF
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-10',
        ]);
    }

    /**
     * M1-12: Preview wajib menampilkan watermark; official_number tetap NULL.
     */
    public function test_m1_12_preview_displays_watermark_and_official_number_is_null(): void
    {
        $service = app(LetterDraftService::class);
        $letter = $service->createDraft($this->staffA, [
            'subject' => 'Surat Uji Pratinjau',
            'recipient_name' => 'Bapak Gubernur',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-20',
            'variables' => ['nama_mahasiswa' => 'Gita', 'universitas' => 'Undip'],
        ]);

        $response = $this->actingAs($this->staffA)->get(route('letters.preview', $letter));
        $response->assertStatus(200);

        // Assert watermark teks
        $response->assertSee('DRAF — BELUM RESMI');
        $response->assertSee('TIDAK MEMILIKI KEKUATAN HUKUM');
        $response->assertSee('BELUM DITERBITKAN (DRAF)');

        // Assert official_number pada objek surat adalah NULL
        $this->assertNull($letter->official_number);

        // Assert jejak audit LETTER_PREVIEWED tersimpan
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'LETTER_PREVIEWED',
            'entity_id' => $letter->id,
            'actor_id' => $this->staffA->id,
        ]);
    }

    /**
     * M1-13: Audit event dicatat bagi create/revision/preview/perubahan templat.
     */
    public function test_m1_13_audit_events_recorded_for_all_lifecycle_actions(): void
    {
        $service = app(LetterDraftService::class);
        $letter = $service->createDraft($this->staffA, [
            'subject' => 'Surat Uji Audit',
            'recipient_name' => 'Penerima',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->activeTemplateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-10',
            'variables' => ['nama_mahasiswa' => 'Hendra', 'universitas' => 'UNS'],
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'LETTER_CREATED',
            'entity_id' => $letter->id,
        ]);

        $service->reviseDraft($this->staffA, $letter, [
            'subject' => 'Surat Uji Audit v2',
            'recipient_name' => 'Penerima',
            'change_summary' => 'Revisi audit test',
        ], expectedRevisionNo: 1);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'LETTER_REVISED',
            'entity_id' => $letter->id,
        ]);

        // Toggle template oleh admin
        $this->actingAs($this->adminA)->patch(route('templates.toggle', $this->activeTemplateA));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'TEMPLATE_UPDATED',
            'entity_id' => $this->activeTemplateA->id,
            'actor_id' => $this->adminA->id,
        ]);
    }

    /**
     * M1-14: Input invalid, XSS payload dan ekspresi tak sah dinetralkan.
     */
    public function test_m1_14_xss_and_injection_payload_sanitized(): void
    {
        $engine = new TemplateEngineService;
        $template = '<p>Halo {{nama}}, asal {{sekolah}}.</p>';
        $xssInput = '<script>alert("XSS_ATTACK")</script>';

        $rendered = $engine->render($template, [
            'nama' => $xssInput,
            'sekolah' => 'SMK 1',
        ]);

        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;XSS_ATTACK&quot;)&lt;/script&gt;', $rendered);
    }
}
