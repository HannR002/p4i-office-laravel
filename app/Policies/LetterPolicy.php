<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;

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
        if (!$user->isActive()) {
            return false;
        }

        // 1. Isolasi Organisasi (Multi-tenant)
        if ($user->organization_id !== $letter->organization_id) {
            return false;
        }

        // 2. Proteksi Dokumen Rahasia (CONFIDENTIAL / RAHASIA)
        if ($letter->classification === 'RAHASIA') {
            return $letter->author_id === $user->id
                || $user->isSystemAdmin()
                || $user->hasRole('PENGURUS_YAYASAN');
        }

        return true;
    }

    /**
     * Menentukan apakah pengguna dapat membuat draf surat baru.
     */
    public function create(User $user): bool
    {
        return $user->isActive() && !empty($user->organization_id);
    }

    /**
     * Menentukan apakah pengguna dapat merevisi/memperbarui draf surat.
     */
    public function update(User $user, Letter $letter): bool
    {
        if (!$user->isActive()) {
            return false;
        }

        // 1. Isolasi Organisasi
        if ($user->organization_id !== $letter->organization_id) {
            return false;
        }

        // 2. Hanya status DRAFT yang dapat diedit
        if ($letter->status !== 'DRAFT') {
            return false;
        }

        // 3. Hak kepemilikan atau hak administratif
        if ($letter->classification === 'RAHASIA') {
            return $letter->author_id === $user->id || $user->isSystemAdmin();
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
        if (!$user->isActive() || $user->organization_id !== $letter->organization_id) {
            return false;
        }

        return $letter->status === 'DRAFT'
            && ($letter->author_id === $user->id || $user->isSystemAdmin());
    }
}
