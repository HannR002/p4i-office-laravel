@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Revisi Draf Surat Dinas</h1>
            <p class="text-xs text-slate-500">
                Membuat versi revisi baru (v{{ $letter->current_revision_no + 1 }}) dari draf "{{ $letter->subject }}"
            </p>
        </div>
        <div>
            <a href="{{ route('letters.show', $letter) }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors">
                ← Kembali ke Detail
            </a>
        </div>
    </div>

    <!-- Peringatan Konkurensi & Immutabilitas -->
    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-xs flex items-center gap-2">
        <span class="text-base">🛡️</span>
        <div>
            <strong>PENCEGAHAN LOST-UPDATE & IMMUTABILITAS:</strong> Menyimpan formulir ini akan menghasilkan rekaman revisi baru <strong>(v{{ $letter->current_revision_no + 1 }})</strong> secara *append-only*. Versi lama (v{{ $letter->current_revision_no }}) tidak akan pernah diubah. Jika ada staf lain yang menyimpan perubahan lebih dahulu, sistem akan menolak permintaan Anda secara otomatis (409 Stale Revision).
        </div>
    </div>

    <form method="POST" action="{{ route('letters.update', $letter) }}" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
        @csrf
        @method('PUT')

        <!-- Optimistic Concurrency Control Token -->
        <input type="hidden" name="expected_revision_no" value="{{ $letter->current_revision_no }}">
        <input type="hidden" name="template_id" value="{{ $letter->template_id }}">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <!-- Perihal Surat -->
            <div class="md:col-span-2">
                <label for="subject" class="block font-semibold text-slate-800 mb-1">Perihal Surat <span class="text-rose-500">*</span></label>
                <input type="text" name="subject" id="subject" value="{{ old('subject', $letter->subject) }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Penerima / Tujuan -->
            <div>
                <label for="recipient_name" class="block font-semibold text-slate-800 mb-1">Tujuan / Nama Penerima <span class="text-rose-500">*</span></label>
                <input type="text" name="recipient_name" id="recipient_name" value="{{ old('recipient_name', $letter->recipient_name) }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Tanggal Rencana Surat -->
            <div>
                <label for="planned_date" class="block font-semibold text-slate-800 mb-1">Tanggal Rencana Surat <span class="text-rose-500">*</span></label>
                <input type="date" name="planned_date" id="planned_date" value="{{ old('planned_date', $letter->planned_date) }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Klasifikasi Keamanan -->
            <div>
                <label for="classification" class="block font-semibold text-slate-800 mb-1">Klasifikasi Dokumen <span class="text-rose-500">*</span></label>
                <select name="classification" id="classification" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="INTERNAL" {{ old('classification', $letter->classification) === 'INTERNAL' ? 'selected' : '' }}>INTERNAL — Bebas diakses staf yayasan</option>
                    <option value="TERBATAS" {{ old('classification', $letter->classification) === 'TERBATAS' ? 'selected' : '' }}>TERBATAS — Terbatas unit kerja terkait</option>
                    <option value="RAHASIA" {{ old('classification', $letter->classification) === 'RAHASIA' ? 'selected' : '' }}>RAHASIA — Hanya pembuat & pimpinan yayasan</option>
                </select>
            </div>

            <!-- Catatan Ringkasan Perubahan -->
            <div class="md:col-span-2">
                <label for="change_summary" class="block font-semibold text-slate-800 mb-1">Ringkasan Perubahan Revisi <span class="text-rose-500">*</span></label>
                <input type="text" name="change_summary" id="change_summary" value="{{ old('change_summary') }}" required placeholder="Contoh: Perbaikan ejaan nama instansi dan nilai akhir magang" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
        </div>

        <!-- Isian Variabel Naskah -->
        <div class="border-t border-slate-200 pt-4 space-y-3">
            <h3 class="text-xs font-bold text-slate-800">Isian Variabel Naskah Surat</h3>
            <p class="text-[11px] text-slate-500">Perbarui nilai variabel formulir untuk menghasilkan naskah revisi baru:</p>

            @php
                $vars = $letter->latestRevision->content['variables'] ?? [];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label for="var_nama_mahasiswa" class="block font-semibold text-slate-700 mb-1">Nama Mahasiswa / Subjek</label>
                    <input type="text" name="variables[nama_mahasiswa]" id="var_nama_mahasiswa" value="{{ old('variables.nama_mahasiswa', $vars['nama_mahasiswa'] ?? '') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label for="var_universitas" class="block font-semibold text-slate-700 mb-1">Instansi / Universitas</label>
                    <input type="text" name="variables[universitas]" id="var_universitas" value="{{ old('variables.universitas', $vars['universitas'] ?? '') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label for="var_periode" class="block font-semibold text-slate-700 mb-1">Periode Kegiatan</label>
                    <input type="text" name="variables[periode]" id="var_periode" value="{{ old('variables.periode', $vars['periode'] ?? '') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label for="var_keterangan" class="block font-semibold text-slate-700 mb-1">Keterangan / Hasil Penilaian</label>
                    <input type="text" name="variables[keterangan]" id="var_keterangan" value="{{ old('variables.keterangan', $vars['keterangan'] ?? '') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="border-t border-slate-200 pt-4 flex items-center justify-end gap-3">
            <a href="{{ route('letters.show', $letter) }}" class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors">
                Batal
            </a>
            <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-lg bg-indigo-700 hover:bg-indigo-800 text-white shadow-sm transition-colors">
                💾 Simpan Revisi Baru (v{{ $letter->current_revision_no + 1 }})
            </button>
        </div>
    </form>
</div>
@endsection
