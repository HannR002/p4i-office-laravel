<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pratinjau Draf — {{ $letter->subject }} (v{{ $revision->version }})</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                padding: 0 !important;
            }
            .document-sheet {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }

        .watermark-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 4.5rem;
            font-weight: 900;
            color: rgba(220, 38, 38, 0.12); /* Semi-transparent Red */
            text-transform: uppercase;
            letter-spacing: 0.25em;
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
            z-index: 10;
        }

        .watermark-repeater {
            background-image: repeating-linear-gradient(
                -45deg,
                rgba(239, 68, 68, 0.04),
                rgba(239, 68, 68, 0.04) 150px,
                transparent 150px,
                transparent 300px
            );
        }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased min-h-screen py-8 px-4 flex flex-col items-center">

    <!-- Top Floating Toolbar (No Print) -->
    <div class="no-print max-w-3xl w-full mb-4 flex items-center justify-between bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm text-xs">
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
            <span class="font-bold text-slate-800">Mode Pratinjau Naskah</span>
            <span class="bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded text-[10px]">
                REVISI v{{ $revision->version }}
            </span>
            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-mono">
                SHA: {{ substr($revision->content_sha256, 0, 10) }}...
            </span>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-1.5 bg-indigo-700 hover:bg-indigo-800 text-white font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                <span>🖨️</span> Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition-colors">
                Tutup
            </button>
        </div>
    </div>

    <!-- Mandatory Disclaimer Banner (Visible in Print & Screen) -->
    <div class="max-w-3xl w-full mb-3 p-3 bg-amber-50 border-2 border-dashed border-amber-300 rounded-lg text-amber-900 text-xs text-center font-bold tracking-wide">
        ⚠️ DRAF — BELUM RESMI / TIDAK MEMILIKI KEKUATAN HUKUM / NOMOR SURAT BELUM DIALOKASIKAN
    </div>

    <!-- A4 Paper Document Sheet -->
    <div class="document-sheet relative bg-white max-w-3xl w-full p-10 md:p-14 rounded-lg border border-slate-200 shadow-lg watermark-repeater overflow-hidden text-slate-900 leading-relaxed text-xs">
        <!-- Giant Diagonal Watermark -->
        <div class="watermark-overlay">
            DRAF — BELUM RESMI
        </div>

        <!-- Kop Surat Yayasan P4I -->
        <div class="border-b-2 border-slate-900 pb-4 mb-6 text-center relative z-20">
            <div class="text-sm font-extrabold uppercase tracking-wider text-slate-900">
                Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia (P4I)
            </div>
            <div class="text-[10px] text-slate-600 mt-1">
                Sekretariat Utama &bull; Gedung P4I Lantai 3 &bull; Jakarta, Indonesia
            </div>
            <div class="text-[9px] text-slate-400 mt-0.5">
                Surat Elektronik: sekretariat@p4i.or.id &bull; Laman Resmi: https://p4i.or.id
            </div>
        </div>

        <!-- Metadata Header Surat -->
        <div class="flex justify-between items-start mb-6 text-xs relative z-20">
            <div class="space-y-1">
                <div class="flex gap-2">
                    <span class="w-20 font-semibold text-slate-600">Nomor</span>
                    <span class="font-mono font-bold text-amber-700">: {{ $letter->official_number ?? 'BELUM DITERBITKAN (DRAF)' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-20 font-semibold text-slate-600">Klasifikasi</span>
                    <span class="font-bold">: {{ $revision->classification }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-20 font-semibold text-slate-600">Perihal</span>
                    <span class="font-bold">: {{ $revision->subject }}</span>
                </div>
            </div>

            <div class="text-right">
                <div class="font-semibold text-slate-700">
                    Jakarta, {{ \Carbon\Carbon::parse($revision->planned_date)->translatedFormat('d F Y') }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1">
                    Kepada Yth.<br>
                    <strong>{{ $revision->recipient_name }}</strong>
                </div>
            </div>
        </div>

        <!-- Body / Konten Naskah Surat -->
        <div class="my-8 space-y-4 text-xs leading-relaxed relative z-20 text-justify">
            @if(isset($revision->content['rendered_body']))
                {!! $revision->content['rendered_body'] !!}
            @else
                <p>Menerangkan dengan sebenarnya bahwa yang bersangkutan terdaftar dalam kegiatan internal yayasan.</p>
            @endif
        </div>

        <!-- Area Penutup & Penanda Tangan (Strictly DRAFT - NO SIGNATURE) -->
        <div class="mt-12 pt-6 flex justify-between items-end relative z-20 text-xs">
            <div class="text-[10px] text-slate-400">
                Dokumen ID: {{ $letter->id }}<br>
                Revisi: v{{ $revision->version }} &bull; Dibuat: {{ \Carbon\Carbon::parse($revision->created_at)->format('d/m/Y H:i') }}<br>
                SHA-256: <span class="font-mono">{{ substr($revision->content_sha256, 0, 24) }}...</span>
            </div>

            <div class="text-center w-64">
                <div class="text-xs font-semibold text-slate-700 mb-2">
                    Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia
                </div>
                <!-- Draf Placeholder (NO REAL SIGNATURE) -->
                <div class="h-20 my-2 border-2 border-dashed border-amber-300 bg-amber-50/50 rounded flex items-center justify-center text-[10px] text-amber-800 font-bold uppercase tracking-wider">
                    [ DRAF — BELUM DITANDATANGANI ]
                </div>
                <div class="font-bold text-slate-800 border-t border-slate-300 pt-1">
                    ( Pejabat Penandatangan Definitif )
                </div>
                <div class="text-[10px] text-slate-500">
                    Mandat & Pengesahan Belum Diterbitkan
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Footer Note -->
    <div class="max-w-3xl w-full mt-4 text-center text-[11px] text-slate-400">
        P4I Office v1.0 &bull; Sistem Administrasi Persuratan Internal Yayasan P4I
    </div>

</body>
</html>
