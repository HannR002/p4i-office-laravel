@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Katalog Templat Surat Dinas</h1>
            <p class="text-xs text-slate-500">Daftar format standar persuratan dan skema formulir naskah Yayasan P4I</p>
        </div>
        <div>
            <span class="text-xs text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-lg shadow-sm">
                Total: <strong>{{ $templates->count() }}</strong> templat
            </span>
        </div>
    </div>

    @if($templates->isEmpty())
        <div class="bg-white p-12 text-center rounded-xl border border-slate-200 shadow-sm text-xs text-slate-400">
            Belum ada templat surat yang terdaftar di organisasi ini.
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($templates as $tmpl)
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <h3 class="font-bold text-sm text-slate-900">{{ $tmpl->title }}</h3>
                                <div class="text-[11px] text-slate-500 font-medium">
                                    {{ $tmpl->letterType->label ?? '-' }} ({{ $tmpl->letterType->code ?? '' }}) &bull; Versi {{ $tmpl->version }}
                                </div>
                            </div>
                            <div>
                                @if($tmpl->is_active)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        AKTIF
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        NONAKTIF
                                    </span>
                                @endif
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 mb-3">
                            {{ $tmpl->description ?? 'Tidak ada deskripsi.' }}
                        </p>

                        <!-- Variabel Schema -->
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 text-[11px] mb-3">
                            <span class="font-semibold text-slate-500 block mb-1">Placeholder Variabel:</span>
                            <div class="flex flex-wrap gap-1">
                                @if(isset($tmpl->form_schema['fields']) && is_array($tmpl->form_schema['fields']))
                                    @foreach($tmpl->form_schema['fields'] as $field)
                                        <code class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-indigo-700 font-mono text-[10px]">
                                            &#123;&#123; {{ $field }} &#125;&#125;
                                        </code>
                                    @endforeach
                                @else
                                    <span class="text-slate-400 italic">Tidak ada variabel khusus.</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Administrator Actions -->
                    <div class="border-t border-slate-100 pt-3 flex items-center justify-between text-xs">
                        <span class="text-[10px] text-slate-400 font-mono truncate max-w-[180px]">
                            ID: {{ $tmpl->id }}
                        </span>
                        @can('update', $tmpl)
                            <form action="{{ route('templates.toggle', $tmpl) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-3 py-1 rounded text-xs font-semibold transition-colors {{ $tmpl->is_active ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200' }}">
                                    {{ $tmpl->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
