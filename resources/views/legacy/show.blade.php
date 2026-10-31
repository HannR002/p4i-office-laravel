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
                    STATUS BATCH: {{ $batch->status }}
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

    <!-- Catatan & Panel Evaluasi Verifikasi Reviewer (BR-07, BR-08) -->
    @if(Auth::user()->isSystemAdmin() || Auth::user()->hasRole('REVIEWER') || Auth::user()->hasRole('PENGURUS_YAYASAN'))
    <div class="bg-indigo-50/70 p-6 rounded-2xl border border-indigo-200 shadow-sm">
        <h3 class="text-sm font-bold text-indigo-950 mb-1">⚖️ Panel Evaluasi & Keputusan Verifikasi Batch (BR-08)</h3>
        <p class="text-xs text-indigo-800 mb-4">
            Batch yang memuat baris berkonflik (<strong class="font-bold text-rose-700">HAS_CONFLICTS</strong>) wajib diselesaikan per baris melalui rekonsiliasi arsip sebelum dapat disahkan menjadi baseline.
        </p>

        @if($batch->notes)
        <div class="bg-white p-3 rounded-lg border border-indigo-200 text-xs text-slate-700 mb-4 whitespace-pre-line">
            <strong>Catatan Audit Arsip:</strong><br>{{ $batch->notes }}
        </div>
        @endif

        <form action="{{ route('legacy.verify', $batch->id) }}" method="POST" class="flex flex-col sm:flex-row gap-3 items-end">
            @csrf
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-indigo-900 mb-1">Catatan Evaluasi / Berita Acara Reviewer *</label>
                <input type="text" name="notes" required placeholder="Masukkan nomor berita acara rekonsiliasi atau catatan telaah arsip..." class="w-full text-xs rounded-lg border border-indigo-300 p-2.5 focus:ring-2 focus:ring-indigo-500 bg-white">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" name="decision" value="APPROVE" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-sm whitespace-nowrap">
                    ✓ Verifikasi Seluruh Batch (VERIFIED)
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
            Baris Konflik / Anomali ({{ $batch->conflict_rows }})
        </a>
    </div>

    <!-- Tabel Baris Register & Rekonsiliasi (BR-06, BR-07, BR-09) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5 w-12 text-center">No</th>
                        <th class="p-3.5">Nomor Asli (Raw) & Lokasi</th>
                        <th class="p-3.5">Tanggal Surat</th>
                        <th class="p-3.5">Jenis / Seri</th>
                        <th class="p-3.5">Penerima & Perihal</th>
                        <th class="p-3.5">Status Verifikasi (BR-07)</th>
                        <th class="p-3.5">Tindakan Rekonsiliasi & Catatan</th>
                        @if(Auth::user()->isSystemAdmin() || Auth::user()->hasRole('REVIEWER') || Auth::user()->hasRole('PENGURUS_YAYASAN'))
                        <th class="p-3.5 text-right">Aksi Arsiparis</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($rows as $row)
                    <tr class="hover:bg-slate-50/80 transition-colors {{ $row->verification_status === 'CONFLICT' ? 'bg-rose-50/40' : '' }}">
                        <td class="p-3.5 text-center text-slate-400 font-mono">{{ $row->row_index }}</td>
                        <td class="p-3.5">
                            <div class="font-mono font-bold {{ $row->is_valid ? 'text-slate-900' : 'text-rose-700' }}">
                                {{ $row->raw_number ?: '<NOMOR KOSONG>' }}
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                📍 {{ $row->source_location ?? 'Buku Register Fisik' }}
                            </div>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            {{ $row->document_date ? $row->document_date->format('d/m/Y') : '-' }}
                            @if($row->provenance_year)
                                <div class="text-[10px] text-slate-400 font-mono">Thn: {{ $row->provenance_year }}</div>
                            @endif
                        </td>
                        <td class="p-3.5 font-mono text-[11px]">
                            <div>{{ $row->letter_type_code ?? '-' }}</div>
                            @if($row->provenance_series)
                                <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-600">Seri: {{ $row->provenance_series }}</span>
                            @endif
                        </td>
                        <td class="p-3.5 max-w-xs">
                            <div class="font-medium text-slate-800 truncate" title="{{ $row->recipient }}">{{ $row->recipient ?? '-' }}</div>
                            <div class="text-[11px] text-slate-500 truncate" title="{{ $row->subject }}">{{ $row->subject ?? '-' }}</div>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            @if($row->verification_status === 'VERIFIED')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    VERIFIED
                                </span>
                            @elseif($row->verification_status === 'CONFLICT')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                    CONFLICT
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    UNVERIFIED
                                </span>
                            @endif
                        </td>
                        <td class="p-3.5 text-xs">
                            @if($row->conflict_details)
                                <div class="text-rose-700 font-medium mb-1">{{ $row->conflict_details }}</div>
                            @endif
                            @if($row->reconciliation_action)
                                <div class="text-[11px] text-slate-600">
                                    Aksi: <code class="font-bold text-indigo-700">{{ $row->reconciliation_action }}</code>
                                </div>
                            @endif
                            @if($row->reconciliation_notes)
                                <div class="text-[10px] text-slate-500 italic mt-0.5">{{ $row->reconciliation_notes }}</div>
                            @endif
                        </td>

                        @if(Auth::user()->isSystemAdmin() || Auth::user()->hasRole('REVIEWER') || Auth::user()->hasRole('PENGURUS_YAYASAN'))
                        <td class="p-3.5 text-right whitespace-nowrap">
                            <!-- Tombol Rekonsiliasi Modal / Form Mini -->
                            <details class="relative inline-block text-left">
                                <summary class="cursor-pointer text-xs font-bold text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                                    Rekonsiliasi &darr;
                                </summary>
                                <div class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-lg border border-slate-200 p-4 z-20 text-left">
                                    <form action="{{ route('legacy.rows.reconcile', $row->id) }}" method="POST" class="space-y-3">
                                        @csrf
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-700 mb-1">Status Verifikasi *</label>
                                            <select name="status" class="w-full text-xs rounded border border-slate-300 p-1.5 focus:ring-1 focus:ring-indigo-500">
                                                <option value="VERIFIED" {{ $row->verification_status === 'VERIFIED' ? 'selected' : '' }}>VERIFIED (Disahkan)</option>
                                                <option value="CONFLICT" {{ $row->verification_status === 'CONFLICT' ? 'selected' : '' }}>CONFLICT (Berkonflik)</option>
                                                <option value="UNVERIFIED" {{ $row->verification_status === 'UNVERIFIED' ? 'selected' : '' }}>UNVERIFIED (Belum Diuji)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-700 mb-1">Tindakan Rekonsiliasi *</label>
                                            <select name="reconciliation_action" class="w-full text-xs rounded border border-slate-300 p-1.5 focus:ring-1 focus:ring-indigo-500">
                                                <option value="RETAIN_AS_IS">Pertahankan Nomor Asli (RETAIN)</option>
                                                <option value="AMEND_OFFICIAL_RECORD">Perbaikan Berita Acara (AMEND)</option>
                                                <option value="VOID_ENTRY">Batalkan Naskah (VOID)</option>
                                                <option value="FLAGGED_FOR_BOARD">Laporkan ke Rapat Pengurus (FLAG)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-700 mb-1">Catatan Telaah Arsiparis *</label>
                                            <textarea name="notes" rows="2" required placeholder="Dasar fisik/arsip keputusan..." class="w-full text-xs rounded border border-slate-300 p-1.5 focus:ring-1 focus:ring-indigo-500">{{ $row->reconciliation_notes }}</textarea>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-700 mb-1">Ref SK / Berita Acara</label>
                                            <input type="text" name="board_resolution_ref" value="{{ $row->board_resolution_ref }}" placeholder="No. BA/SK Pengurus..." class="w-full text-xs rounded border border-slate-300 p-1.5 focus:ring-1 focus:ring-indigo-500">
                                        </div>
                                        <button type="submit" class="w-full bg-indigo-700 text-white text-xs font-bold py-1.5 rounded hover:bg-indigo-800 transition-colors">
                                            Simpan Rekonsiliasi
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-slate-400">
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
