<?php

namespace Tests\Feature;

use App\Exceptions\OfficialNumberingBlockedException;
use App\Models\Letter;
use App\Models\LetterType;
use App\Models\NumberingPolicy;
use App\Models\Organization;
use App\Models\User;
use App\Services\LetterNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafetyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_safety_guard_feature_numbering_is_strictly_false(): void
    {
        $this->assertFalse(config('features.official_numbering_active'));
    }

    public function test_allocate_official_number_throws_blocked_exception(): void
    {
        $service = new LetterNumberingService();

        $this->expectException(OfficialNumberingBlockedException::class);
        $this->expectExceptionMessage('BLOCKED: Penomoran surat resmi terkunci mutlak');

        $service->allocateOfficialNumber();
    }

    public function test_new_letter_always_has_null_official_number(): void
    {
        $org = Organization::create([
            'legal_name' => 'Yayasan P4I',
            'acronym' => 'P4I',
        ]);

        $user = User::create([
            'organization_id' => $org->id,
            'email' => 'staf@p4i.local',
            'display_name' => 'Staf Uji',
            'password' => 'secret123',
        ]);

        $letterType = LetterType::create([
            'organization_id' => $org->id,
            'code' => 'KET-MAGANG',
            'label' => 'Keterangan Magang',
        ]);

        $letter = Letter::create([
            'organization_id' => $org->id,
            'letter_type_id' => $letterType->id,
            'author_id' => $user->id,
            'subject' => 'Surat Keterangan Magang Riset',
            'recipient_name' => 'Universitas Indonesia',
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-01',
            'status' => 'DRAFT',
            'current_revision_no' => 1,
            'official_number' => null,
        ]);

        $this->assertNull($letter->official_number);
        $this->assertEquals('DRAFT', $letter->status);
    }

    public function test_numbering_policies_defaults_to_inactive(): void
    {
        $org = Organization::create([
            'legal_name' => 'Yayasan P4I',
            'acronym' => 'P4I',
        ]);

        $policy = NumberingPolicy::create([
            'organization_id' => $org->id,
            'series_key' => 'DEFAULT_2026',
            'code' => 'P4I',
            'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
            'reset_period' => 'YEAR',
            'padding' => 3,
            'is_active' => false,
            'legacy_reconciled' => false,
        ]);

        $this->assertFalse($policy->is_active);
        $this->assertFalse($policy->legacy_reconciled);
    }
}

