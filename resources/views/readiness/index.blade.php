@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-rose-100 text-rose-800 border border-rose-300">
                    LAPORAN AUDIT PRE-AKTIVASI (BR-11, BR-12)
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-slate-100 text-slate-700">
                    SPRINT M3B
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 mt-2">Evaluasi Kesiapan Pra-Aktivasi Persuratan Resmi</h1>
            <p class="text-xs text-slate-500 mt-1">
                Laporan objektif tanpa klaim semu terhadap 8 pilar kesiapan hukum, register historis, mandat pejabat, prosedur pemulihan bencana, dan kepatuhan hosting.
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
                    <span class="text-amber-300 font-mono">{{ $report['overall_status'] }} (DIBLOKIR DARI PRODUKSI)</span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-white">
                    Penerbitan Nomor Resmi Belum Diizinkan
                </h2>
                <p class="text-xs text-rose-100 max-w-2xl leading-relaxed">
                    Sistem mendeteksi bahwa prasyarat administratif legal yayasan, rekonsiliasi arsip fisik lama, dan pengesahan mandat penandatangan belum disahkan secara formal oleh Pengurus Yayasan P4I. Kolom <code class="font-mono bg-rose-950 px-1 py-0.5 rounded text-amber-300">letters.official_number</code> secara permanen terkunci bernilai <strong>NULL</strong>.
                </p>
            </div>

            <div class="bg-white/10 p-5 rounded-xl border border-white/20 text-center min-w-[200px] backdrop-blur-sm">
                <div class="text-[11px] text-rose-200 uppercase font-semibold">Tingkat Kesiapan Sistem</div>
                <div class="text-4xl font-black text-amber-300 my-1">{{ $report['readiness_percentage'] }}%</div>
                <div class="text-[10px] text-rose-200">{{ $report['passed_pillars'] }} dari {{ $report['total_pillars'] }} Pilar Terpenuhi</div>
            </div>
        </div>

        <!-- Daftar Pemicu Blokir (Blocking Reasons) -->
        @if(!empty($report['blocking_reasons']))
        <div class="mt-6 pt-5 border-t border-rose-700/60">
            <div class="text-xs font-bold text-amber-300 uppercase tracking-wide mb-2">Faktor Penentu Blokir (Prasyarat Wajib Menuju Produksi):</div>
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

    <!-- 8 Pilar Evaluasi Kesiapan (Grid 2 Kolom) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @foreach($report['pillars'] as $pillar)
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xs font-bold text-slate-800">{{ $pillar['title'] }}</h3>
                    @if($pillar['status'] === 'VERIFIED')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            TERPENUHI (VERIFIED)
                        </span>
                    @elseif($pillar['status'] === 'CONFLICT')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                            BERKONFLIK (CONFLICT)
                        </span>
                    @elseif($pillar['status'] === 'IN_PROGRESS')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                            PROSES REKONSILIASI
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                            DIBLOKIR (BLOCKED)
                        </span>
                    @endif
                </div>

                <div class="text-[11px] font-mono text-indigo-700 bg-indigo-50/60 p-2 rounded-lg border border-indigo-100 mb-2">
                    Ref Dokumen: <strong class="text-slate-900">{{ $pillar['document_ref'] ?? '-' }}</strong>
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
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">📌 Lembar Keputusan Administratif Dewan Pengurus Yayasan P4I</h3>
        <div class="space-y-2 text-xs text-slate-600 leading-relaxed">
            <p>1. <strong>Pengesahan SOP Penomoran Resmi:</strong> Menandatangani Surat Keputusan (SK) Standar Operasional Prosedur Penomoran Surat Resmi sesuai draf lembar kerja pada <code class="font-mono text-indigo-700">docs/numbering-sop-decision-worksheet.md</code>.</p>
            <p>2. <strong>Penyelesaian Rekonsiliasi Arsip Historis:</strong> Menyelesaikan berita acara terhadap 2 nomor surat berkonflik pada buku register fisik lama agar counter awal dapat direkonsiliasi.</p>
            <p>3. <strong>Pengesahan Mandat Pejabat Definitif:</strong> Menerbitkan SK penetapan nama pejabat penelaah dan penandatangan yang sah untuk menggantikan akun simulasi teknis.</p>
            <p>4. <strong>Larangan Aktivasi Sepihak:</strong> Modul penomoran resmi yayasan tetap terkunci (OFF) hingga audit independen GPT-6 dan tanda tangan berita acara pengurus yayasan selesai dilaksanakan.</p>
        </div>
    </div>
</div>
@endsection
