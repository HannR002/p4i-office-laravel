<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — P4I Office</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="text-center space-y-2">
            <div class="w-12 h-12 bg-indigo-700 text-white rounded-xl mx-auto flex items-center justify-center font-bold text-lg">
                P4I
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">P4I Office</h1>
            <p class="text-xs text-slate-500">Sistem Administrasi dan Persuratan Internal Yayasan P4I</p>
        </div>

        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-amber-900 text-[11px] leading-relaxed">
            <strong>Sprint M0:</strong> Fondasi arsitektur Laravel 10 LTS untuk Hostinger Business Web Hosting & MariaDB.
        </div>

        @if($errors->any())
            <div class="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">Email Pengguna</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="{{ old('email') }}" 
                    required 
                    autofocus
                    placeholder="nama@p4i.local" 
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    placeholder="••••••••" 
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-slate-600">Ingat sesi saya</span>
                </label>
            </div>

            <button 
                type="submit" 
                class="w-full py-2.5 px-4 bg-indigo-700 hover:bg-indigo-800 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors"
            >
                Masuk ke Sistem
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 text-center text-[11px] text-slate-400">
            Akses terbatas hanya untuk staf, reviewer, dan pengurus terdaftar Yayasan P4I.
        </div>
    </div>

</body>
</html>
