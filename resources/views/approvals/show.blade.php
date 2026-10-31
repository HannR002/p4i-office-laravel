@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('approvals.index') }}" class="p-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors" title="Kembali ke Antrean">
                ←
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ $letter->subject }}</h1>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                        v{{ $letter->current_revision_no }}
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                        {{ $letter->status }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $letter->letterType->label ?? '-' }} &bull; Diajukan oleh {{ $letter->author->display_name ?? '-' }} ({{ $letter->unit->name ?? 'Sekretariat' }})
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('letters.preview', $letter) }}" target="_blank" class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                🖨️ Pratinjau Watermark Draf
            </a>
            <a href="{{ route('letters.history', $letter) }}" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-lg border border-slate-300">
                📜 Linimasa Status
            </a>
        </div>
    </div>

    <!-- Metadata & Mandate Verification Bar -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
        <div>
            <span class="text-slate-400 font-medium block mb-0.5">Kewenangan Pemeriksa Anda</span>
            <div class="flex items-center gap-1.5 flex-wrap">
                @if($canApprove)
                    <span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 font-bold border border-emerald-300">
                        ✓ Berwenang Menyetujui (APPROVE)
                    </span>
                @elseif($canReview)
                    <span class="px-2 py-1 rounded bg-blue-100 text-blue-800 font-bold border border-blue-300">
                        ✓ Berwenang Menelaah (REVIEW)
                    </span>
                @else
                    <span class="px-2 py-1 rounded bg-rose-100 text-rose-800 font-bold border border-rose-300">
                        ✕ Mandat Tidak Mencukupi
                    </span>
                @endif
            </div>
        </div>

        <div>
            <span class="text-slate-400 font-medium block mb-0.5">Hash Integritas Konten Draf (SHA-256)</span>
            <div class="font-mono text-[11px] text-slate-700 bg-slate-50 p-1.5 rounded border border-slate-200 truncate" title="{{ $letter->latestRevision->content_sha256 ?? '-' }}">
                {{ $letter->latestRevision->content_sha256 ?? '-' }}
            </div>
        </div>

        <div>
            <span class="text-slate-400 font-medium block mb-0.5">Nomor Surat Resmi</span>
            <div class="font-mono font-bold text-amber-700 bg-amber-50 px-2 py-1 rounded inline-block">
                {{ $letter->official_number ?? 'BELUM DITERBITKAN (DRAF)' }}
            </div>
        </div>
    </div>

    <!-- Reviewer Action Panel -->
    @if($letter->status === 'SUBMITTED')
        <div class="bg-indigo-50 border border-indigo-200 p-5 rounded-xl shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="font-bold text-sm text-indigo-950 mb-1">Mulai Telaah Draf Surat</h3>
                <p class="text-xs text-indigo-800">Draf ini baru diajukan dan belum diklaim oleh reviewer. Klik tombol di samping untuk mulai memeriksa.</p>
            </div>
            @if($canReview || $canApprove)
                <form action="{{ route('approvals.claim', $letter) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-indigo-700 hover:bg-indigo-800 text-white font-semibold rounded-lg text-xs shadow-sm transition-colors whitespace-nowrap">
                        🔍 Mulai Periksa (Start Review)
                    </button>
                </form>
            @endif
        </div>
    @elseif($letter->status === 'IN_REVIEW')
        <div class="bg-white border border-slate-200 p-6 rounded-xl shadow-sm space-y-5">
            <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                <span>⚖️</span>
                <span>Lembar Keputusan Reviewer</span>
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Action 1: Approve -->
                @if($canApprove)
                    <div class="p-4 bg-emerald-50/60 rounded-xl border border-emerald-200 space-y-3">
                        <div class="font-bold text-xs text-emerald-900 flex items-center gap-1.5">
                            <span>✅</span>
                            <span>Setujui Draf (Approve)</span>
                        </div>
                        <p class="text-[11px] text-emerald-800 leading-relaxed">
                            Menyetujui isi substansi naskah revisi v{{ $letter->current_revision_no }}. Nomor surat tetap belum diterbitkan.
                        </p>
                        <form action="{{ route('approvals.decision', $letter) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin menyetujui draf surat ini?');" class="space-y-3">
                            @csrf
                            <input type="hidden" name="decision" value="APPROVED">
                            <input type="hidden" name="expected_version" value="{{ $letter->current_revision_no }}">
                            <input type="hidden" name="expected_sha256" value="{{ $letter->latestRevision->content_sha256 ?? '' }}">
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                            <textarea name="comments" rows="2" placeholder="Catatan persetujuan (opsional)..." class="w-full text-xs p-2 rounded-lg border border-emerald-300 focus:ring-1 focus:ring-emerald-500"></textarea>

                            <button type="submit" class="w-full py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg text-xs shadow-sm transition-colors">
                                Setujui Revisi Ini
                            </button>
                        </form>
                    </div>
                @else
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-slate-500 text-xs">
                        <div class="font-bold text-slate-700 mb-1">Setujui Draf (APPROVE)</div>
                        <p class="text-[11px]">Akun Anda tidak memiliki cakupan mandat APPROVE pada jenis surat ini.</p>
                    </div>
                @endif

                <!-- Action 2: Request Changes -->
                <div class="p-4 bg-amber-50/60 rounded-xl border border-amber-200 space-y-3">
                    <div class="font-bold text-xs text-amber-900 flex items-center gap-1.5">
                        <span>⚠️</span>
                        <span>Minta Perbaikan (Changes)</span>
                    </div>
                    <p class="text-[11px] text-amber-800 leading-relaxed">
                        Kembalikan draf kepada staf pembuat untuk diperbaiki dengan mencantumkan alasan.
                    </p>
                    <form action="{{ route('approvals.decision', $letter) }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" name="decision" value="CHANGES_REQUESTED">
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                        <textarea name="reason" rows="2" required placeholder="Alasan perbaikan yang wajib dipenuhi..." class="w-full text-xs p-2 rounded-lg border border-amber-300 focus:ring-1 focus:ring-amber-500"></textarea>

                        <button type="submit" class="w-full py-2 bg-amber-700 hover:bg-amber-800 text-white font-semibold rounded-lg text-xs shadow-sm transition-colors">
                            Kembalikan untuk Revisi
                        </button>
                    </form>
                </div>

                <!-- Action 3: Reject -->
                <div class="p-4 bg-rose-50/60 rounded-xl border border-rose-200 space-y-3">
                    <div class="font-bold text-xs text-rose-900 flex items-center gap-1.5">
                        <span>❌</span>
                        <span>Tolak Draf (Reject)</span>
                    </div>
                    <p class="text-[11px] text-rose-800 leading-relaxed">
                        Menolak draf surat secara definitif. Draf tidak dapat dilanjutkan kembali.
                    </p>
                    <form action="{{ route('approvals.decision', $letter) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin menolak draf surat ini secara permanen?');" class="space-y-3">
                        @csrf
                        <input type="hidden" name="decision" value="REJECTED">
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                        <textarea name="reason" rows="2" required placeholder="Alasan penolakan surat..." class="w-full text-xs p-2 rounded-lg border border-rose-300 focus:ring-1 focus:ring-rose-500"></textarea>

                        <button type="submit" class="w-full py-2 bg-rose-700 hover:bg-rose-800 text-white font-semibold rounded-lg text-xs shadow-sm transition-colors">
                            Tolak Draf Surat
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Letter Body Preview -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <div class="font-bold text-xs text-slate-800 flex items-center gap-2">
                <span>📄</span>
                <span>Isi Naskah Draf yang Ditelaah (Versi {{ $letter->current_revision_no }})</span>
            </div>
            <div class="text-[11px] text-slate-500 font-mono">
                Klasifikasi: {{ $letter->classification }}
            </div>
        </div>

        <div class="p-6 prose prose-sm max-w-none text-slate-800 text-xs leading-relaxed space-y-4">
            @if(isset($letter->latestRevision->content['rendered_body']))
                {!! $letter->latestRevision->content['rendered_body'] !!}
            @else
                <p class="text-slate-400 italic">Konten draf belum tersedia.</p>
            @endif
        </div>

        @if(!empty($letter->latestRevision->content['variables']))
            <div class="bg-slate-50/75 p-4 border-t border-slate-200">
                <div class="text-[11px] font-bold text-slate-600 mb-2">Variabel Isian Formulir:</div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                    @foreach($letter->latestRevision->content['variables'] as $k => $v)
                        <div class="flex items-center gap-1.5">
                            <dt class="font-semibold text-slate-500">{{ $k }}:</dt>
                            <dd class="text-slate-800 font-medium">{{ is_array($v) ? json_encode($v) : $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
</div>
@endsection
