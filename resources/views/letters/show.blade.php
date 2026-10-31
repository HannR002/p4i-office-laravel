@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Status Notifications & Workflow Banners -->
    @if($letter->status === 'CHANGES_REQUESTED')
        @php
            $latestStep = $letter->approvalSteps()->where('decision', 'CHANGES_REQUESTED')->latest('decided_at')->first();
        @endphp
        <div class="p-4 bg-amber-50 rounded-xl border border-amber-300 text-amber-900 shadow-sm flex items-start gap-3">
            <span class="text-xl">⚠️</span>
            <div class="flex-1 text-xs">
                <div class="font-bold text-sm text-amber-950 mb-1">Permintaan Perbaikan Draf dari Reviewer</div>
                <p class="leading-relaxed mb-2 font-medium">
                    Catatan Reviewer: "{{ $latestStep->comments ?? 'Mohon periksa dan sesuaikan kembali poin-poin dalam draf surat ini.' }}"
                </p>
                <div class="flex items-center gap-2">
                    @can('update', $letter)
                        <a href="{{ route('letters.edit', $letter) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-700 hover:bg-amber-800 text-white font-semibold rounded-md transition-colors">
                            <span>✏️</span>
                            <span>Buat Revisi Perbaikan Baru</span>
                        </a>
                    @endcan
                    <span class="text-[11px] text-amber-700">Draf akan kembali ke status DRAFT setelah revisi baru disimpan.</span>
                </div>
            </div>
        </div>
    @elseif($letter->status === 'SUBMITTED')
        <div class="p-4 bg-blue-50 rounded-xl border border-blue-200 text-blue-900 shadow-sm flex items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">⏳</span>
                <div>
                    <span class="font-bold">Draf Telah Diajukan:</span> Sedang menunggu giliran pemeriksaan oleh reviewer berwenang. Penyuntingan dikunci sementara.
                </div>
            </div>
            <a href="{{ route('letters.history', $letter) }}" class="text-blue-700 hover:text-blue-900 font-semibold underline">
                Lihat Linimasa
            </a>
        </div>
    @elseif($letter->status === 'IN_REVIEW')
        <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-200 text-indigo-900 shadow-sm flex items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">🔍</span>
                <div>
                    <span class="font-bold">Sedang Ditelaah:</span> Draf surat ini sedang aktif diperiksa oleh pejabat reviewer.
                </div>
            </div>
            <a href="{{ route('letters.history', $letter) }}" class="text-indigo-700 hover:text-indigo-900 font-semibold underline">
                Lihat Catatan Pemeriksaan
            </a>
        </div>
    @elseif($letter->status === 'APPROVED')
        @php
            $approvedStep = $letter->approvalSteps()->where('decision', 'APPROVED')->latest('decided_at')->first();
        @endphp
        <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-300 text-emerald-950 shadow-sm flex items-start gap-3 text-xs">
            <span class="text-xl">✅</span>
            <div class="flex-1">
                <div class="font-bold text-sm text-emerald-900 mb-0.5">Draf Telah Disetujui (APPROVED)</div>
                <p class="leading-relaxed text-emerald-800">
                    Disetujui oleh: <strong>{{ $approvedStep->reviewer->display_name ?? 'Pejabat Pemeriksa' }}</strong> 
                    (Dasar Mandat: {{ $approvedStep->mandate->decree_number ?? '-' }}).
                    <span class="block mt-1 text-[11px] font-semibold text-emerald-900 bg-emerald-100/75 px-2 py-1 rounded inline-block">
                        PEMBERITAHUAN KEBIJAKAN: Status APPROVED merupakan persetujuan substansi internal. Nomor surat resmi belum diterbitkan (tetap NULL) sesuai mandat Sprint M2.
                    </span>
                </p>
            </div>
        </div>
    @elseif($letter->status === 'REJECTED')
        @php
            $rejectedStep = $letter->approvalSteps()->where('decision', 'REJECTED')->latest('decided_at')->first();
        @endphp
        <div class="p-4 bg-rose-50 rounded-xl border border-rose-300 text-rose-950 shadow-sm flex items-start gap-3 text-xs">
            <span class="text-xl">❌</span>
            <div>
                <div class="font-bold text-sm text-rose-900 mb-0.5">Draf Surat Ditolak (REJECTED)</div>
                <p class="leading-relaxed text-rose-800">
                    Alasan Penolakan: "{{ $rejectedStep->comments ?? 'Draf surat tidak memenuhi ketentuan atau format yang ditetapkan.' }}"
                </p>
            </div>
        </div>
    @endif

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('letters.index') }}" class="p-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors" title="Kembali ke Daftar">
                ←
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ $letter->subject }}</h1>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                        v{{ $letter->current_revision_no }}
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold 
                        @if($letter->status === 'APPROVED') bg-emerald-100 text-emerald-800 border-emerald-300
                        @elseif($letter->status === 'REJECTED') bg-rose-100 text-rose-800 border-rose-300
                        @elseif($letter->status === 'CHANGES_REQUESTED') bg-amber-100 text-amber-800 border-amber-300
                        @elseif($letter->status === 'SUBMITTED' || $letter->status === 'IN_REVIEW') bg-blue-100 text-blue-800 border-blue-300
                        @else bg-slate-100 text-slate-700 border-slate-300 @endif border">
                        {{ $letter->status }}
                    </span>
                    @if($letter->classification === 'RAHASIA')
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                            🔒 RAHASIA
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $letter->letterType->label ?? 'Surat Dinas' }} &bull; Dibuat oleh {{ $letter->author->display_name ?? '-' }} ({{ $letter->unit->name ?? 'Sekretariat' }})
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($letter->status === 'DRAFT')
                @can('submit', $letter)
                    <form action="{{ route('letters.submit', $letter) }}" method="POST" class="inline" onsubmit="return confirm('Ajukan draf surat revisi v{{ $letter->current_revision_no }} untuk pemeriksaan reviewer?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                            <span>🚀</span>
                            <span>Ajukan ke Reviewer</span>
                        </button>
                    </form>
                @endcan
            @endif

            @can('update', $letter)
                <a href="{{ route('letters.edit', $letter) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <span>✏️</span>
                    <span>Revisi Draf Baru</span>
                </a>
            @endcan

            <!-- Ekspor Dokumen Sprint M4A -->
            <form action="{{ route('letters.export', $letter) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="format" value="PDF">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-rose-700 hover:bg-rose-800 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors" title="Ekspor Draf ke format PDF ber-watermark">
                    <span>📄</span>
                    <span>Unduh Draf PDF</span>
                </button>
            </form>

            <form action="{{ route('letters.export', $letter) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="format" value="DOCX">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-700 hover:bg-sky-800 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors" title="Ekspor Draf ke format DOCX terstruktur">
                    <span>📝</span>
                    <span>Unduh Draf DOCX</span>
                </button>
            </form>

            <a href="{{ route('letters.preview', $letter) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                <span>🖨️</span>
                <span>Pratinjau Watermark</span>
            </a>

            <a href="{{ route('letters.history', $letter) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-lg border border-slate-300 transition-colors">
                <span>📜</span>
                <span>Riwayat & Linimasa</span>
            </a>
        </div>
    </div>

    <!-- Metadata Card -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
        <div>
            <div class="text-slate-400 font-semibold mb-0.5">Nomor Surat Resmi</div>
            <div class="font-mono font-bold text-amber-700 bg-amber-50 px-2 py-1 rounded inline-block">
                {{ $letter->official_number ?? 'BELUM DITERBITKAN (DRAF)' }}
            </div>
        </div>
        <div>
            <div class="text-slate-400 font-semibold mb-0.5">Tujuan / Penerima</div>
            <div class="font-bold text-slate-900">{{ $letter->recipient_name }}</div>
        </div>
        <div>
            <div class="text-slate-400 font-semibold mb-0.5">Tanggal Rencana</div>
            <div class="font-bold text-slate-900">{{ \Carbon\Carbon::parse($letter->planned_date)->translatedFormat('d F Y') }}</div>
        </div>
        <div>
            <div class="text-slate-400 font-semibold mb-0.5">Templat Dasar</div>
            <div class="font-medium text-slate-800">{{ $letter->template->title ?? '-' }} (v{{ $letter->template->version ?? '1' }})</div>
        </div>
    </div>

    <!-- Main Content & Revision Timeline Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Letter Body Content (2 cols) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div class="font-bold text-xs text-slate-800 flex items-center gap-2">
                        <span>📄</span>
                        <span>Isi Naskah Draf Terkini (Versi {{ $currentRevision->version ?? $letter->current_revision_no }})</span>
                    </div>
                    <div class="text-[11px] text-slate-500 font-mono">
                        SHA256: {{ substr($currentRevision->content_sha256 ?? '', 0, 12) }}...
                    </div>
                </div>

                <div class="p-6 prose prose-sm max-w-none text-slate-800 text-xs leading-relaxed space-y-4">
                    @if(isset($currentRevision->content['rendered_body']))
                        {!! $currentRevision->content['rendered_body'] !!}
                    @else
                        <p class="text-slate-400 italic">Isi surat belum dikonfigurasi.</p>
                    @endif
                </div>

                @if(!empty($currentRevision->content['variables']))
                    <div class="bg-slate-50/75 p-4 border-t border-slate-200">
                        <div class="text-[11px] font-bold text-slate-600 mb-2">Variabel Isian Formulir:</div>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                            @foreach($currentRevision->content['variables'] as $k => $v)
                                <div class="flex items-center gap-1.5">
                                    <dt class="font-semibold text-slate-500">{{ $k }}:</dt>
                                    <dd class="text-slate-800 font-medium">{{ is_array($v) ? json_encode($v) : $v }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </div>

            <!-- Arsip Berkas Ekspor Digital (Sprint M4A) -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div class="font-bold text-xs text-slate-800 flex items-center gap-2">
                        <span>📦</span>
                        <span>Berkas Ekspor & Arsip Digital Privat (Sprint M4A)</span>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-500">
                        {{ $letter->documentExports->count() }} Berkas Diarsipkan
                    </span>
                </div>

                <div class="p-4">
                    @if($letter->documentExports->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 text-slate-500 text-[11px]">
                                        <th class="pb-2 font-semibold">Format</th>
                                        <th class="pb-2 font-semibold">Revisi</th>
                                        <th class="pb-2 font-semibold">Checksum SHA-256</th>
                                        <th class="pb-2 font-semibold">Ukuran</th>
                                        <th class="pb-2 font-semibold">Diekspor Oleh</th>
                                        <th class="pb-2 font-semibold">Unduhan</th>
                                        <th class="pb-2 text-right font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($letter->documentExports->sortByDesc('created_at') as $export)
                                        <tr class="hover:bg-slate-50">
                                            <td class="py-2.5">
                                                @if($export->format === 'PDF')
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">PDF</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-200">DOCX</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 font-bold text-slate-800">
                                                v{{ $export->revision_version }}
                                            </td>
                                            <td class="py-2.5 font-mono text-[10px] text-slate-600 truncate max-w-[140px]" title="{{ $export->file_hash }}">
                                                {{ substr($export->file_hash, 0, 12) }}...{{ substr($export->file_hash, -4) }}
                                            </td>
                                            <td class="py-2.5 text-slate-600">
                                                {{ $export->file_size_formatted }}
                                            </td>
                                            <td class="py-2.5 text-slate-600">
                                                {{ $export->exporter->display_name ?? 'Sistem' }}<br>
                                                <span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($export->created_at)->format('d/m/Y H:i') }}</span>
                                            </td>
                                            <td class="py-2.5 text-slate-600">
                                                <span class="font-bold text-slate-800">{{ $export->download_count }}x</span>
                                                @if($export->last_downloaded_at)
                                                    <div class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($export->last_downloaded_at)->format('d/m H:i') }}</div>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-right">
                                                @if($export->isReady())
                                                    <a href="{{ route('archives.download', $export) }}" class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold rounded text-[11px] border border-indigo-200 transition-colors">
                                                        <span>⬇️</span>
                                                        <span>Unduh</span>
                                                    </a>
                                                @elseif($export->isError())
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Gagal</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 animate-pulse">Memproses...</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-6 text-slate-400 text-xs">
                            <span class="text-2xl block mb-1">📂</span>
                            Belum ada berkas ekspor untuk naskah surat ini.<br>
                            Gunakan tombol <strong>Unduh Draf PDF</strong> atau <strong>Unduh Draf DOCX</strong> di atas untuk menghasilkan arsip dokumen.
                        </div>
                    @endif
                </div>
            </div>
            </div>
        </div>

        <!-- Revision History Timeline (1 col) -->
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <h3 class="font-bold text-xs text-slate-900 mb-3 flex items-center gap-2">
                    <span>📜</span>
                    <span>Riwayat Revisi Immutabel ({{ $letter->revisions->count() }})</span>
                </h3>

                <div class="space-y-3 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-slate-200">
                    @foreach($letter->revisions->sortByDesc('version') as $rev)
                        <div class="relative flex items-start gap-3 pl-1">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold z-10 {{ $rev->version === $letter->current_revision_no ? 'bg-indigo-600 text-white ring-4 ring-indigo-50' : 'bg-slate-200 text-slate-700' }}">
                                v{{ $rev->version }}
                            </div>
                            <div class="flex-1 bg-slate-50 p-2.5 rounded-lg border border-slate-200 text-xs">
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <span class="font-bold text-slate-800">Versi {{ $rev->version }}</span>
                                    <span class="text-[10px] text-slate-400">
                                        {{ \Carbon\Carbon::parse($rev->created_at)->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-600 mb-2 leading-tight">
                                    {{ $rev->change_summary ?? 'Pembaruan naskah' }}
                                </p>
                                <div class="text-[10px] text-slate-400 mb-2 font-mono truncate" title="{{ $rev->content_sha256 }}">
                                    SHA: {{ substr($rev->content_sha256, 0, 16) }}...
                                </div>
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="text-slate-500 font-medium">Oleh: {{ $rev->creator->display_name ?? 'Staf' }}</span>
                                    <a href="{{ route('letters.preview', ['letter' => $letter, 'version' => $rev->version]) }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-semibold underline">
                                        Pratinjau v{{ $rev->version }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Catatan Integritas -->
            <div class="p-3.5 bg-blue-50 rounded-xl border border-blue-200 text-blue-900 text-[11px] leading-relaxed">
                <strong>JAMINAN IMMUTABILITAS:</strong> Seluruh baris revisi terlindungi oleh trigger MariaDB InnoDB (`trg_letter_revisions_no_update`). Revisi lama bersifat permanen dan tidak dapat diedit atau dihapus oleh siapapun.
            </div>
        </div>
    </div>
</div>
@endsection
