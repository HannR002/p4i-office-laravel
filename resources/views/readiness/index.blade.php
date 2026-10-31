@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-rose-100 text-rose-800 border border-rose-300">
                    LAPORAN RESMI KESIAPAN PERSURATAN (NR-16)
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-slate-100 text-slate-700">
                    SPRINT M3A
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 mt-2">Evaluasi Kesiapan Penerbitan Persuratan Resmi</h1>
            <p class="text-xs text-slate-500 mt-1">
                Laporan objektif tanpa klaim semu terhadap 4 pilar kesiapan hukum, register historis, mandat pejabat, dan proteksi keamanan sistem.
            </p>
        </div>

        <div class="text-right text-xs text-slate-400">
            Dievaluasi Pada: <br>
            <strong class="text-slate-700 font-mono">{{ \Carbon\Carbon::parse($report['evaluated_at'])->format('d M Y, H:i:s T') }}</strong>
        </div>
    </div>

    <!-- Banner Utama Status Kesiapan (BLOCKED) -->
    <div class="bg-gradient-to-r from-rose-900 via-rose-800 to-red-900 text-white p-6 rounded-2xl shadow-lg border-2 border-rose-500">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-white text-xs font-bold tracking-wider uppercase backdrop-blur-sm">
                    <span>⛔ STATUS KESELURUHAN:</span>
                    <span class="text-amber-300">{{ $report['overall_status'] }} (DIBLOKIR DARI PRODUKSI)</span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-white">
                    Penerbitan Nomor Resmi Belum Diizinkan
                </h2>
                <p class="text-xs text-rose-100 max-w-2xl leading-relaxed">
                    Sistem mendeteksi bahwa prasyarat administratif legal dan rekonsiliasi arsip historis belum terpenuhi. Tidak ada nomor surat resmi yang dapat dialokasikan pada lingkungan sistem saat ini.
                </p>
            </div>

            <div class="bg-white/10 p-4 rounded-xl border border-white/20 text-center min-w-[180px] backdrop-blur-sm">
                <div class="text-[11px] text-rose-200 uppercase font-semibold">Tingkat Kesiapan</div>
                <div class="text-3xl font-black text-amber-300 my-1">25%</div>
                <div class="text-[10px] text-rose-200">1 dari 4 Pilar Terpenuhi</div>
            </div>
        </div>

        <!-- Daftar Pemicu Blokir (Blocking Reasons) -->
        @if(!empty($report['blocking_reasons']))
        <div class="mt-6 pt-5 border-t border-rose-700/60">
            <div class="text-xs font-bold text-amber-300 uppercase tracking-wide mb-2">Faktor Penentu Blokir (Prasyarat Wajib):</div>
            <ul class="space-y-1.5 text-xs text-rose-100">
                @foreach($report['blocking_reasons'] as $reason)
                <li class="flex items-start gap-2">
                    <span class="text-amber-400 font-bold">✕</span>
                    <span>{{ $reason }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <!-- 4 Pilar Evaluasi Kesiapan -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @foreach($report['pillars'] as $pillar)
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-slate-800">{{ $pillar['title'] }}</h3>
                    @if($pillar['status'] === 'VERIFIED')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            TERPENUHI (VERIFIED)
                        </span>
                    @elseif($pillar['status'] === 'SECURE_OFF')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            AMAN (SECURE OFF)
                        </span>
                    @elseif($pillar['status'] === 'SIMULATION_ONLY')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                            SIMULASI (NON-DEFINITIF)
                        </span>
                    @elseif($pillar['status'] === 'DISCREPANCIES_FOUND' || $pillar['status'] === 'IN_PROGRESS')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                            PERLU REKONSILIASI
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                            DIBLOKIR (BLOCKED)
                        </span>
                    @endif
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    {{ $pillar['details'] }}
                </p>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                <span>Kode: <code class="font-mono text-slate-600">{{ $pillar['key'] }}</code></span>
                <span>Kepatuhan: <strong class="{{ $pillar['passed'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $pillar['passed'] ? 'MEMENUHI' : 'BELUM' }}</strong></span>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Rekomendasi Administratif untuk Dewan Pengurus Yayasan P4I -->
    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">📌 Rekomendasi Tindak Lanjut Menuju Sprint M3B & Produksi</h3>
        <div class="space-y-2 text-xs text-slate-600 leading-relaxed">
            <p>1. <strong>Untuk Ketua & Dewan Pengurus Yayasan P4I:</strong> Menetapkan dan menandatangani Surat Keputusan (SK) Standar Prosedur Operasional Penomoran Surat Resmi serta SK Pengangkatan Pejabat Penelaah/Penandatangan Definitif.</p>
            <p>2. <strong>Untuk Tim Arsiparis Yayasan:</strong> Menyelesaikan verifikasi baris konflik pada tabel staging register historis agar nomor urut baseline dapat direkonsiliasi.</p>
            <p>3. <strong>Untuk Tim Auditor GPT-6:</strong> Memverifikasi bahwa tidak ada celah bypass penomoran resmi sebelum sprint penomoran definitif dibuka.</p>
        </div>
    </div>
</div>
@endsection
