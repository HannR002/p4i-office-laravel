@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Welcome Header -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Selamat Datang, {{ $user->display_name }}</h1>
            <p class="text-xs text-slate-500 mt-1">
                Unit Kerja: <strong>{{ $user->unit->name ?? 'Sekretariat Yayasan' }}</strong> &bull; Organisasi: <strong>{{ $user->organization->legal_name ?? 'Yayasan P4I' }}</strong>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @foreach($user->roles as $role)
                <span class="text-xs bg-indigo-50 text-indigo-800 border border-indigo-200 px-2.5 py-1 rounded-md font-bold">
                    {{ $role->label }}
                </span>
            @endforeach
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200">
            <div class="text-xs text-slate-500 font-medium">Draf Surat Saya</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $myDraftsCount }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Naskah dalam pengerjaan</div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200">
            <div class="text-xs text-slate-500 font-medium">Menunggu Pemeriksaan</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">{{ $pendingApprovalsCount }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Antrean reviewer yayasan</div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200">
            <div class="text-xs text-slate-500 font-medium">Mandat Pejabat Aktif</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $activeMandatesCount }}</div>
            <div class="text-[11px] text-slate-400 mt-1">SK penetapan kewenangan</div>
        </div>
    </div>

    <!-- Hostinger Architecture Milestone Info -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 text-xs text-indigo-950 space-y-2">
        <div class="font-bold flex items-center gap-2 text-indigo-900">
            <span>🚀</span>
            <span>STATUS IMPLEMENTASI SPRINT M0 (HOSTINGER BUSINESS WEB HOSTING / MARIADB)</span>
        </div>
        <p class="leading-relaxed text-indigo-800">
            Aplikasi Laravel 10 LTS telah dikonfigurasi paralel dengan skema basis data MariaDB InnoDB transaksional (15 tabel). Fitur penomoran surat resmi terkunci (OFF) sesuai regulasi tata kelola. Seluruh pengujian awal telah diverifikasi via PHPUnit.
        </p>
    </div>

    <!-- Recent Letters Table Preview -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Surat Dinas Terbaru</h2>
            <a href="{{ route('letters.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold">Lihat Semua &rarr;</a>
        </div>

        @if($recentLetters->isEmpty())
            <div class="p-8 text-center text-xs text-slate-400">
                Belum ada draf surat dinas yang dibuat.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-semibold">
                        <tr>
                            <th class="py-2.5 px-4">Perihal</th>
                            <th class="py-2.5 px-4">Jenis</th>
                            <th class="py-2.5 px-4">Pembuat</th>
                            <th class="py-2.5 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($recentLetters as $letter)
                            <tr>
                                <td class="py-2.5 px-4 font-bold text-slate-900">{{ $letter->subject }}</td>
                                <td class="py-2.5 px-4 text-slate-600">{{ $letter->letterType->label ?? '-' }}</td>
                                <td class="py-2.5 px-4 text-slate-600">{{ $letter->author->display_name ?? '-' }}</td>
                                <td class="py-2.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                        {{ $letter->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
