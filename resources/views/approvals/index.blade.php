@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Antrean Pemeriksaan (Approval Queue)</h1>
            <p class="text-xs text-slate-500">Draf surat menunggu telaah reviewer berwenang berdasarkan SK Mandat</p>
        </div>
        <span class="text-xs bg-amber-50 text-amber-800 border border-amber-200 px-3 py-1 rounded-lg font-bold">
            {{ $pendingRequests->count() }} Menunggu
        </span>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-xs text-slate-400">
        @if($pendingRequests->isEmpty())
            Antrean pemeriksaan bersih. Tidak ada pengajuan draf surat yang tertunda.
        @else
            Ditemukan {{ $pendingRequests->count() }} pengajuan aktif.
        @endif
    </div>
</div>
@endsection
