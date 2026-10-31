<?php

namespace Tests\Feature;

use App\Models\IncomingLetter;
use App\Models\Letter;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AgendaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgendaBookTest extends TestCase
{
    protected Organization $orgA;

    protected Organization $orgB;

    protected User $staffA;

    protected User $staffB;

    protected LetterType $letterTypeA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->orgA = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Utama',
            'acronym' => 'P4I',
        ]);

        $this->orgB = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan Asing Non-P4I',
            'acronym' => 'ASING',
        ]);

        $staffRole = Role::create(['id' => (string) Str::uuid(), 'role_key' => 'STAFF', 'label' => 'Staf']);

        $this->staffA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'email' => 'staf_a_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Staf Organisasi A',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->staffA->roles()->attach($staffRole->id);

        $this->staffB = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'email' => 'staf_b_'.Str::random(5).'@asing.or.id',
            'display_name' => 'Staf Organisasi B',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->staffB->roles()->attach($staffRole->id);

        $this->letterTypeA = LetterType::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'code' => 'TGS_'.Str::random(3),
            'label' => 'Surat Tugas A',
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
        DB::table('numbering_simulations')->truncate();
        DB::table('numbering_simulation_counters')->truncate();
        DB::table('legacy_register_rows')->truncate();
        DB::table('legacy_register_batches')->truncate();
        DB::table('incoming_letters')->truncate();
        DB::table('numbering_policies')->truncate();
        DB::table('audit_events')->truncate();
        DB::table('approval_steps')->truncate();
        DB::table('approval_requests')->truncate();
        DB::table('signatory_mandates')->truncate();
        DB::table('letter_status_history')->truncate();
        DB::table('letter_revisions')->truncate();
        DB::table('letters')->truncate();
        DB::table('letter_templates')->truncate();
        DB::table('letter_types')->truncate();
        DB::table('user_roles')->truncate();
        DB::table('users')->truncate();
        DB::table('roles')->truncate();
        DB::table('org_units')->truncate();
        DB::table('organizations')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * NR-11: Buku Agenda Surat Keluar memperlihatkan filter jenis, status, dan pencarian subjek.
     */
    public function test_nr_11_outgoing_agenda_book_filtering_and_search(): void
    {
        Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->letterTypeA->id,
            'author_id' => $this->staffA->id,
            'subject' => 'Surat Riset Kecerdasan Artifisial',
            'recipient_name' => 'Institut Teknologi Bandung',
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-05',
            'status' => 'APPROVED',
            'official_number' => null,
        ]);

        Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->letterTypeA->id,
            'author_id' => $this->staffA->id,
            'subject' => 'Undangan Workshop Tata Kelola',
            'recipient_name' => 'Universitas Indonesia',
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-10',
            'status' => 'DRAFT',
            'official_number' => null,
        ]);

        $service = app(AgendaService::class);

        // Filter status APPROVED
        $resApproved = $service->getOutgoingAgenda($this->orgA, ['status' => 'APPROVED']);
        $this->assertCount(1, $resApproved);
        $this->assertEquals('Surat Riset Kecerdasan Artifisial', $resApproved->first()->subject);

        // Filter search keyword
        $resSearch = $service->getOutgoingAgenda($this->orgA, ['search' => 'Workshop']);
        $this->assertCount(1, $resSearch);
        $this->assertEquals('Undangan Workshop Tata Kelola', $resSearch->first()->subject);

        // Akses via HTTP view
        $response = $this->actingAs($this->staffA)->get(route('agenda.outgoing', ['status' => 'APPROVED']));
        $response->assertOk();
        $response->assertSee('Surat Riset Kecerdasan Artifisial');
        $response->assertSee('[NULL - NON-RESMI]');
    }

    /**
     * NR-12: Nomor surat luar pengirim dan nomor agenda masuk P4I dibedakan dengan tegas.
     */
    public function test_nr_12_incoming_letter_separates_sender_number_and_internal_agenda(): void
    {
        $response = $this->actingAs($this->staffA)->post(route('agenda.incoming.store'), [
            'sender_name' => 'Kementerian Komunikasi dan Informatika',
            'sender_letter_number' => 'B-998/KOMINFO/DJAI/11/2026',
            'letter_date' => '2026-11-01',
            'received_date' => '2026-11-02',
            'subject' => 'Undangan Rapat Koordinasi AI Nasional',
            'classification' => 'INTERNAL',
            'notes' => 'Diterima via pos tercatat',
        ]);

        $response->assertRedirect(route('agenda.incoming'));

        $incoming = IncomingLetter::where('organization_id', $this->orgA->id)->latest()->first();
        $this->assertNotNull($incoming);

        // Bukti pemisahan:
        $this->assertEquals('B-998/KOMINFO/DJAI/11/2026', $incoming->sender_letter_number);
        $this->assertStringStartsWith('AG-IN-2026-', $incoming->internal_agenda_number);
        $this->assertNotEquals($incoming->sender_letter_number, $incoming->internal_agenda_number);

        // Verifikasi audit event (NR-13)
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $this->orgA->id,
            'event_type' => 'INCOMING_LETTER_REGISTERED',
            'entity_id' => $incoming->id,
        ]);
    }

    /**
     * NR-14: Operasi dilindungi RBAC dan kepemilikan organisasi (multi-tenant isolation).
     */
    public function test_nr_14_rbac_and_multi_tenant_isolation(): void
    {
        // 1. Staf biasa tidak boleh membuat numbering policy (Hanya Admin / Pengurus)
        $responseStaff = $this->actingAs($this->staffA)->post(route('numbering.policies.store'), [
            'series_key' => 'UNAUTHORIZED_SERIES',
            'code' => 'UNAUTH',
            'format_pattern' => '{seq}/{code}',
            'reset_period' => 'YEAR',
            'padding' => 3,
        ]);
        $responseStaff->assertForbidden();

        // 2. Organisasi B tidak dapat melihat agenda surat masuk Organisasi A
        IncomingLetter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'sender_name' => 'Kemendikbud',
            'sender_letter_number' => '4455/A/2026',
            'internal_agenda_number' => 'AG-IN-2026-0099',
            'letter_date' => '2026-11-01',
            'received_date' => '2026-11-01',
            'subject' => 'Surat Rahasia Org A',
            'classification' => 'INTERNAL',
            'recorded_by' => $this->staffA->id,
            'status' => 'REGISTERED',
        ]);

        $responseOrgB = $this->actingAs($this->staffB)->get(route('agenda.incoming'));
        $responseOrgB->assertOk();
        $responseOrgB->assertDontSee('Surat Rahasia Org A');
        $responseOrgB->assertDontSee('AG-IN-2026-0099');
    }
}
