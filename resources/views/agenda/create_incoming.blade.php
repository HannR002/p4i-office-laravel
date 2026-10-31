@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                REGISTRASI SURAT MASUK (NR-12)
            </span>
        </div>
        <h1 class="text-xl font-bold text-slate-900">Pencatatan Surat Masuk Baru</h1>
        <p class="text-xs text-slate-500 mt-1">
            Input metadata surat masuk dari instansi eksternal. Sistem akan mengalokasikan Nomor Agenda Internal P4I secara otomatis dan berurutan.
        </p>

        <form action="{{ route('agenda.incoming.store') }}" method="POST" class="mt-6 space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Instansi / Nama Pengirim Eksternal *</label>
                    <input type="text" name="sender_name" required placeholder="Contoh: Kementerian Pendidikan dan Kebudayaan" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Asli Surat Pengirim *</label>
                    <input type="text" name="sender_letter_number" required placeholder="Contoh: 1234/B/TU/2026" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 font-mono focus:ring-2 focus:ring-emerald-500">
                    <p class="text-[11px] text-slate-400 mt-1">Nomor resmi yang tercetak pada berkas pengirim.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Surat *</label>
                    <input type="date" name="letter_date" required value="{{ now()->format('Y-m-d') }}" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Diterima *</label>
                    <input type="date" name="received_date" required value="{{ now()->format('Y-m-d') }}" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Klasifikasi Surat *</label>
                    <select name="classification" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-emerald-500">
                        <option value="INTERNAL">INTERNAL (Biasa)</option>
                        <option value="CONFIDENTIAL">CONFIDENTIAL (Rahasia)</option>
                        <option value="PUBLIC">PUBLIC (Terbuka)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Unit Kerja Tujuan (Disposisi)</label>
                    <select name="recipient_unit_id" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-emerald-500">
                        <option value="">-- Pilih Unit Kerja (Opsional) --</option>
                        @foreach($orgUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Perihal Surat *</label>
                <textarea name="subject" rows="2" required placeholder="Uraikan perihal atau ringkasan isi surat masuk..." class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Tambahan / Keterangan Berkas Fisik</label>
                <textarea name="notes" rows="2" placeholder="Contoh: Diterima dalam amplop tertutup bersama 2 bundel lampiran..." class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="flex items-center justify-between pt-3">
                <a href="{{ route('agenda.incoming') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                    &larr; Batal & Kembali ke Agenda
                </a>

                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-5 py-2.5 rounded-lg transition-colors shadow-sm">
                    Simpan & Terbitkan Nomor Agenda Internal
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
