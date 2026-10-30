<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags: P4I Office Governance & Safety Guards
    |--------------------------------------------------------------------------
    |
    | Penomoran resmi surat dinas terkunci mutlak (false) pada Sprint M0.
    | Surat resmi tidak boleh diterbitkan dan nomor fiktif dilarang keras.
    |
    */
    'official_numbering_active' => env('FEATURE_NUMBERING_ACTIVE', false),
];
