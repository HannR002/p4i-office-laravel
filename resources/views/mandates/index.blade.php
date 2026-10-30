@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Manajemen Mandat Pejabat</h1>
            <p class="text-xs text-slate-500">Dasar hukum SK penetapan pejabat pemeriksa dan penandatangan surat</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-xs text-slate-400">
        @if($mandates->isEmpty())
            Belum ada mandat pejabat yang terdaftar. Penetapan SK resmi akan diatur melalui Sprint M1.
        @else
            Ditemukan {{ $mandates->count() }} mandat terdaftar.
        @endif
    </div>
</div>
@endsection
