@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="bg-white p-6 rounded-xl border border-slate-200">
        <h1 class="text-base font-bold text-slate-900">{{ $letter->subject }}</h1>
        <div class="text-xs text-slate-500 mt-1">
            Status: <span class="font-bold text-indigo-700">{{ $letter->status }}</span> &bull; 
            Nomor Resmi: <span class="text-slate-400 italic">BELUM DITERBITKAN (OFF)</span>
        </div>
    </div>
</div>
@endsection
