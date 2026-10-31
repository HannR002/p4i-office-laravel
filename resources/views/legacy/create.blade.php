@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-indigo-100 text-indigo-800 border border-indigo-300">
                STAGING IMPORT (NR-05, NR-17)
            </span>
        </div>
        <h1 class="text-xl font-bold text-slate-900">Unggah Berkas Register Historis (CSV)</h1>
        <p class="text-xs text-slate-500 mt-1">
            Impor berkas rekapitulasi surat lama ke dalam tabel staging terisolasi. Seluruh baris akan diverifikasi terhadap duplikasi nomor, format tak standar, dan konsistensi tanggal.
        </p>

        <form action="{{ route('legacy.store') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Berkas CSV / Text (*.csv, *.txt) *</label>
                <input type="file" name="register_file" required accept=".csv,.txt" class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500">
                <p class="text-[11px] text-slate-400 mt-1.5">Maksimum ukuran berkas: 5 MB. Format kolom tajuk (header): <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-700 font-mono">nomor_surat,tanggal,jenis,penerima,perihal</code></p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Sumber Berkas & Konteks Arsip</label>
                <textarea name="notes" rows="3" placeholder="Contoh: Rekapitulasi Buku Register Fisik Sekretariat Yayasan P4I Periode 2023-2025..." class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs text-slate-600">
                <strong class="text-slate-900">Struktur Contoh Berkas CSV:</strong>
                <pre class="mt-2 text-[11px] font-mono bg-white p-3 rounded-lg border border-slate-200 overflow-x-auto text-slate-700">nomor_surat,tanggal,jenis,penerima,perihal
001/P4I/I/2024,2024-01-15,SURAT_KELUAR,Dinas Pendidikan,Permohonan Kerjasama Riset
002/P4I/I/2024,2024-01-20,SURAT_TUGAS,Dr. Ir. Hendra,Surat Tugas Penelitian Lapangan
003/P4I/II/2024,2024-02-05,SURAT_KEPUTUSAN,Internal,SK Pengangkatan Staf Administrasi</pre>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('legacy.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                    &larr; Kembali ke Daftar Batch
                </a>

                <button type="submit" class="bg-indigo-700 text-white text-xs font-bold px-5 py-2.5 rounded-lg hover:bg-indigo-800 transition-colors shadow-sm">
                    Mulai Impor ke Staging
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
