@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    BUKU AGENDA SURAT MASUK (NR-12)
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-indigo-100 text-indigo-800 border border-indigo-300">
                    NOMOR AGENDA TERPISAH
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 mt-2">Buku Agenda Persuratan Masuk</h1>
            <p class="text-xs text-slate-500 mt-1">
                Pencatatan surat masuk dari pihak eksternal. Nomor surat asli instansi luar secara ketat dipisahkan dari Nomor Agenda Internal Yayasan P4I (format: <code class="bg-slate-100 px-1 py-0.5 rounded font-mono text-indigo-700">AG-IN-YYYY-XXXX</code>).
            </p>
        </div>

        <div>
            <a href="{{ route('agenda.incoming.create') }}" class="inline-flex items-center gap-2 bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-lg hover:bg-emerald-800 transition-colors shadow-sm">
                <span>📥</span>
                <span>Registrasi Surat Masuk Baru</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('agenda.incoming') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Cari Pengirim / Nomor / Perihal</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ketik kata kunci..." class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Klasifikasi</label>
                <select name="classification" class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-emerald-500">
                    <option value="">Semua Klasifikasi</option>
                    <option value="INTERNAL" {{ ($filters['classification'] ?? '') === 'INTERNAL' ? 'selected' : '' }}>INTERNAL</option>
                    <option value="CONFIDENTIAL" {{ ($filters['classification'] ?? '') === 'CONFIDENTIAL' ? 'selected' : '' }}>CONFIDENTIAL (RAHASIA)</option>
                    <option value="PUBLIC" {{ ($filters['classification'] ?? '') === 'PUBLIC' ? 'selected' : '' }}>PUBLIC</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Tanggal Diterima (Mulai)</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full text-xs rounded-lg border border-slate-300 p-2 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold py-2 px-3 rounded-lg transition-colors">
                    Filter Agenda
                </button>
                <a href="{{ route('agenda.incoming') }}" class="p-2 text-slate-500 hover:text-slate-800 text-xs border border-slate-300 rounded-lg">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Tabel Agenda Surat Masuk -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Rekapitulasi Agenda Surat Masuk</h2>
            <span class="text-xs text-slate-500">Total: {{ $incomingLetters->total() }} Surat Masuk</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Tanggal Terima</th>
                        <th class="p-3.5">Nomor Agenda Internal P4I</th>
                        <th class="p-3.5">Nomor Surat Asli Pengirim (NR-12)</th>
                        <th class="p-3.5">Tanggal Surat</th>
                        <th class="p-3.5">Instansi / Nama Pengirim</th>
                        <th class="p-3.5">Perihal Surat</th>
                        <th class="p-3.5">Tujuan Unit</th>
                        <th class="p-3.5">Klasifikasi</th>
                        <th class="p-3.5">Petugas Pencatat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($incomingLetters as $item)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3.5 whitespace-nowrap text-slate-500">
                            {{ $item->received_date->format('d/m/Y') }}
                        </td>
                        <td class="p-3.5 font-bold font-mono text-emerald-700">
                            {{ $item->internal_agenda_number }}
                        </td>
                        <td class="p-3.5 font-mono text-slate-900 font-medium">
                            {{ $item->sender_letter_number }}
                        </td>
                        <td class="p-3.5 whitespace-nowrap text-slate-500">
                            {{ $item->letter_date->format('d/m/Y') }}
                        </td>
                        <td class="p-3.5 font-semibold text-slate-800">{{ $item->sender_name }}</td>
                        <td class="p-3.5 max-w-xs truncate" title="{{ $item->subject }}">{{ $item->subject }}</td>
                        <td class="p-3.5 text-slate-600">{{ $item->recipientUnit->name ?? 'Sekretariat Utama' }}</td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $item->classification === 'CONFIDENTIAL' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $item->classification }}
                            </span>
                        </td>
                        <td class="p-3.5 text-slate-600">{{ $item->recorder->display_name ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center text-slate-400">
                            Belum ada surat masuk yang tercatat pada Buku Agenda.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($incomingLetters->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $incomingLetters->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
