@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                    STATUS: SIMULASI / DRAFT ONLY
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-rose-100 text-rose-800 border border-rose-300">
                    NOMOR RESMI: OFF
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 mt-2">Kebijakan Penomoran Surat & Simulator Alokasi</h1>
            <p class="text-xs text-slate-500 mt-1">
                Fondasi aturan penomoran format dinamis (Sprint M3A). Seluruh nomor yang dihasilkan bertanda <strong class="text-amber-700 font-mono">[SIMULASI]</strong> dan tidak dialokasikan ke surat resmi yayasan.
            </p>
        </div>
    </div>

    <!-- Playground Simulator -->
    <div class="bg-gradient-to-r from-indigo-900 to-slate-900 text-white p-6 rounded-2xl shadow-md">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold tracking-wide uppercase text-indigo-200">🧪 Simulator Format & Counter Konkurensi</h2>
            <span class="text-xs bg-indigo-800 text-indigo-200 px-3 py-1 rounded-full border border-indigo-700">
                Uji Atomik MariaDB InnoDB
            </span>
        </div>
        <p class="text-xs text-slate-300 mb-6 leading-relaxed">
            Pilih kebijakan aktif untuk menguji evaluasi token dinamis seperti <code class="text-amber-300">{seq:03}</code>, <code class="text-amber-300">{code}</code>, <code class="text-amber-300">{month_roman}</code>, dan <code class="text-amber-300">{year}</code>.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($policies as $p)
            <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-bold text-white">{{ $p->series_key }}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            {{ $p->status }} (Non-Aktif)
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-400 font-mono mb-2">Pola: {{ $p->format_pattern }}</div>
                    <div class="text-[11px] text-slate-400">Kode: <strong class="text-slate-200">{{ $p->code }}</strong> | Reset: <strong class="text-slate-200">{{ $p->reset_period }}</strong></div>
                    
                    <div class="mt-4 p-2.5 rounded-lg bg-slate-900 border border-slate-700 text-center">
                        <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Pratinjau String Format:</div>
                        <div class="text-xs font-mono font-bold text-emerald-400 tracking-wide break-all">
                            {{ $previews[$p->id] ?? '-' }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-700/60 flex items-center justify-between text-[11px] text-slate-400">
                    <span>Counter: <strong class="text-white">{{ $p->counters->first()->last_number ?? 0 }}</strong></span>
                    <span>Simulasi: <strong class="text-white">{{ $p->simulations->count() }}</strong></span>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Tabel Daftar Kebijakan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Daftar Rancangan Kebijakan Penomoran (Policies)</h2>
            <span class="text-xs text-slate-500">Total: {{ $policies->count() }} Format</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Seri / Klasifikasi</th>
                        <th class="p-3.5">Kode Singkatan</th>
                        <th class="p-3.5">Pola Format Token</th>
                        <th class="p-3.5">Periode Reset</th>
                        <th class="p-3.5">Padding</th>
                        <th class="p-3.5">Status Penomoran</th>
                        <th class="p-3.5">Contoh Output Simulasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($policies as $policy)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3.5 font-bold text-slate-900">{{ $policy->series_key }}</td>
                        <td class="p-3.5 font-mono">{{ $policy->code }}</td>
                        <td class="p-3.5 font-mono text-indigo-700">{{ $policy->format_pattern }}</td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">
                                {{ $policy->reset_period }}
                            </span>
                        </td>
                        <td class="p-3.5">{{ $policy->padding }} digit</td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                {{ $policy->status }} (NON-RESMI)
                            </span>
                        </td>
                        <td class="p-3.5">
                            <code class="text-xs font-mono font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-200">
                                {{ $previews[$policy->id] ?? '-' }}
                            </code>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">
                            Belum ada rancangan kebijakan penomoran surat yang dikonfigurasikan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form Tambah Kebijakan DRAFT (Admin / Pengurus Only) -->
    @if(Auth::user()->isSystemAdmin() || Auth::user()->hasRole('PENGURUS_YAYASAN'))
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 mb-1">Tambah Draf Kebijakan Penomoran Baru (NR-01)</h3>
        <p class="text-xs text-slate-500 mb-5">
            Kebijakan baru akan otomatis tersimpan dalam status <strong class="text-amber-800">DRAFT (is_active = false)</strong> hingga disahkan secara administratif melalui SK Pengurus Yayasan P4I.
        </p>

        <form action="{{ route('numbering.policies.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kunci Seri (Series Key) *</label>
                <input type="text" name="series_key" required placeholder="Contoh: SK_PENGURUS" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 uppercase font-mono focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kode Surat (Code) *</label>
                <input type="text" name="code" required placeholder="Contoh: P4I-SK" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 uppercase font-mono focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">Pola Format (Format Pattern) *</label>
                <input type="text" name="format_pattern" required value="{seq:03}/{code}/{month_roman}/{year}" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 font-mono focus:ring-2 focus:ring-indigo-500">
                <p class="text-[11px] text-slate-400 mt-1">Placeholder tersedia: {seq:03}, {seq}, {code}, {month_roman}, {year}, {month}</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Periode Reset Counter *</label>
                <select name="reset_period" class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500">
                    <option value="YEAR">Tahunan (YEAR - Tiap Pergantian Tahun)</option>
                    <option value="MONTH">Bulanan (MONTH - Tiap Pergantian Bulan)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Padding Digit Nomor *</label>
                <input type="number" name="padding" min="1" max="8" value="3" required class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi & Peruntukan</label>
                <textarea name="description" rows="2" placeholder="Catatan peruntukan format penomoran ini..." class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="bg-indigo-700 text-white text-xs font-bold px-4 py-2.5 rounded-lg hover:bg-indigo-800 transition-colors shadow-sm">
                    Simpan Draf Kebijakan (Status DRAFT)
                </button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection
