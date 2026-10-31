@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Buat Draf Surat Baru</h1>
            <p class="text-xs text-slate-500">Inisiasi naskah surat dinas internal baru (Versi 1)</p>
        </div>
        <div>
            <a href="{{ route('letters.index') }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors">
                ← Kembali ke Daftar
            </a>
        </div>
    </div>

    <!-- Alert Keamanan -->
    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-xs flex items-center gap-2">
        <span class="text-base">⚠️</span>
        <div>
            <strong>PERHATIAN KESELAMATAN ARSIP:</strong> Seluruh naskah yang disimpan akan berstatus <strong>DRAFT</strong> dengan penomoran resmi <strong>DINONAKTIFKAN (NULL)</strong>. Setiap perubahan berikutnya akan dicatat sebagai versi revisi baru yang bersifat immutabel.
        </div>
    </div>

    <form method="POST" action="{{ route('letters.store') }}" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <!-- Jenis Surat -->
            <div>
                <label for="letter_type_id" class="block font-semibold text-slate-800 mb-1">Jenis Surat <span class="text-rose-500">*</span></label>
                <select name="letter_type_id" id="letter_type_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">-- Pilih Jenis Surat --</option>
                    @foreach($letterTypes as $type)
                        <option value="{{ $type->id }}" {{ old('letter_type_id') === $type->id ? 'selected' : '' }}>
                            {{ $type->label }} ({{ $type->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Templat Surat -->
            <div>
                <label for="template_id" class="block font-semibold text-slate-800 mb-1">Templat Naskah <span class="text-rose-500">*</span></label>
                <select name="template_id" id="template_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">-- Pilih Templat Aktif --</option>
                    @foreach($templates as $tmpl)
                        <option value="{{ $tmpl->id }}" data-type="{{ $tmpl->letter_type_id }}" {{ old('template_id') === $tmpl->id ? 'selected' : '' }}>
                            {{ $tmpl->title }} (v{{ $tmpl->version }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Perihal Surat -->
            <div class="md:col-span-2">
                <label for="subject" class="block font-semibold text-slate-800 mb-1">Perihal Surat <span class="text-rose-500">*</span></label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required placeholder="Contoh: Surat Keterangan Selesai Magang Riset 2026" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Penerima / Tujuan -->
            <div>
                <label for="recipient_name" class="block font-semibold text-slate-800 mb-1">Tujuan / Nama Penerima <span class="text-rose-500">*</span></label>
                <input type="text" name="recipient_name" id="recipient_name" value="{{ old('recipient_name') }}" required placeholder="Contoh: Sdr. Budi Santoso / Rektor Universitas X" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Tanggal Rencana Surat -->
            <div>
                <label for="planned_date" class="block font-semibold text-slate-800 mb-1">Tanggal Rencana Surat <span class="text-rose-500">*</span></label>
                <input type="date" name="planned_date" id="planned_date" value="{{ old('planned_date', date('Y-m-d')) }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Klasifikasi Keamanan -->
            <div>
                <label for="classification" class="block font-semibold text-slate-800 mb-1">Klasifikasi Dokumen <span class="text-rose-500">*</span></label>
                <select name="classification" id="classification" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="INTERNAL" {{ old('classification') === 'INTERNAL' ? 'selected' : '' }}>INTERNAL — Bebas diakses staf yayasan</option>
                    <option value="TERBATAS" {{ old('classification') === 'TERBATAS' ? 'selected' : '' }}>TERBATAS — Terbatas unit kerja terkait</option>
                    <option value="RAHASIA" {{ old('classification') === 'RAHASIA' ? 'selected' : '' }}>RAHASIA — Hanya pembuat & pimpinan yayasan</option>
                </select>
            </div>
        </div>

        <!-- Isian Variabel Templat -->
        <div class="border-t border-slate-200 pt-4 space-y-3">
            <h3 class="text-xs font-bold text-slate-800">Variabel Isian Naskah Surat</h3>
            <p class="text-[11px] text-slate-500">Lengkapi variabel dinamis berikut yang akan disisipkan ke dalam draf templat:</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label for="var_nama_mahasiswa" class="block font-semibold text-slate-700 mb-1">Nama Mahasiswa / Subjek</label>
                    <input type="text" name="variables[nama_mahasiswa]" id="var_nama_mahasiswa" value="{{ old('variables.nama_mahasiswa') }}" placeholder="Nama lengkap..." class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label for="var_universitas" class="block font-semibold text-slate-700 mb-1">Instansi / Universitas</label>
                    <input type="text" name="variables[universitas]" id="var_universitas" value="{{ old('variables.universitas') }}" placeholder="Nama universitas/instansi..." class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label for="var_periode" class="block font-semibold text-slate-700 mb-1">Periode Kegiatan</label>
                    <input type="text" name="variables[periode]" id="var_periode" value="{{ old('variables.periode') }}" placeholder="Contoh: 1 Agustus s.d. 31 Oktober 2026" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label for="var_keterangan" class="block font-semibold text-slate-700 mb-1">Keterangan / Hasil Penilaian</label>
                    <input type="text" name="variables[keterangan]" id="var_keterangan" value="{{ old('variables.keterangan') }}" placeholder="Contoh: Sangat Baik (A)" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="border-t border-slate-200 pt-4 flex items-center justify-end gap-3">
            <a href="{{ route('letters.index') }}" class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors">
                Batal
            </a>
            <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-lg bg-indigo-700 hover:bg-indigo-800 text-white shadow-sm transition-colors">
                💾 Simpan Draf (v1)
            </button>
        </div>
    </form>
</div>
@endsection
