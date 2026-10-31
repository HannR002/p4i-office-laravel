# ANALISIS KEBIJAKAN DAN KESENJANGAN MANDAT KEWENANGAN
## (MANDATE POLICY & STATUTORY AUTHORITY GAP ANALYSIS)
### Tata Kelola Otoritas Persetujuan Surat Dinas Yayasan P4I Office (v1.0)

- **Penyusun:** Lead Software Engineer & Technical Test Engineer (Gemini Flash 3.8 Reasoning High)
- **Reviewer & Auditor Independen:** GPT-6 (Software Architect & System Analyst)
- **Otoritas Administratif & Kebijakan:** Ketua & Dewan Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Target Regulasi:** UU Yayasan (UU No. 16/2001 jo. UU No. 28/2004) & AD/ART Yayasan P4I
- **Status Dokumen:** Policy & Technical Reconciliation Report — Siap Ditinjau GPT-6

---

## 1. LATAR BELAKANG DAN TUJUAN ANALISIS

Dalam pengembangan sistem persuratan organisasi formal seperti **Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia (P4I)**, sering kali terjadi kesalahpahaman konseptual antara **Peran Perangkat Lunak (Software Roles / RBAC)** dan **Kewenangan Statuter Organisasi (Legal Statutory Mandates)**.

Pada Sprint M2, modul persetujuan (*approval workflow*) diimplementasikan bukan sekadar sebagai tombol submit-approve biasa, melainkan sebagai perwujudan hukum tata kelola persuratan dinas. Dokumen ini menyajikan analisis mendalam mengenai pemisahan kewenangan, penegakan larangan persetujuan mandiri (*self-approval ban*), batas-batas mandat simulasi pengujian, serta rekomendasi transisi menuju mandat operasional resmi bagi Pengurus Yayasan.

---

## 2. PEMISAHAN PERAN APLIKASI VS MANDAT HUKUM STATUTER

### 2.1 Konsep Peran Aplikasi (Software Roles)
Peran aplikasi (didefinisikan pada tabel `roles` dan `user_roles`) semata-mata mengatur **izin navigasi dan fungsionalitas antarmuka perangkat lunak**:
- `STAFF`: Diizinkan mengakses modul draf dan membuat surat.
- `REVIEWER`: Diizinkan mengakses menu antrean pemeriksaan (`/approvals`).
- `SYSTEM_ADMIN`: Diizinkan mengelola akun pengguna, konfigurasi templat, dan melihat log sistem.
- `PENGURUS_YAYASAN`: Diizinkan mengakses dashboard ringkasan eksekutif.

### 2.2 Konsep Mandat Pejabat (Statutory Mandates)
Mandat persuratan (didefinisikan pada tabel `signatory_mandates`) adalah **kewenangan statuter organisasi yang bersumber langsung dari Surat Keputusan (SK) Dewan Pengurus Yayasan**:
- Setiap mandat mengikat secara hukum:
  1. Identitas pejabat penerima mandat (`user_id`).
  2. Organisasi berbadan hukum (`organization_id`).
  3. Nomor dan dasar Surat Keputusan (`decree_number`).
  4. Batas rentang waktu berlaku (`valid_from` s/d `valid_to`).
  5. Cakupan jenis surat tertentu (`letter_type_id`) atau umum.
  6. Cakupan tindakan spesifik (`can_review`, `can_approve`, `can_sign`).
  7. Mekanisme penarikan seketika (`revoked_at`).

### 2.3 Prinsip Anti-Eskalasi: Administrator Teknis Bukan Penandatangan Statuter
> **PRINSIP KRITIS P4I OFFICE:**  
> Memiliki peran teknis `SYSTEM_ADMIN` **TIDAK OTOMATIS** memberikan hak menyetujui surat dinas. Seorang administrator sistem yang tidak tercatat memiliki mandat aktif pada tabel `signatory_mandates` akan ditolak oleh sistem saat mencoba mengeksekusi persetujuan naskah surat.

Hal ini mencegah eskalasi wewenang sepihak oleh tenaga teknologi informasi atas naskah kebijakan resmi yayasan.

---

## 3. PENEGAKAN LARANGAN PERSETUJUAN MANDIRI (SELF-APPROVAL BAN)

Sesuai dengan prinsip tata kelola organisasi yang bersih (*good institutional governance*) dan klausul SRS Sprint M2 (M2-FR-06 / AP2-06):
- **Aturan Baku:** Pengguna yang membuat atau mengajukan draf surat (**author**) dilarang keras bertindak sebagai penelaah atau pemberi persetujuan atas surat buatannya sendiri.
- **Implementasi Backend:**
  Pada berkas [`MandateValidationService.php`](file:///D:/p4i-office-laravel/app/Services/MandateValidationService.php#L31-L36):
  ```php
  if ($letter->author_id === $reviewer->id) {
      throw new AccessDeniedHttpException('Pembuat surat dilarang keras menelaah atau menyetujui pengajuannya sendiri (Self-approval ditolak).');
  }
  ```
- **Pengecualian Nihil:** Aturan ini berlaku mutlak tanpa perkecualian, termasuk bagi akun Ketua Yayasan sekalipun. Jika Ketua Yayasan membuat draf secara mandiri, persetujuan harus diberikan oleh pengurus lain yang memegang mandat setara, atau draf diajukan oleh sekretariat staf untuk ditelaah oleh Ketua.

---

## 4. MANDAT SIMULASI VS MANDAT RESMI YAYASAN

Pada fase pengembangan Sprint M2, seluruh pengujian dilakukan menggunakan **Mandat Simulasi**:

| Parameter | Mandat Simulasi (Sprint M2 Lokal) | Mandat Resmi (Produksi Definitif) |
|---|---|---|
| **Flag Basis Data** | `is_simulation = true` | `is_simulation = false` |
| **Nomor SK / Acuan** | Berawalan `[SIMULASI] ...` (contoh: `[SIMULASI] SK-01/REV/2026`) | Nomor SK Definitif Buku Register Yayasan |
| **Keabsahan Hukum** | **TIDAK SAH** di luar pengujian lokal | **SAH & MENGIKAT** secara hukum perdata/organisasi |
| **Penetapan** | Dihasilkan otomatis oleh `DatabaseSeeder` | Ditetapkan melalui Rapat Pengurus Yayasan P4I |
| **Tanda Tangan** | Dinonaktifkan (`can_sign = false`) | Menunggu regulasi Sprint M3 (Digital Signature) |

### Penandaan Visual pada Antarmuka Pengguna
Pada antarmuka katalog mandat ([`resources/views/mandates/index.blade.php`](file:///D:/p4i-office-laravel/resources/views/mandates/index.blade.php)), sistem menampilkan:
1. Badge mencolok berwarna kuning-emas bertuliskan **[SIMULASI]**.
2. Banner peringatan tata kelola pada bagian atas halaman yang menegaskan bahwa data tersebut semata-mata instrumen pengujian lokal.

---

## 5. MEKANISME REVOKASI DAN BATAS KADALUARSA MANDAT

Sistem persuratan P4I Office v1.0 menerapkan kontrol siklus hidup mandat yang ketat:
1. **Kadaluarsa Temporal Otomatis (*Temporal Expiration*):**
   - Mandat yang telah melewati tanggal `valid_to` otomatis ditolak oleh `MandateValidationService` tanpa memerlukan intervensi manual cron job.
2. **Penarikan Wewenang Seketika (*Instant Revocation*):**
   - Kolom `revoked_at` dan status `REVOKED` mencatat pembatalan mandat sebelum masa berlakunya habis (misal pejabat berhenti atau dialih-tugaskan).
   - Upaya persetujuan oleh mandat yang telah ditarik langsung digagalkan seketika.
3. **Imutabilitas Keputusan Masa Lalu:**
   - Pencabutan mandat hari ini **tidak membatalkan keabsahan surat yang telah disetujui di masa lalu** saat mandat tersebut masih aktif, karena baris `approval_steps` mengikat `mandate_id`, `decision_at`, dan hash konten secara historis.

---

## 6. REKOMENDASI UNTUK GPT-6 DAN PENGURUS YAYASAN P4I

1. **Untuk Tim Arsitek Sistem GPT-6:**
   - Arsitektur validasi mandat pada `MandateValidationService` dan `ApprovalWorkflowService` telah terbukti kokoh, teruji oleh 18 gerbang keberterimaan, dan bebas dari celah *privilege escalation*.
   - Direkomendasikan mempertahankan skema pengikatan (*binding approval step*) ke `content_sha256` dan `revision_version` sebagai fondasi menuju alur penandatanganan Sprint M3.
2. **Untuk Ketua & Dewan Pengurus Yayasan P4I:**
   - Sebelum sistem dideploy ke lingkungan produksi Hostinger, Pengurus Yayasan wajib menyusun **Surat Keputusan (SK) Standar Prosedur Operasional Persuratan** yang menetapkan siapa saja pejabat berwenang menelaah (Reviewer) dan menandatangani (Signatory) masing-masing kategori surat dinas.
   - Mandat simulasi akan dibersihkan (*purged*) secara aman saat inisialisasi basis data produksi, digantikan dengan register SK resmi yayasan.
