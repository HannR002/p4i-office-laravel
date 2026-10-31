# ANALISIS CELAH KEBIJAKAN PENOMORAN PERSURATAN (NUMBERING POLICY GAP)
## P4I Office v1.0 — Kajian Format Historis vs Standar Baku Surat Keputusan Pengurus Yayasan

- **Tanggal Dokumen:** 01 November 2026
- **Penyusun Analisis:** Gemini Flash 3.8 — Reasoning High (Lead Software Engineer)
- **Tujuan Dokumen:** Bahan Telaah Arsitektur untuk Tim Auditor GPT-6 dan Pengesahan SK Dewan Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel` (Sinkronisasi: `D:\p4i-office`)

---

## 1. LATAR BELAKANG & TEMUAN FORMAT HISTORIS

Pada audit naskah dan templat persuratan fisik lama Yayasan P4I (periode 2023–2025), ditemukan pola umum penomoran:
$$\text{[Nomor Urut]} / \text{P4I} / \text{[Bulan Romawi]} / \text{[Tahun]}$$
Contoh konkret yang tercatat pada arsip fisik:
- `001/P4I/I/2024`
- `015/P4I/V/2024`
- `104/P4I/XII/2024`

Meskipun pola di atas telah digunakan secara *de facto* dalam praktik administrasi harian sekretariat, pola tersebut **bukan merupakan regulasi yang disahkan melalui Surat Keputusan (SK) Standar Operasional Prosedur (SOP) Definitif**. Mengimplementasikan pola ini langsung ke produksi tanpa penelaahan formal berpotensi menimbulkan kerancuan hukum, konflik pengarsipan, dan ketidaksesuaian tata kelola.

---

## 2. INVENTARISASI CELAH KEBIJAKAN (GAP ANALYSIS)

Berikut adalah 6 celah utama yang diidentifikasi antara pola historis dengan kebutuhan sistem enterprise P4I Office:

### Celah 1: Ketiadaan Kode Klasifikasi Jenis Surat (Letter Type Code)
- **Kondisi Historis:** Seluruh jenis surat (Surat Keputusan Pengurus, Surat Tugas Lapangan, Surat Keterangan Magang, Surat Undangan, dan Perjanjian Kerjasama) dicampur dalam satu penomoran umum dengan kode `/P4I/`.
- **Dampak Risiko:** Sulit membedakan kekuatan hukum naskah. Sebuah surat tugas dinas biasa bisa memiliki nomor yang rancu dengan Surat Keputusan Pengurus Yayasan.
- **Rekomendasi Solusi:** Menyisipkan kode klasifikasi naskah resmi pada format penomoran:
  - Surat Keputusan: `{seq:03}/P4I-SK/{month_roman}/{year}`
  - Surat Tugas: `{seq:03}/P4I-ST/{month_roman}/{year}`
  - Surat Keterangan: `{seq:03}/P4I-KET/{month_roman}/{year}`
  - Surat Permohonan / Dinas Biasa: `{seq:03}/P4I-SRT/{month_roman}/{year}`

### Celah 2: Lingkup dan Periode Reset Counter (Reset Scope & Period)
- **Kondisi Historis:** Tidak ada aturan tertulis kapan nomor urut kembali ke `001`. Pada berkas tahun 2023–2024, nomor kembali ke `001` setiap tanggal 1 Januari (tahunan). Namun pada beberapa unit kerja, counter diteruskan tanpa reset tahunan.
- **Dampak Risiko:** Potensi nomor ganda jika pergantian tahun tidak didefinisikan secara atomik dalam basis data.
- **Rekomendasi Solusi:** Menetapkan aturan baku dalam SK Pengurus: Counter di-reset setiap tanggal 1 Januari pukul 00:00:00 (YEARLY RESET) per seri klasifikasi naskah.

### Celah 3: Standar Digit Padding Nomor Urut
- **Kondisi Historis:** Ditemukan format tidak konsisten antara 2 digit (`15/P4I/...`), 3 digit (`015/P4I/...`), hingga 4 digit (`0015/P4I/...`).
- **Dampak Risiko:** Kesulitan pengurutan leksikografis (*string sorting*) pada basis data dan pencarian arsip digital.
- **Rekomendasi Solusi:** Menetapkan standar padding **3 digit** untuk volume surat yayasan di bawah 1.000 surat per tahun (misal: `001` s.d. `999`), atau **4 digit** jika volume tahunan diproyeksikan melebihi 1.000 naskah.

### Celah 4: Penanganan Pembatalan Surat & Celah Urutan (Voided Letter & Sequence Gap)
- **Kondisi Historis:** Pada pencatatan buku fisik, jika surat dibatalkan setelah nomor dialokasikan, nomor tersebut terkadang dicoret dan dipakai kembali untuk surat lain.
- **Dampak Risiko:** **Sangat Berbahaya**. Menggunakan ulang nomor yang pernah beredar (*reuse allocated number*) melanggar prinsip non-repudiasi (*non-repudiation*) dan integritas audit trail ISO 15489 (Records Management).
- **Rekomendasi Solusi:** Menetapkan aturan **Strict Monotonic - No Reuse**: Sekali nomor dialokasikan ke suatu surat dinas, nomor tersebut tidak boleh digunakan ulang meskipun surat dibatalkan. Surat yang batal wajib berstatus `CANCELLED` dan tercatat pada Buku Agenda dengan keterangan pembatalan resmi.

### Celah 5: Pemisahan Nomor Surat Eksternal Masuk vs Agenda Internal
- **Kondisi Historis:** Surat masuk dari pihak luar (kementerian, universitas, mitra) sering kali diregistrasikan dengan mencampuradukkan nomor pengirim dan nomor buku agenda.
- **Dampak Risiko:** Hilangnya identifikasi nomor surat asli yang sah dari instansi pengirim saat melakukan korespondensi balasan.
- **Rekomendasi Solusi:** (Telah diimplementasikan pada Sprint M3A, NR-12): Nomor surat asli dari pihak luar disimpan apa adanya pada kolom `sender_letter_number`, sedangkan yayasan menerbitkan nomor agenda internal terpisah dengan format `AG-IN-YYYY-XXXX`.

### Celah 6: Hierarki Otoritas Penandatanganan (Signatory Authority)
- **Kondisi Historis:** Tidak tercatat secara eksplisit siapa saja pejabat yayasan yang berwenang menandatangani jenis surat tertentu.
- **Dampak Risiko:** Tanda tangan dapat dipersengketakan secara legal bila penandatangan tidak memegang mandat sah pada tanggal penerbitan surat.
- **Rekomendasi Solusi:** Integrasi tabel `signatory_mandates` dengan format penomoran, di mana sistem secara otomatis memvalidasi apakah penandatangan memiliki mandat aktif ber-SK saat nomor diterbitkan.

---

## 3. DRAF PROPOSAL SURAT KEPUTUSAN DEWAN PENGURUS YAYASAN P4I

Untuk memfasilitasi Dewan Pengurus Yayasan P4I dalam mengesahkan kebijakan sebelum dibukanya Sprint M3B, berikut dirumuskan naskah draf keputusan:

```
SURAT KEPUTUSAN DEWAN PENGURUS YAYASAN P4I
NOMOR: 01/SK-P4I/XI/2026
TENTANG
STANDAR OPERASIONAL PROSEDUR PENOMORAN DAN TATA KELOLA PERSURATAN DINAS
YAYASAN PUSAT PENDIDIKAN DAN PENELITIAN PEMBANGUNAN INDONESIA (P4I)

Menimbang:
a. Bahwa tertib administrasi dan legalitas persuratan dinas merupakan pilar tata kelola organisasi yang berintegritas;
b. Bahwa sistem otomasi persuratan P4I Office v1.0 memerlukan standarisasi format penomoran baku;

Memutuskan:
Menetapkan:
Pasal 1: Format Penomoran Surat Resmi Yayasan P4I
Format baku penomoran surat resmi keluar menggunakan formula:
[Nomor Urut 3 Digit] / [Kode Jenis Surat] / [Bulan Romawi] / [Tahun Penerbitan]

Pasal 2: Klasifikasi Kode Surat
(1) Surat Keputusan Pengurus: P4I-SK
(2) Surat Tugas Peneliti / Pelaksana: P4I-ST
(3) Surat Keterangan Resmi: P4I-KET
(4) Surat Dinas Korespondensi Umum: P4I-SRT

Pasal 3: Periode Counter & Larangan Penggunaan Ulang
(1) Nomor urut di-reset kembali ke angka 001 pada setiap tanggal 1 Januari pukul 00:00:00.
(2) Nomor yang telah dialokasikan tidak dapat digunakan kembali meskipun naskah surat dibatalkan.
```

---

## 4. KESIMPULAN & REKOMENDASI UNTUK AUDITOR GPT-6

1. Logika teknis penomoran yang dibangun pada Sprint M3A (`NumberingService`) telah dirancang bersifat modular dan dinamis, sehingga siap mengakomodasi format di atas tanpa perubahan skema.
2. Status modul penomoran resmi pada aplikasi Laravel saat ini **tetap dipertahankan OFF (`FEATURE_NUMBERING_ACTIVE = false`) dan `letters.official_number = NULL`** hingga SK Pengurus Yayasan di atas disahkan secara administratif.
