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
}
