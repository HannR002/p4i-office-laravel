@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('letters.show', $letter) }}" class="p-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors" title="Kembali ke Detail Surat">
                ←
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Daur Hidup & Linimasa Keputusan</h1>
                <p class="text-xs text-slate-500">Perihal: {{ $letter->subject }} &bull; Status: <span class="font-bold text-slate-700">{{ $letter->status }}</span></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('letters.preview', $letter) }}" target="_blank" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                🖨️ Pratinjau Watermark
            </a>
        </div>
    </div>

    <!-- Overview Card -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div>
            <span class="text-slate-400 font-medium block">Nomor Surat Resmi</span>
            <span class="font-mono font-bold text-amber-700">{{ $letter->official_number ?? 'BELUM DITERBITKAN (DRAF)' }}</span>
        </div>
        <div>
            <span class="text-slate-400 font-medium block">Pembuat Draf</span>
            <span class="font-semibold text-slate-800">{{ $letter->author->display_name ?? '-' }}</span>
        </div>
        <div>
            <span class="text-slate-400 font-medium block">Revisi Terkini</span>
            <span class="font-bold text-indigo-700">Versi {{ $letter->current_revision_no }}</span>
        </div>
        <div>
            <span class="text-slate-400 font-medium block">Klasifikasi Keamanan</span>
            <span class="font-semibold text-slate-800">{{ $letter->classification }}</span>
        </div>
    </div>

    <!-- Unified Lifecycle Timeline -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
        <h2 class="font-bold text-sm text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
            <span>📜</span>
            <span>Linimasa Riwayat Status & Keputusan Pemeriksaan</span>
        </h2>

        @if($letter->statusHistory->isEmpty())
            <p class="text-xs text-slate-400 italic py-4">Belum ada riwayat status tercatat.</p>
        @else
            <div class="space-y-4 relative before:absolute before:inset-0 before:left-4 before:w-0.5 before:bg-slate-200 pl-2">
                @foreach($letter->statusHistory as $hist)
                    <div class="relative flex items-start gap-4">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold z-10 
                            @if($hist->to_status === 'APPROVED') bg-emerald-600 text-white ring-4 ring-emerald-50
                            @elseif($hist->to_status === 'REJECTED') bg-rose-600 text-white ring-4 ring-rose-50
                            @elseif($hist->to_status === 'CHANGES_REQUESTED') bg-amber-600 text-white ring-4 ring-amber-50
                            @elseif($hist->to_status === 'SUBMITTED' || $hist->to_status === 'IN_REVIEW') bg-blue-600 text-white ring-4 ring-blue-50
                            @else bg-slate-500 text-white ring-4 ring-slate-100 @endif">
                            @if($hist->to_status === 'APPROVED') ✓
                            @elseif($hist->to_status === 'REJECTED') ✕
                            @elseif($hist->to_status === 'CHANGES_REQUESTED') ⚠
                            @elseif($hist->to_status === 'SUBMITTED') 🚀
                            @elseif($hist->to_status === 'IN_REVIEW') 🔍
                            @else • @endif
                        </div>

                        <div class="flex-1 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                            <div class="flex items-center justify-between mb-1.5 flex-wrap gap-1">
                                <div class="font-bold text-slate-900 flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                        @if($hist->to_status === 'APPROVED') bg-emerald-100 text-emerald-800 border border-emerald-300
                                        @elseif($hist->to_status === 'REJECTED') bg-rose-100 text-rose-800 border border-rose-300
                                        @elseif($hist->to_status === 'CHANGES_REQUESTED') bg-amber-100 text-amber-800 border border-amber-300
                                        @elseif($hist->to_status === 'SUBMITTED') bg-blue-100 text-blue-800 border border-blue-300
                                        @elseif($hist->to_status === 'IN_REVIEW') bg-indigo-100 text-indigo-800 border border-indigo-300
                                        @else bg-slate-200 text-slate-800 @endif">
                                        {{ $hist->from_status }} → {{ $hist->to_status }}
                                    </span>
                                    <span>Oleh: {{ $hist->actor->display_name ?? 'Sistem' }}</span>
                                </div>
                                <span class="text-[11px] text-slate-400">
                                    {{ \Carbon\Carbon::parse($hist->created_at)->format('d F Y, H:i') }} WIB
                                </span>
                            </div>

                            @if($hist->reason)
                                <div class="mt-2 p-2.5 bg-white rounded-lg border border-slate-200 text-slate-700 leading-relaxed">
                                    <span class="font-semibold text-slate-500 block mb-0.5">Catatan / Alasan:</span>
                                    {{ $hist->reason }}
                                </div>
                            @endif

                            @if($hist->revision_id)
                                <div class="mt-2 text-[11px] text-slate-500 font-mono">
                                    Terkait Revisi ID: {{ substr($hist->revision_id, 0, 8) }}...
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Approval Steps Detail (if any decisions taken) -->
    @if($letter->approvalSteps->isNotEmpty())
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
            <h2 class="font-bold text-sm text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                <span>⚖️</span>
                <span>Keputusan Pejabat Pemeriksa (Binding Approval Steps)</span>
            </h2>

            <div class="space-y-3">
                @foreach($letter->approvalSteps as $step)
                    <div class="p-4 rounded-xl border 
                        @if($step->decision === 'APPROVED') bg-emerald-50/50 border-emerald-200
                        @elseif($step->decision === 'REJECTED') bg-rose-50/50 border-rose-200
                        @else bg-amber-50/50 border-amber-200 @endif text-xs">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm 
                                @if($step->decision === 'APPROVED') text-emerald-800
                                @elseif($step->decision === 'REJECTED') text-rose-800
                                @else text-amber-800 @endif">
                                KEPUTUSAN: {{ $step->decision }}
                            </span>
                            <span class="text-slate-500 text-[11px]">
                                {{ \Carbon\Carbon::parse($step->decided_at)->format('d F Y, H:i:s') }} WIB
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] mb-2">
                            <div>
                                <span class="text-slate-500 font-medium">Reviewer:</span>
                                <span class="font-semibold text-slate-800">{{ $step->reviewer->display_name ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 font-medium">Dasar Mandat:</span>
                                <span class="font-semibold text-slate-800">{{ $step->mandate->decree_number ?? '-' }}</span>
                            </div>
                            <div class="sm:col-span-2 font-mono text-[10px] text-slate-500">
                                Binding Content Hash: {{ $step->decision_content_sha256 }}
                            </div>
                        </div>

                        @if($step->comments)
                            <div class="p-2.5 bg-white rounded-lg border border-slate-200 text-slate-800">
                                <strong>Komentar:</strong> {{ $step->comments }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
