@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-indigo-100 text-indigo-800 border border-indigo-300">
                    BUKU AGENDA SURAT KELUAR (NR-11)
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                    NOMOR RESMI: TETAP NULL
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 mt-2">Buku Agenda Persuratan Keluar</h1>
            <p class="text-xs text-slate-500 mt-1">
                Pencatatan dan pelacakan seluruh surat keluar Yayasan P4I, mulai dari tahap konseptor draf hingga disetujui (APPROVED).
            </p>
        </div>

        <div>
            <a href="{{ route('letters.create') }}" class="inline-flex items-center gap-2 bg-indigo-700 text-white text-xs font-bold px-4 py-2.5 rounded-lg hover:bg-indigo-800 transition-colors shadow-sm">
                <span>➕</span>
                <span>Buat Draf Surat Baru</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('agenda.outgoing') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Cari Subjek / Penerima</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ketik kata kunci..." class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Jenis Surat</label>
                <select name="letter_type_id" class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Jenis</option>
                    @foreach($letterTypes as $lt)
                    <option value="{{ $lt->id }}" {{ ($filters['letter_type_id'] ?? '') === $lt->id ? 'selected' : '' }}>
                        {{ $lt->code }} — {{ $lt->label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Status Surat</label>
                <select name="status" class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Status</option>
                    <option value="DRAFT" {{ ($filters['status'] ?? '') === 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                    <option value="SUBMITTED" {{ ($filters['status'] ?? '') === 'SUBMITTED' ? 'selected' : '' }}>SUBMITTED</option>
                    <option value="CHANGES_REQUESTED" {{ ($filters['status'] ?? '') === 'CHANGES_REQUESTED' ? 'selected' : '' }}>CHANGES REQUESTED</option>
                    <option value="APPROVED" {{ ($filters['status'] ?? '') === 'APPROVED' ? 'selected' : '' }}>APPROVED</option>
                    <option value="REJECTED" {{ ($filters['status'] ?? '') === 'REJECTED' ? 'selected' : '' }}>REJECTED</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold py-2 px-3 rounded-lg transition-colors">
                    Filter Agenda
                </button>
                <a href="{{ route('agenda.outgoing') }}" class="p-2 text-slate-500 hover:text-slate-800 text-xs border border-slate-300 rounded-lg">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Tabel Agenda Surat Keluar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Rekapitulasi Agenda Surat Keluar</h2>
            <span class="text-xs text-slate-500">Total: {{ $letters->total() }} Surat</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Tanggal Rencana</th>
                        <th class="p-3.5">Jenis Surat</th>
                        <th class="p-3.5">Nomor Surat Resmi (NR-15)</th>
                        <th class="p-3.5">Tujuan / Penerima</th>
                        <th class="p-3.5">Perihal</th>
                        <th class="p-3.5">Klasifikasi</th>
                        <th class="p-3.5">Konseptor (Author)</th>
                        <th class="p-3.5">Status Alur</th>
                        <th class="p-3.5 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($letters as $letter)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3.5 whitespace-nowrap text-slate-500">
                            {{ $letter->planned_date->format('d/m/Y') }}
                        </td>
                        <td class="p-3.5 font-bold text-indigo-700 font-mono">
                            {{ $letter->letterType->code ?? '-' }}
                        </td>
                        <td class="p-3.5">
                            @if($letter->official_number)
                                <span class="font-mono font-bold text-slate-900">{{ $letter->official_number }}</span>
                            @else
                                <span class="text-[11px] font-mono text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                    [NULL - NON-RESMI]
                                </span>
                            @endif
                        </td>
                        <td class="p-3.5 font-semibold text-slate-800">{{ $letter->recipient_name }}</td>
                        <td class="p-3.5 max-w-xs truncate" title="{{ $letter->subject }}">{{ $letter->subject }}</td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $letter->classification === 'CONFIDENTIAL' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $letter->classification }}
                            </span>
                        </td>
                        <td class="p-3.5 text-slate-600">{{ $letter->author->display_name ?? '-' }}</td>
                        <td class="p-3.5 whitespace-nowrap">
                            @if($letter->status === 'APPROVED')
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    APPROVED
                                </span>
                            @elseif($letter->status === 'SUBMITTED')
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">
                                    SUBMITTED
                                </span>
                            @elseif($letter->status === 'CHANGES_REQUESTED')
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    REVISI
                                </span>
                            @elseif($letter->status === 'REJECTED')
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                    REJECTED
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    DRAFT
                                </span>
                            @endif
                        </td>
                        <td class="p-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('letters.show', $letter->id) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                                Lihat &rarr;
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center text-slate-400">
                            Tidak ada surat keluar yang sesuai dengan kriteria pencarian.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($letters->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $letters->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
