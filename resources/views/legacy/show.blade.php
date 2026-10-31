@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Batch Details Header -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('legacy.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                    &larr; Kembali ke Daftar Staging
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold
                    {{ $batch->status === 'VERIFIED' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' :
                       ($batch->status === 'HAS_CONFLICTS' ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-slate-100 text-slate-700') }}">
                    STATUS: {{ $batch->status }}
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900">{{ $batch->source_filename }}</h1>
            <div class="text-xs text-slate-500 font-mono mt-1 break-all">
                SHA-256: {{ $batch->source_hash }}
            </div>
            <div class="text-xs text-slate-500 mt-1">
                Diimpor oleh: <strong class="text-slate-800">{{ $batch->importer->display_name ?? '-' }}</strong> pada {{ $batch->created_at->format('d/m/Y H:i') }}
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-200 min-w-[90px]">
                <div class="text-xs text-slate-500">Total Baris</div>
                <div class="text-base font-bold text-slate-900">{{ $batch->total_rows }}</div>
            </div>
            <div class="text-center p-3 rounded-xl bg-emerald-50 border border-emerald-200 min-w-[90px]">
                <div class="text-xs text-emerald-700">Valid</div>
                <div class="text-base font-bold text-emerald-800">{{ $batch->valid_rows }}</div>
            </div>
            <div class="text-center p-3 rounded-xl bg-rose-50 border border-rose-200 min-w-[90px]">
                <div class="text-xs text-rose-700">Konflik</div>
                <div class="text-base font-bold text-rose-800">{{ $batch->conflict_rows }}</div>
            </div>
        </div>
    </div>

    <!-- Catatan & Panel Verifikasi Reviewer (NR-07) -->
    @if(Auth::user()->isSystemAdmin() || Auth::user()->hasRole('REVIEWER') || Auth::user()->hasRole('PENGURUS_YAYASAN'))
    <div class="bg-indigo-50/70 p-6 rounded-2xl border border-indigo-200 shadow-sm">
        <h3 class="text-sm font-bold text-indigo-950 mb-1">⚖️ Panel Evaluasi & Verifikasi Register Historis (NR-07)</h3>
        <p class="text-xs text-indigo-800 mb-4">
            Hanya berkas register yang telah diverifikasi (<strong class="font-bold">VERIFIED</strong>) secara sah dan bersih dari anomali yang dapat dipertimbangkan oleh Pengurus Yayasan sebagai rujukan rekonsiliasi.
        </p>

        @if($batch->notes)
        <div class="bg-white p-3 rounded-lg border border-indigo-200 text-xs text-slate-700 mb-4 whitespace-pre-line">
            <strong>Catatan Audit:</strong><br>{{ $batch->notes }}
        </div>
        @endif

        <form action="{{ route('legacy.verify', $batch->id) }}" method="POST" class="flex flex-col sm:flex-row gap-3 items-end">
            @csrf
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-indigo-900 mb-1">Catatan Verifikasi / Putusan Reviewer *</label>
                <input type="text" name="notes" required placeholder="Masukkan dasar evaluasi verifikasi atau temuan audit..." class="w-full text-xs rounded-lg border border-indigo-300 p-2.5 focus:ring-2 focus:ring-indigo-500 bg-white">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" name="decision" value="APPROVE" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-sm whitespace-nowrap">
                    ✓ Verifikasi Batch (VERIFIED)
                </button>
                <button type="submit" name="decision" value="REJECT" class="bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-sm whitespace-nowrap">
                    ✕ Tolak Batch (REJECTED)
                </button>
            </div>
        </form>
    </div>
    @endif

    <!-- Filter Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
        <a href="{{ route('legacy.show', [$batch->id, 'filter' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $filter === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            Semua Baris ({{ $batch->total_rows }})
        </a>
        <a href="{{ route('legacy.show', [$batch->id, 'filter' => 'valid']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $filter === 'valid' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            Baris Valid ({{ $batch->valid_rows }})
        </a>
        <a href="{{ route('legacy.show', [$batch->id, 'filter' => 'conflict']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $filter === 'conflict' ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            Baris Anomali / Konflik ({{ $batch->conflict_rows }})
        </a>
    </div>

    <!-- Tabel Baris Register -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5 w-12 text-center">No</th>
                        <th class="p-3.5">Nomor Asli (Raw)</th>
                        <th class="p-3.5">Nomor Normalisasi</th>
                        <th class="p-3.5">Tanggal Surat</th>
                        <th class="p-3.5">Jenis</th>
                        <th class="p-3.5">Penerima</th>
                        <th class="p-3.5">Perihal</th>
                        <th class="p-3.5">Status Validasi</th>
                        <th class="p-3.5">Detail Anomali / Konflik (NR-06)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($rows as $row)
                    <tr class="hover:bg-slate-50/80 transition-colors {{ ! $row->is_valid ? 'bg-rose-50/40' : '' }}">
                        <td class="p-3.5 text-center text-slate-400 font-mono">{{ $row->row_index }}</td>
                        <td class="p-3.5 font-mono font-bold {{ $row->is_valid ? 'text-slate-900' : 'text-rose-700' }}">
                            {{ $row->raw_number ?: '<KOSONG>' }}
                        </td>
                        <td class="p-3.5 font-mono text-slate-600">{{ $row->normalized_number ?? '-' }}</td>
                        <td class="p-3.5 whitespace-nowrap">{{ $row->document_date ? $row->document_date->format('d/m/Y') : '-' }}</td>
                        <td class="p-3.5 font-mono text-[11px]">{{ $row->letter_type_code ?? '-' }}</td>
                        <td class="p-3.5">{{ $row->recipient ?? '-' }}</td>
                        <td class="p-3.5 max-w-xs truncate" title="{{ $row->subject }}">{{ $row->subject ?? '-' }}</td>
                        <td class="p-3.5 whitespace-nowrap">
                            @if($row->is_valid)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    VALID
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                    {{ $row->conflict_type }}
                                </span>
                            @endif
                        </td>
                        <td class="p-3.5 text-xs text-rose-700 leading-tight">
                            {{ $row->conflict_details ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center text-slate-400">
                            Tidak ada baris yang sesuai dengan filter pencarian.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rows->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $rows->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
