@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Katalog Mandat Pejabat (Authority Mandates)</h1>
            <p class="text-xs text-slate-500">Daftar ketetapan wewenang penelaahan dan persetujuan surat dinas berdasarkan SK Pengurus Yayasan P4I</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs bg-slate-100 text-slate-800 border border-slate-300 px-3 py-1.5 rounded-lg font-bold">
                {{ $mandates->count() }} Mandat Terdaftar
            </span>
        </div>
    </div>

    <!-- Governance Notice Banner -->
    <div class="p-4 bg-amber-50 rounded-xl border border-amber-200 text-amber-950 text-xs shadow-sm flex items-start gap-3">
        <span class="text-xl">⚠️</span>
        <div>
            <div class="font-bold text-sm text-amber-900 mb-0.5">PEMBERITAHUAN TATA KELOLA ADMINISTRATIF (SPRINT M2)</div>
            <p class="leading-relaxed text-amber-800">
                Seluruh data mandat bertanda badge <strong>[SIMULASI]</strong> merupakan instrumen uji coba sistem pada lingkungan pengembangan lokal dan <strong>TIDAK BERLAKU</strong> sebagai penetapan hukum yayasan definitif. Penetapan mandat pejabat operasional sejati merupakan wewenang tunggal Ketua/Pengurus Yayasan P4I melalui Surat Keputusan resmi.
            </p>
        </div>
    </div>

    <!-- Mandates Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($mandates->isEmpty())
            <div class="p-12 text-center text-xs text-slate-500 space-y-2">
                <div class="text-3xl">📭</div>
                <div class="font-bold text-slate-700 text-sm">Belum Ada Mandat Terdaftar</div>
                <p class="text-slate-400 max-w-sm mx-auto">Mandat pejabat belum dikonfigurasi pada organisasi ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4 font-semibold">Pejabat Penerima Mandat</th>
                            <th class="py-3 px-4 font-semibold">Nomor SK / Dasar Keputusan</th>
                            <th class="py-3 px-4 font-semibold">Cakupan Jenis Surat</th>
                            <th class="py-3 px-4 font-semibold">Kewenangan</th>
                            <th class="py-3 px-4 font-semibold">Masa Berlaku</th>
                            <th class="py-3 px-4 font-semibold">Status</th>
                            <th class="py-3 px-4 font-semibold text-right">Tipe Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($mandates as $mandate)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $mandate->user->display_name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $mandate->user->email ?? '-' }}</div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap font-mono text-[11px] font-semibold text-slate-800">
                                    {{ $mandate->decree_number }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if($mandate->letterType)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $mandate->letterType->label }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            Semua Jenis Surat
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @if($mandate->can_review)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                                Review
                                            </span>
                                        @endif
                                        @if($mandate->can_approve)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                Approve
                                            </span>
                                        @endif
                                        @if($mandate->can_sign)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800 border border-purple-200" title="Tanda tangan dinonaktifkan di Sprint M2">
                                                Sign (Off)
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-[11px] text-slate-600">
                                    {{ $mandate->valid_from->format('d/m/Y') }} s/d {{ $mandate->valid_to->format('d/m/Y') }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if($mandate->isValidNow())
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            AKTIF
                                        </span>
                                    @elseif($mandate->status === 'REVOKED' || $mandate->revoked_at !== null)
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                            DICABUT (REVOKED)
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700">
                                            KADALUARSA (EXPIRED)
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-right">
                                    @if($mandate->is_simulation || str_contains($mandate->decree_number, 'SIM'))
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300 tracking-wider">
                                            SIMULASI
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-300">
                                            RESMI
                                        </span>
                                    @endif
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
