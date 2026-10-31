@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-indigo-100 text-indigo-800 border border-indigo-300">
                    TABEL STAGING TERISOLASI (NR-05)
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                    STATUS: UNVERIFIED BASELINE (NR-08)
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 mt-2">Staging Impor Register Historis Persuratan</h1>
            <p class="text-xs text-slate-500 mt-1">
                Penerimaan berkas rekapitulasi persuratan lama Yayasan P4I ke lingkungan staging terisolasi. Data ini diaudit terhadap anomali duplikasi, format cacat, dan tanggal sebelum rekonsiliasi.
            </p>
        </div>

        <div>
            <a href="{{ route('legacy.create') }}" class="inline-flex items-center gap-2 bg-indigo-700 text-white text-xs font-bold px-4 py-2.5 rounded-lg hover:bg-indigo-800 transition-colors shadow-sm">
                <span>📤</span>
                <span>Unggah Berkas Register (CSV)</span>
            </a>
        </div>
    </div>

    <!-- Alert Keamanan NR-08 -->
    <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl text-xs text-amber-900 leading-relaxed">
        <strong>PERINGATAN ARSITEKTUR (NR-08):</strong> Data register historis yang berada pada tabel staging tidak dapat dan dilarang digunakan sebagai baseline penomoran resmi sebelum seluruh konflik/anomali diselesaikan dan disahkan oleh Tim Arsiparis / SK Pengurus Yayasan P4I.
    </div>

    <!-- Tabel Daftar Batch Impor -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Riwayat Batch Unggahan Register Staging</h2>
            <span class="text-xs text-slate-500">Total: {{ $batches->total() }} Batch</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Tanggal Impor</th>
                        <th class="p-3.5">Nama Berkas Sumber</th>
                        <th class="p-3.5">Checksum SHA-256 (NR-07)</th>
                        <th class="p-3.5">Total Baris</th>
                        <th class="p-3.5">Baris Valid</th>
                        <th class="p-3.5">Anomali / Konflik</th>
                        <th class="p-3.5">Status Audit</th>
                        <th class="p-3.5">Pengunggah</th>
                        <th class="p-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($batches as $batch)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3.5 whitespace-nowrap text-slate-500">
                            {{ $batch->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="p-3.5 font-bold text-slate-900">
                            {{ $batch->source_filename }}
                        </td>
                        <td class="p-3.5 font-mono text-[11px] text-slate-500" title="{{ $batch->source_hash }}">
                            {{ substr($batch->source_hash, 0, 12) }}...{{ substr($batch->source_hash, -8) }}
                        </td>
                        <td class="p-3.5 font-bold">{{ $batch->total_rows }}</td>
                        <td class="p-3.5 text-emerald-700 font-semibold">{{ $batch->valid_rows }}</td>
                        <td class="p-3.5">
                            @if($batch->conflict_rows > 0)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                    {{ $batch->conflict_rows }} Konflik
                                </span>
                            @else
                                <span class="text-slate-400">0</span>
                            @endif
                        </td>
                        <td class="p-3.5">
                            @if($batch->status === 'VERIFIED')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    DIVERIFIKASI
                                </span>
                            @elseif($batch->status === 'HAS_CONFLICTS')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    ADA ANOMALI
                                </span>
                            @elseif($batch->status === 'REJECTED')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                    DITOLAK
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    STAGING
                                </span>
                            @endif
                        </td>
                        <td class="p-3.5 text-slate-600">
                            {{ $batch->importer->display_name ?? '-' }}
                        </td>
                        <td class="p-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('legacy.show', $batch->id) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                                Inspeksi Baris & Anomali &rarr;
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center text-slate-400">
                            Belum ada berkas register historis yang diimpor ke staging.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($batches->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $batches->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
