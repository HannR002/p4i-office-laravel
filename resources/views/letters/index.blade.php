@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Daftar Surat Dinas</h1>
            <p class="text-xs text-slate-500">Seluruh draf dan arsip surat internal organisasi</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if($letters->isEmpty())
            <div class="p-8 text-center text-xs text-slate-400">
                Belum ada data surat. Modul draf akan aktif penuh pada Sprint M1.
            </div>
        @else
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-semibold">
                    <tr>
                        <th class="py-2.5 px-4">Perihal</th>
                        <th class="py-2.5 px-4">Tujuan</th>
                        <th class="py-2.5 px-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($letters as $letter)
                        <tr>
                            <td class="py-2.5 px-4 font-bold">{{ $letter->subject }}</td>
                            <td class="py-2.5 px-4 text-slate-600">{{ $letter->recipient_name }}</td>
                            <td class="py-2.5 px-4">{{ $letter->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
