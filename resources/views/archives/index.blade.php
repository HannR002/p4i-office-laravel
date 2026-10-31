@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Arsip Digital Privat (Dokumen Naskah Dinas)</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Penyimpanan privat terisolasi di luar public web root dengan integritas checksum SHA-256 dan pembatasan multi-tenant.
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if(Auth::user()->isSystemAdmin() || Auth::user()->hasRole('PENGURUS_YAYASAN'))
                <a href="{{ route('archives.admin') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <span>🛡️</span>
                    <span>Panel Pengawasan & Integritas</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Storage Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Total Berkas Arsip</div>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format($stats['total_files']) }}</div>
            <div class="text-[11px] text-emerald-600 mt-0.5 font-medium">{{ $stats['ready_files'] }} Siap Diunduh</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Total Ukuran Penyimpanan</div>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ $stats['total_size_mb'] }} <span class="text-xs font-normal text-slate-500">MB</span></div>
            <div class="text-[11px] text-slate-400 mt-0.5">Disk Privat Terenkripsi</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Draf PDF (Watermark)</div>
            <div class="text-xl font-bold text-rose-700 mt-1">{{ number_format($stats['pdf_count']) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Format Standar Baca</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-slate-400 text-xs font-semibold">Draf DOCX (Terstruktur)</div>
            <div class="text-xl font-bold text-sky-700 mt-1">{{ number_format($stats['docx_count']) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Format Olah Kata Terbuka</div>
        </div>
    </div>

    <!-- Filter and Search Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('archives.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 text-xs">
            <div>
                <label class="block text-slate-600 font-semibold mb-1">Cari Perihal / SHA256 / ID</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ketik kata kunci..." class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs">
            </div>
            <div>
                <label class="block text-slate-600 font-semibold mb-1">Format Berkas</label>
                <select name="format" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs">
                    <option value="">Semua Format</option>
                    <option value="PDF" {{ request('format') === 'PDF' ? 'selected' : '' }}>PDF</option>
                    <option value="DOCX" {{ request('format') === 'DOCX' ? 'selected' : '' }}>DOCX</option>
                </select>
            </div>
            <div>
                <label class="block text-slate-600 font-semibold mb-1">Status Berkas</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs">
                    <option value="">Semua Status</option>
                    <option value="READY" {{ request('status') === 'READY' ? 'selected' : '' }}>READY</option>
                    <option value="PENDING" {{ request('status') === 'PENDING' ? 'selected' : '' }}>PENDING</option>
                    <option value="ERROR" {{ request('status') === 'ERROR' ? 'selected' : '' }}>ERROR</option>
                </select>
            </div>
            <div>
                <label class="block text-slate-600 font-semibold mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg text-xs transition-colors">
                    Filter
                </button>
                <a href="{{ route('archives.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-xs transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Archive Files Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-[11px]">
                        <th class="py-3 px-4 font-semibold">Dokumen / Surat</th>
                        <th class="py-3 px-4 font-semibold">Format</th>
                        <th class="py-3 px-4 font-semibold">Revisi</th>
                        <th class="py-3 px-4 font-semibold">Checksum SHA-256</th>
                        <th class="py-3 px-4 font-semibold">Ukuran</th>
                        <th class="py-3 px-4 font-semibold">Diekspor Oleh</th>
                        <th class="py-3 px-4 font-semibold">Status</th>
                        <th class="py-3 px-4 font-semibold">Unduhan</th>
                        <th class="py-3 px-4 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($exports as $exp)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4">
                                <a href="{{ route('letters.show', $exp->letter_id) }}" class="font-bold text-slate-900 hover:text-indigo-600 block line-clamp-1">
                                    {{ $exp->letter->subject ?? 'Dokumen Surat' }}
                                </a>
                                <div class="text-[11px] text-slate-500">
                                    Penerima: {{ $exp->letter->recipient_name ?? '-' }}
                                    @if($exp->letter->classification === 'CONFIDENTIAL' || $exp->letter->classification === 'RAHASIA')
                                        <span class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-100 text-rose-800">RAHASIA</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                @if($exp->format === 'PDF')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">PDF</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-200">DOCX</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800">
                                v{{ $exp->revision_version }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[10px] text-slate-600 truncate max-w-[140px]" title="{{ $exp->file_hash }}">
                                {{ $exp->file_hash ? substr($exp->file_hash, 0, 12).'...'.substr($exp->file_hash, -4) : '-' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $exp->file_size_formatted }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <div>{{ $exp->exporter->display_name ?? 'Sistem' }}</div>
                                <div class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($exp->created_at)->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @if($exp->isReady())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">READY</span>
                                @elseif($exp->isError())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200" title="{{ $exp->error_message }}">ERROR</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 animate-pulse">PENDING</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <span class="font-bold text-slate-800">{{ $exp->download_count }}x</span>
                                @if($exp->last_downloaded_at)
                                    <div class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($exp->last_downloaded_at)->format('d/m H:i') }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-1">
                                @if($exp->isReady())
                                    <a href="{{ route('archives.download', $exp) }}" class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold rounded text-[11px] border border-indigo-200 transition-colors">
                                        <span>⬇️</span>
                                        <span>Unduh</span>
                                    </a>
                                @endif
                                <a href="{{ route('letters.show', $exp->letter_id) }}" class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-semibold rounded text-[11px] border border-slate-200 transition-colors">
                                    <span>👁️</span>
                                    <span>Surat</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-slate-400">
                                <span class="text-3xl block mb-2">📂</span>
                                Tidak ada berkas arsip yang sesuai dengan kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($exports->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $exports->links() }}
            </div>
        @endif
    </div>

    <!-- Security & Policy Banner -->
    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-slate-600 text-xs flex items-start gap-3">
        <span class="text-xl">🛡️</span>
        <div class="space-y-1">
            <div class="font-bold text-slate-800">Tata Kelola & Keamanan Arsip Digital P4I Office:</div>
            <p class="leading-relaxed">
                Seluruh berkas arsip disimpan pada disk privat <code class="bg-slate-200 px-1 py-0.5 rounded text-slate-800 font-mono">private_archives</code> yang berada di luar direktori publik web. Akses unduh diautentikasi dengan verifikasi integritas hash SHA-256 secara langsung untuk menjamin dokumen belum pernah dimanipulasi atau diubah secara liar.
            </p>
        </div>
    </div>
</div>
@endsection
