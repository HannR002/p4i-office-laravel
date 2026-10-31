@extends('layouts.app')

@section('content')
<div class="space-y-5">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Daftar Surat Dinas</h1>
            <p class="text-xs text-slate-500">Kelola dan telusuri draf persuratan internal Yayasan P4I</p>
        </div>
        <div>
            <a href="{{ route('letters.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                <span>➕</span>
                <span>Buat Draf Baru</span>
            </a>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('letters.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div>
                <label for="search" class="block font-semibold text-slate-700 mb-1">Pencarian</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari perihal / penerima..." class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="classification" class="block font-semibold text-slate-700 mb-1">Klasifikasi</label>
                <select name="classification" id="classification" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">Semua Klasifikasi</option>
                    <option value="INTERNAL" {{ request('classification') === 'INTERNAL' ? 'selected' : '' }}>INTERNAL</option>
                    <option value="TERBATAS" {{ request('classification') === 'TERBATAS' ? 'selected' : '' }}>TERBATAS</option>
                    <option value="RAHASIA" {{ request('classification') === 'RAHASIA' ? 'selected' : '' }}>RAHASIA (CONFIDENTIAL)</option>
                </select>
            </div>

            <div>
                <label for="letter_type_id" class="block font-semibold text-slate-700 mb-1">Jenis Surat</label>
                <select name="letter_type_id" id="letter_type_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">Semua Jenis Surat</option>
                    @foreach($letterTypes as $type)
                        <option value="{{ $type->id }}" {{ request('letter_type_id') === $type->id ? 'selected' : '' }}>
                            {{ $type->label }} ({{ $type->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-lg transition-colors text-center">
                    Terapkan Filter
                </button>
                @if(request()->hasAny(['search', 'classification', 'letter_type_id']))
                    <a href="{{ route('letters.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Letter Table Container -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($letters->isEmpty())
            <div class="py-12 px-4 text-center">
                <div class="text-3xl mb-2">📭</div>
                <h3 class="text-sm font-bold text-slate-700 mb-1">Tidak Ada Draf Surat Ditemukan</h3>
                <p class="text-xs text-slate-500 mb-4">Mulai dengan membuat draf surat dinas pertama Anda.</p>
                <a href="{{ route('letters.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-semibold rounded-lg transition-colors">
                    ➕ Buat Draf Sekarang
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-semibold">
                        <tr>
                            <th class="py-3 px-4">Perihal & Jenis</th>
                            <th class="py-3 px-4">Tujuan / Penerima</th>
                            <th class="py-3 px-4">Tanggal Rencana</th>
                            <th class="py-3 px-4">Klasifikasi</th>
                            <th class="py-3 px-4">Pembuat</th>
                            <th class="py-3 px-4">Status & Revisi</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($letters as $letter)
                            <tr class="hover:bg-slate-50/75 transition-colors">
                                <td class="py-3 px-4">
                                    <a href="{{ route('letters.show', $letter) }}" class="font-bold text-slate-900 hover:text-indigo-600 transition-colors">
                                        {{ $letter->subject }}
                                    </a>
                                    <div class="text-[11px] text-slate-500">
                                        {{ $letter->letterType->label ?? '-' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-medium">
                                    {{ $letter->recipient_name }}
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    {{ \Carbon\Carbon::parse($letter->planned_date)->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3 px-4">
                                    @if($letter->classification === 'RAHASIA')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                            🔒 RAHASIA
                                        </span>
                                    @elseif($letter->classification === 'TERBATAS')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            ⚠️ TERBATAS
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                            INTERNAL
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-800">{{ $letter->author->display_name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $letter->unit->name ?? 'Sekretariat' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-300">
                                        {{ $letter->status }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 ml-1">
                                        v{{ $letter->current_revision_no }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('letters.show', $letter) }}" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors text-[11px]" title="Detail Surat">
                                            Detail
                                        </a>
                                        @can('update', $letter)
                                            <a href="{{ route('letters.edit', $letter) }}" class="px-2.5 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold transition-colors text-[11px]" title="Revisi Draf">
                                                Revisi
                                            </a>
                                        @endcan
                                        <a href="{{ route('letters.preview', $letter) }}" target="_blank" class="px-2.5 py-1 rounded bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold transition-colors text-[11px]" title="Pratinjau Cetak">
                                            Pratinjau
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if($letters->hasPages())
                <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                    {{ $letters->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
