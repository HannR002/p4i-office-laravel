<?php

namespace App\Services;

use App\Exceptions\OfficialNumberingBlockedException;

class LetterNumberingService
{
    /**
     * Mencegah penerbitan nomor surat resmi secara mutlak pada Sprint M0.
     *
     * @throws OfficialNumberingBlockedException
     */
    public function allocateOfficialNumber(): never
    {
        throw new OfficialNumberingBlockedException(
            'BLOCKED: Penomoran surat resmi terkunci mutlak pada Sprint M0! '.
            'Syarat pembukaan: SK pengesahan format nomor dan register arsip lama disahkan oleh Pengurus Yayasan P4I.'
        );
    }
}
