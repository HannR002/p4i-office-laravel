@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Antrean Pemeriksaan (Reviewer Inbox)</h1>
            <p class="text-xs text-slate-500">Draf surat yang menunggu telaah dan persetujuan berdasarkan mandat kewenangan aktif</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs bg-amber-50 text-amber-800 border border-amber-200 px-3 py-1.5 rounded-lg font-bold">
                {{ $pendingRequests->total() }} Pengajuan Aktif
            </span>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 text-xs">
        <a href="{{ route('approvals.index') }}" class="px-4 py-2.5 font-semibold {{ empty($statusFilter) ? 'text-indigo-700 border-b-2 border-indigo-700' : 'text-slate-500 hover:text-slate-800' }}">
            Semua Antrean
        </a>
        <a href="{{ route('approvals.index', ['status' => 'PENDING']) }}" class="px-4 py-2.5 font-semibold {{ $statusFilter === 'PENDING' ? 'text-indigo-700 border-b-2 border-indigo-700' : 'text-slate-500 hover:text-slate-800' }}">
            Menunggu Telaah (SUBMITTED)
        </a>
        <a href="{{ route('approvals.index', ['status' => 'IN_REVIEW']) }}" class="px-4 py-2.5 font-semibold {{ $statusFilter === 'IN_REVIEW' ? 'text-indigo-700 border-b-2 border-indigo-700' : 'text-slate-500 hover:text-slate-800' }}">
            Sedang Ditelaah (IN_REVIEW)
        </a>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($pendingRequests->isEmpty())
            <div class="p-12 text-center text-xs text-slate-500 space-y-2">
                <div class="text-3xl">📭</div>
                <div class="font-bold text-slate-700 text-sm">Antrean Pemeriksaan Bersih</div>
                <p class="text-slate-400 max-w-sm mx-auto">Tidak ada draf surat yang menunggu pemeriksaan Anda saat ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4 font-semibold">Tanggal Diajukan</th>
                            <th class="py-3 px-4 font-semibold">Pembuat Draf</th>
                            <th class="py-3 px-4 font-semibold">Perihal Surat</th>
                            <th class="py-3 px-4 font-semibold">Jenis Surat</th>
                            <th class="py-3 px-4 font-semibold">Versi</th>
                            <th class="py-3 px-4 font-semibold">Status</th>
                            <th class="py-3 px-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($pendingRequests as $req)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-500">
                                    {{ \Carbon\Carbon::parse($req->submitted_at)->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="font-semibold text-slate-900">{{ $req->submitter->display_name ?? '-' }}</span>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <div class="font-bold text-slate-900 truncate" title="{{ $req->letter->subject ?? '-' }}">
                                        {{ $req->letter->subject ?? '-' }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate">
                                        Tujuan: {{ $req->letter->recipient_name ?? '-' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ $req->letter->letterType->label ?? '-' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        v{{ $req->revision_version }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if($req->status === 'IN_REVIEW')
                                        <span class="px-2.5 py-1 rounded text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-300">
                                            Sedang Ditelaah
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                            Menunggu Telaah
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-right">
                                    <a href="{{ route('approvals.show', $req->letter_id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-700 hover:bg-indigo-800 text-white font-semibold rounded-lg shadow-sm text-xs transition-colors">
                                        <span>🔍</span>
                                        <span>Periksa Draf</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($pendingRequests->hasPages())
                <div class="p-4 border-t border-slate-200 bg-slate-50">
                    {{ $pendingRequests->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
