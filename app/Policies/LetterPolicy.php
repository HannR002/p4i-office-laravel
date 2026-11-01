<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;
use App\Services\MandateValidationService;

class LetterPolicy
{
    /**
     * Menentukan apakah pengguna dapat melihat daftar surat.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Menentukan apakah pengguna dapat melihat surat tertentu.
     * Menerapkan Isolasi Multi-Tenant dan Penegakan Kerahasiaan (Confidentiality).
     */
    public function view(User $user, Letter $letter): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        // 1. Isolasi Organisasi (Multi-tenant)
        if ($user->organization_id !== $letter->organization_id) {
            return false;
        }

        // 2. Proteksi Dokumen Rahasia (CONFIDENTIAL / RAHASIA) (M4B-02, M4B-03, M4B-04)
        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            return app(MandateValidationService::class)->canAccessConfidential($user, $letter, 'VIEW');
        }

        return true;
    }

    /**
     * Menentukan apakah pengguna dapat mengekspor naskah surat (M4B-04).
     */
    public function export(User $user, Letter $letter): bool
    {
        if (! $user->isActive() || $user->organization_id !== $letter->organization_id) {
            return false;
        }

        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            return app(MandateValidationService::class)->canAccessConfidential($user, $letter, 'EXPORT');
        }

        return true;
    }

    /**
     * Menentukan apakah pengguna dapat mengunduh arsip naskah surat (M4B-04).
     */
    public function download(User $user, Letter $letter): bool
    {
        return $this->export($user, $letter);
    }

    /**
     * Menentukan apakah pengguna dapat membuat draf surat baru.
     */
    public function create(User $user): bool
    {
        return $user->isActive() && ! empty($user->organization_id);
    }

    /**
     * Menentukan apakah pengguna dapat merevisi/memperbarui draf surat.
     * Hanya diizinkan pada status DRAFT dan CHANGES_REQUESTED.
     */
    public function update(User $user, Letter $letter): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        // 1. Isolasi Organisasi
        if ($user->organization_id !== $letter->organization_id) {
            return false;
        }

        // 2. Hanya status DRAFT atau CHANGES_REQUESTED yang dapat diedit
        if (! in_array($letter->status, ['DRAFT', 'CHANGES_REQUESTED'], true)) {
            return false;
        }

        // 3. Hak kepemilikan atau hak administratif (Dokumen RAHASIA/CONFIDENTIAL wajib melewati validasi mandat)
        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            return $letter->author_id === $user->id
                || app(MandateValidationService::class)->canAccessConfidential($user, $letter, 'VIEW');
        }

        return $letter->author_id === $user->id || $user->isSystemAdmin();
    }

    /**
     * Menentukan apakah pengguna dapat mengajukan surat untuk pemeriksaan.
     */
    public function submit(User $user, Letter $letter): bool
    {
        if (! $user->isActive() || $user->organization_id !== $letter->organization_id) {
            return false;
        }

        if ($letter->status !== 'DRAFT') {
            return false;
        }

        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            return $letter->author_id === $user->id
                || app(MandateValidationService::class)->canAccessConfidential($user, $letter, 'VIEW');
        }

        return $letter->author_id === $user->id || $user->isSystemAdmin();
    }

    /**
     * Menentukan apakah pengguna dapat melihat pratinjau surat.
     */
    public function preview(User $user, Letter $letter): bool
    {
        return $this->view($user, $letter);
    }

    /**
     * Menentukan apakah pengguna dapat menghapus draf surat.
     */
    public function delete(User $user, Letter $letter): bool
    {
        if (! $user->isActive() || $user->organization_id !== $letter->organization_id) {
            return false;
        }

        return $letter->status === 'DRAFT'
            && ($letter->author_id === $user->id || $user->isSystemAdmin());
    }
}
