@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('archives.index') }}" class="p-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors text-xs">
                    ← Kembali
                </a>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Pengawasan Integritas Arsip Digital & Penyimpanan</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Panel diagnostik kearsipan untuk verifikasi integritas fisik berkas, pemantauan kapasitas, dan audit trail unduhan (Sprint M4A).
            </p>
        </div>
        <div>
            <form action="{{ route('archives.admin.verify') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors">
                    <span>🔍</span>
                    <span>Jalankan Audit Integritas SHA-256</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Integrity Audit Result Alert -->
    @if(session('integrity_result'))
        @php $res = session('integrity_result'); @endphp
        <div class="p-5 rounded-xl border {{ $res['all_intact'] ? 'bg-emerald-50 border-emerald-300 text-emerald-950' : 'bg-rose-50 border-rose-300 text-rose-950' }} shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 font-bold text-sm">
                    <span>{{ $res['all_intact'] ? '✅' : '⚠️' }}</span>
                    <span>Hasil Audit Integritas Arsip Fisik: {{ $res['all_intact'] ? 'SELURUH BERKAS VALID & UTUH' : 'TERDETEKSI ANOMALI INTEGRITAS' }}</span>
                </div>
                <div class="text-[11px] text-slate-500 font-mono">
                    Waktu Audit: {{ \Carbon\Carbon::parse($res['verified_at'])->format('d/m/Y H:i:s') }}
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div class="bg-white/80 p-3 rounded-lg border border-slate-200">
                    <div class="text-slate-500 font-medium">Total Berkas Diperiksa</div>
                    <div class="text-lg font-bold text-slate-900 mt-0.5">{{ $res['total_files'] }} Berkas</div>
                </div>
                <div class="bg-white/80 p-3 rounded-lg border border-slate-200">
                    <div class="text-emerald-700 font-medium">Berkas Utuh & Cocok</div>
                    <div class="text-lg font-bold text-emerald-700 mt-0.5">{{ $res['valid_files'] }} Berkas</div>
                </div>
                <div class="bg-white/80 p-3 rounded-lg border border-slate-200">
                    <div class="text-rose-700 font-medium">Berkas Rusak (Corrupted)</div>
                    <div class="text-lg font-bold {{ $res['corrupted_files'] > 0 ? 'text-rose-700 font-black' : 'text-slate-500' }} mt-0.5">{{ $res['corrupted_files'] }} Berkas</div>
                </div>
                <div class="bg-white/80 p-3 rounded-lg border border-slate-200">
                    <div class="text-amber-700 font-medium">Berkas Hilang (Missing)</div>
                    <div class="text-lg font-bold {{ $res['missing_files'] > 0 ? 'text-amber-700 font-black' : 'text-slate-500' }} mt-0.5">{{ $res['missing_files'] }} Berkas</div>
                </div>
            </div>

            @if($res['all_intact'])
                <p class="text-xs text-emerald-800 leading-relaxed">
                    Setiap berkas dokumen pada penyimpanan privat <code class="bg-emerald-100 px-1 py-0.5 rounded text-emerald-900 font-mono">storage/app/private_archives</code> telah diverifikasi bit-per-bit terhadap hash SHA-256 yang tersimpan pada tabel basis data. Tidak ditemukan manipulasi berkas atau korupsi data.
                </p>
            @else
                <p class="text-xs text-rose-800 leading-relaxed font-semibold">
                    Peringatan: Terdapat ketidakcocokan antara berkas fisik di disk penyimpanan dan catatan basis data. Segera periksa cadangan dan log sistem.
                </p>
            @endif
        </div>
    @endif

    <!-- Storage Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Total Arsip Terdaftar</div>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format($stats['total_exports']) }}</div>
            <div class="text-[11px] text-slate-500 mt-0.5">{{ $stats['ready_exports'] }} Siap / {{ $stats['total_exports'] - $stats['ready_exports'] }} Lainnya</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Penggunaan Disk Privat</div>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ $stats['total_size_mb'] }} <span class="text-xs font-normal text-slate-500">MB</span></div>
            <div class="text-[11px] text-slate-400 mt-0.5">Kapasitas Hosting Hostinger</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Frekuensi Unduhan Teraudit</div>
            <div class="text-xl font-bold text-indigo-700 mt-1">{{ number_format($stats['total_downloads']) }}x</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Tercatat di Audit Trail</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Komposisi Format</div>
            <div class="text-xs font-bold text-slate-800 mt-2 flex items-center justify-between">
                <span class="text-rose-700">PDF: {{ $stats['pdf_count'] }}</span>
                <span class="text-sky-700">DOCX: {{ $stats['docx_count'] }}</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1.5 overflow-hidden flex">
                @php 
                    $total = max(1, $stats['pdf_count'] + $stats['docx_count']);
                    $pdfPct = round(($stats['pdf_count'] / $total) * 100);
                @endphp
                <div class="bg-rose-500 h-1.5" style="width: {{ $pdfPct }}%"></div>
                <div class="bg-sky-500 h-1.5" style="width: {{ 100 - $pdfPct }}%"></div>
            </div>
        </div>
    </div>

    <!-- Recent Audit Logs for Exports & Downloads -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <div class="font-bold text-xs text-slate-800 flex items-center gap-2">
                <span>📜</span>
                <span>Log Audit Aktivitas Kearsipan Terbaru (M4A-15)</span>
            </div>
            <span class="text-[11px] text-slate-500">
                20 Catatan Terakhir (Immutabel)
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500 text-[11px] bg-slate-50/50">
                        <th class="py-2.5 px-4 font-semibold">Waktu Event</th>
                        <th class="py-2.5 px-4 font-semibold">Tipe Kejadian</th>
                        <th class="py-2.5 px-4 font-semibold">Pelaksana</th>
                        <th class="py-2.5 px-4 font-semibold">Target Naskah / Ekspor</th>
                        <th class="py-2.5 px-4 font-semibold">Payload & Checksum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($auditLogs as $log)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-4 text-slate-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($log->occurred_at)->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="py-2.5 px-4">
                                @if($log->event_type === 'DOCUMENT_EXPORTED')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">DOCUMENT_EXPORTED</span>
                                @elseif($log->event_type === 'DOCUMENT_DOWNLOADED')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">DOCUMENT_DOWNLOADED</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">{{ $log->event_type }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-4 text-slate-800 font-medium">
                                {{ $log->actor->display_name ?? 'Sistem' }}
                            </td>
                            <td class="py-2.5 px-4 text-slate-600 font-mono text-[11px]">
                                {{ $log->event_metadata['letter_id'] ?? $log->entity_id }}
                            </td>
                            <td class="py-2.5 px-4 text-slate-500 font-mono text-[10px]">
                                @if(isset($log->event_metadata['sha256']))
                                    SHA: {{ substr($log->event_metadata['sha256'], 0, 16) }}... ({{ $log->event_metadata['format'] ?? '' }} v{{ $log->event_metadata['revision_version'] ?? '' }})
                                @else
                                    {{ json_encode($log->event_metadata) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-6 text-slate-400">
                                Belum ada aktivitas ekspor atau unduh yang tercatat dalam log audit.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
