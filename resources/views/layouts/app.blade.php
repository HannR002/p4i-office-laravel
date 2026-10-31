<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'P4I Office' }} — Sistem Administrasi Yayasan P4I</title>
    <!-- Tailwind CSS CDN for instant styling in Sprint M0 -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .watermark-banner {
            background-color: #fef3c7;
            border-bottom: 2px dashed #f59e0b;
            color: #92400e;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Safety Guard Watermark Banner -->
    <div class="watermark-banner py-2 px-4 text-center text-xs font-bold tracking-wide">
        ⚠️ DRAF — BELUM RESMI / SPRINT M3A: PENOMORAN SIMULASI, REGISTER HISTORIS & AGENDA PERSURATAN (STATUS RESMI: OFF & BLOCKED)
    </div>

    <!-- Header Navbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-700 text-white flex items-center justify-center font-bold text-sm tracking-tighter">
                    P4I
                </div>
                <div>
                    <a href="{{ route('dashboard') }}" class="font-bold text-slate-900 hover:text-indigo-600 transition-colors">
                        P4I Office <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-2 py-0.5 rounded">v1.0-M3A</span>
                    </a>
                    <div class="text-[11px] text-slate-500 font-medium">Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia</div>
                </div>
            </div>

            @auth
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-bold text-slate-900">{{ Auth::user()->display_name }}</div>
                    <div class="text-[11px] text-slate-500">{{ Auth::user()->email }}</div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors">
                        Keluar
                    </button>
                </form>
            </div>
            @endauth
        </div>
    </header>

    <!-- Main Container with Sidebar -->
    <div class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col md:flex-row gap-6">
        @auth
        <!-- Navigation Sidebar -->
        <aside class="w-full md:w-64 space-y-1">
            <nav class="bg-white p-3 rounded-xl border border-slate-200 space-y-1 text-xs font-medium">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>📊</span>
                    <span>Dashboard Utama</span>
                </a>
                <a href="{{ route('letters.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('letters.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>✉️</span>
                    <span>Surat Dinas (Draf)</span>
                </a>
                <a href="{{ route('templates.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('templates.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>📑</span>
                    <span>Katalog Templat</span>
                </a>
                @if(Auth::user()->hasRole('REVIEWER') || Auth::user()->hasRole('PENGURUS_YAYASAN') || Auth::user()->isSystemAdmin())
                <a href="{{ route('approvals.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('approvals.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>✅</span>
                    <span>Antrean Pemeriksaan</span>
                </a>
                @endif
                <a href="{{ route('mandates.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('mandates.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>📜</span>
                    <span>Mandat Pejabat</span>
                </a>

                <div class="pt-2 pb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    Sprint M3A: Penomoran & Agenda
                </div>

                <a href="{{ route('numbering.policies.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('numbering.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>🔢</span>
                    <span>Kebijakan & Simulator Nomor</span>
                </a>
                <a href="{{ route('legacy.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('legacy.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>🗄️</span>
                    <span>Staging Register Historis</span>
                </a>
                <a href="{{ route('agenda.outgoing') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('agenda.outgoing') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>📤</span>
                    <span>Agenda Surat Keluar</span>
                </a>
                <a href="{{ route('agenda.incoming') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('agenda.incoming*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>📥</span>
                    <span>Agenda Surat Masuk</span>
                </a>
                <a href="{{ route('readiness.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('readiness.*') ? 'bg-rose-50 text-rose-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>🛡️</span>
                    <span>Kesiapan Persuratan (BLOCKED)</span>
                </a>
                <div class="pt-2 pb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    Sprint M4A: Arsip & Ekspor
                </div>

                <a href="{{ route('archives.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('archives.index') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>📦</span>
                    <span>Arsip Digital Dokumen</span>
                </a>

                @if(Auth::user()->isSystemAdmin() || Auth::user()->hasRole('PENGURUS_YAYASAN'))
                <a href="{{ route('archives.admin') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('archives.admin*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>🛡️</span>
                    <span>Pengawasan Integritas Arsip</span>
                </a>
                @endif
            </nav>

            <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-[11px] leading-relaxed">
                <strong>PENGAMAN PERSURATAN:</strong> Nomor resmi dinonaktifkan (OFF). Dokumen ekspor PDF/DOCX berstatus DRAF SIMULASI dengan watermark permanen, isolasi penyimpanan privat, dan verifikasi checksum SHA-256.
            </div>
        </aside>
        @endauth

        <!-- Content Area -->
        <main class="flex-1">
            @if(session('status') || session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-medium">
                    {{ session('status') ?? session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg text-xs font-medium space-y-1">
                    <div class="font-bold">Terjadi Kesalahan:</div>
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs text-slate-500">
            P4I Office v1.0 — Sprint M3A (Fondasi Penomoran & Agenda) &copy; 2026 Yayasan P4I. Semua hak dilindungi.
        </div>
    </footer>

</body>
</html>
