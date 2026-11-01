<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\SignatoryMandate;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MandateValidationService
{
    /**
     * Memvalidasi apakah reviewer memiliki mandat aktif dan sah untuk melakukan aksi tertentu
     * pada surat dinas yang ditargetkan.
     *
     * @param  User  $reviewer  Pengguna yang akan menelaah / menyetujui
     * @param  Letter  $letter  Surat yang ditelaah
     * @param  string  $requiredAction  'REVIEW' atau 'APPROVE'
     * @return SignatoryMandate Mandat yang sah
     *
     * @throws AccessDeniedHttpException|ValidationException
     */
    public function validateMandate(User $reviewer, Letter $letter, string $requiredAction = 'REVIEW'): SignatoryMandate
    {
        // 1. Verifikasi status akun pengguna (harus ACTIVE)
        if ($reviewer->status !== 'ACTIVE') {
            throw new AccessDeniedHttpException('Akun pengguna tidak aktif (DISABLED) dan tidak berhak melakukan telaah surat.');
        }

        // 2. Verifikasi isolasi multi-tenant (organisasi harus sama)
        if ($reviewer->organization_id !== $letter->organization_id) {
            throw new AccessDeniedHttpException('Akses lintas organisasi ditolak. Reviewer dan surat harus berada pada yayasan yang sama.');
        }

        // 3. Pencegahan persetujuan sendiri (Self-Approval Prevention - AP2-06)
        // Pembuat surat dilarang keras menyetujui pengajuannya sendiri, bahkan jika memiliki peran SYSTEM_ADMIN
        if ($letter->author_id === $reviewer->id) {
            throw new AccessDeniedHttpException('Pembuat surat dilarang keras menelaah atau menyetujui pengajuannya sendiri (Self-approval ditolak).');
        }

        // 4. Cari mandat penandatangan/pemeriksa yang aktif dan cocok
        $today = now()->format('Y-m-d');

        $query = SignatoryMandate::where('organization_id', $letter->organization_id)
            ->where('user_id', $reviewer->id)
            ->where('status', 'ACTIVE')
            ->whereNull('revoked_at')
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_to', '>=', $today);

        // Filter berdasarkan cakupan aksi yang dibutuhkan
        if ($requiredAction === 'APPROVE') {
            $query->where('can_approve', true);
        } else {
            // Untuk review biasa, bisa memiliki can_review atau can_approve
            $query->where(function ($q) {
                $q->where('can_review', true)
                    ->orWhere('can_approve', true);
            });
        }

        // Filter cakupan jenis surat (spesifik atau berlaku umum untuk semua jenis)
        $query->where(function ($q) use ($letter) {
            $q->whereNull('letter_type_id')
                ->orWhere('letter_type_id', $letter->letter_type_id);
        });

        // Filter cakupan unit kerja jika dispesifikasikan pada mandat
        if ($letter->unit_id) {
            $query->where(function ($q) use ($letter) {
                $q->whereNull('unit_id')
                    ->orWhere('unit_id', $letter->unit_id);
            });
        }

        $mandate = $query->first();

        if (! $mandate) {
            throw ValidationException::withMessages([
                'mandate' => "Pengguna tidak memiliki mandat aktif yang sah untuk melakukan tindakan {$requiredAction} pada jenis surat ini.",
            ]);
        }

        return $mandate;
    }

    /**
     * Memeriksa apakah pengguna berhak melakukan review pada surat.
     */
    public function canReview(User $user, Letter $letter): bool
    {
        try {
            $this->validateMandate($user, $letter, 'REVIEW');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Memeriksa apakah pengguna berhak melakukan approval pada surat.
     */
    public function canApprove(User $user, Letter $letter): bool
    {
        try {
            $this->validateMandate($user, $letter, 'APPROVE');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Memvalidasi kewenangan akses terhadap naskah dinas berklasifikasi RAHASIA / CONFIDENTIAL (M4B-02, M4B-03, M4B-04).
     *
     * Akses hanya diizinkan untuk:
     * 1. Pembuat naskah dinas (Author/Konseptor).
     * 2. Pejabat yang memiliki Mandat Aktif (SignatoryMandate) yang sah dan mencakup:
     *    - Akun pengguna berstatus ACTIVE.
     *    - Berada pada organisasi yang sama (multi-tenant).
     *    - Mandat berstatus ACTIVE dan belum dicabut (revoked_at IS NULL).
     *    - Tanggal saat ini berada dalam rentang valid_from s/d valid_to.
     *    - Memiliki kewenangan review atau approve (can_review atau can_approve).
     *    - Jenis surat cocok (letter_type_id IS NULL atau sama).
     *    - Unit kerja cocok jika dispesifikasikan (mandate unit_id IS NULL atau sama).
     *
     * Penegasan Keamanan (Sprint M4B):
     * Peran teknis (REVIEWER, SYSTEM_ADMIN, PENGURUS_YAYASAN) TIDAK memberikan bypass otomatis
     * tanpa kepemilikan naskah atau mandat sah yang terbukti berlaku.
     */
    public function canAccessConfidential(User $user, Letter $letter, string $action = 'VIEW'): bool
    {
        // 1. Akun pengguna harus berstatus aktif
        if (! $user->isActive()) {
            return false;
        }

        // 2. Isolasi multi-tenant mutlak
        if ($user->organization_id !== $letter->organization_id) {
            return false;
        }

        // 3. Hak Pemilik / Pembuat Naskah (Author)
        if ($letter->author_id === $user->id) {
            return true;
        }

        // 4. Verifikasi Mandat Aktif Pejabat Pemeriksa
        $today = now()->format('Y-m-d');
        $targetUnitId = $letter->unit_id ?: $letter->author?->unit_id;

        $query = SignatoryMandate::where('organization_id', $letter->organization_id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->whereNull('revoked_at')
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_to', '>=', $today);

        // Filter kewenangan penelaah
        if (in_array(strtoupper($action), ['APPROVE', 'SIGN'], true)) {
            $query->where('can_approve', true);
        } else {
            $query->where(function ($q) {
                $q->where('can_review', true)
                    ->orWhere('can_approve', true);
            });
        }

        // Filter jenis surat
        $query->where(function ($q) use ($letter) {
            $q->whereNull('letter_type_id')
                ->orWhere('letter_type_id', $letter->letter_type_id);
        });

        // Filter unit kerja jika mandat dibatasi untuk unit tertentu
        if ($targetUnitId) {
            $query->where(function ($q) use ($targetUnitId) {
                $q->whereNull('unit_id')
                    ->orWhere('unit_id', $targetUnitId);
            });
        } else {
            $query->whereNull('unit_id');
        }

        return $query->exists();
    }

    /**
     * Menerapkan penyaringan SQL ketat untuk naskah rahasia pada kueri Letter (STG-04).
     */
    public function applyConfidentialityScope($letterQuery, User $user): void
    {
        $letterQuery->where(function ($q) use ($user) {
            $q->whereNotIn('letters.classification', ['CONFIDENTIAL', 'RAHASIA'])
                ->orWhere('letters.author_id', $user->id)
                ->orWhereExists(function ($sub) use ($user) {
                    $today = now()->format('Y-m-d');
                    $sub->selectRaw('1')
                        ->from('signatory_mandates')
                        ->where('signatory_mandates.organization_id', $user->organization_id)
                        ->where('signatory_mandates.user_id', $user->id)
                        ->where('signatory_mandates.status', 'ACTIVE')
                        ->whereNull('signatory_mandates.revoked_at')
                        ->whereDate('signatory_mandates.valid_from', '<=', $today)
                        ->whereDate('signatory_mandates.valid_to', '>=', $today)
                        ->where(function ($m) {
                            $m->where('signatory_mandates.can_review', true)
                                ->orWhere('signatory_mandates.can_approve', true);
                        })
                        ->where(function ($m) {
                            $m->whereNull('signatory_mandates.letter_type_id')
                                ->orWhereColumn('signatory_mandates.letter_type_id', 'letters.letter_type_id');
                        })
                        ->where(function ($m) {
                            $m->whereNull('signatory_mandates.unit_id')
                                ->orWhereRaw('signatory_mandates.unit_id = COALESCE(letters.unit_id, (SELECT u.unit_id FROM users u WHERE u.id = letters.author_id))');
                        });
                });
        });
    }
}
