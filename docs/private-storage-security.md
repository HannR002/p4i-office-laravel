# KEAMANAN PENYIMPANAN ARSIP DIGITAL PRIVAT
## PRIVATE STORAGE SECURITY ARCHITECTURE & THREAT MITIGATION

**Sistem:** P4I Office v1.0  
**Modul:** Private Digital Archive & Document Storage  
**Penyusun:** Gemini Flash 3.8 — Reasoning High  
**QA Auditor & Software Architect:** GPT-6  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal:** 01 November 2026  
**Status:** **AUDITED & TESTED**

---

## 1. PENDAHULUAN & PRINSIP DASAR

Arsip persuratan digital Yayasan P4I memuat informasi kelembagaan yang memerlukan perlindungan kerahasiaan (*confidentiality*), integritas data (*integrity*), dan ketersediaan (*availability*). Sistem mengadopsi prinsip **Zero Trust File Access**, di mana tidak ada berkas dokumen yang dapat diakses secara langsung melalui protokol HTTP tanpa melalui tahapan autentikasi, otorisasi *multi-tenant*, proteksi klasifikasi naskah, dan verifikasi checksum kriptografis.

---

## 2. TOPOLOGI ISOLASI FISIK DIREKTORI

```
[Web Server Root / Public Web Access]
 │
 ├── public/ (index.php, css, js, build/)  <-- HANYA BERKAS ASSET STATIS
 │    └── (DILARANG KERAS MENYIMPAN ARSIP DOKUMEN DI SINI)
 │
[Internal Application Root / Protected Storage]
 │
 └── storage/
      └── app/
           ├── private_archives/           <-- DISK PRIVAT TERISOLASI
           │    └── {organization_id}/
           │         └── {year}/
           │              ├── PDF/
           │              │    └── {export_uuid}.pdf
           │              └── DOCX/
           │                   └── {export_uuid}.docx
```

### Karakteristik Isolasi:
1. **Di Luar Public Root:** Direktori `storage/app/private_archives` tidak memiliki symlink ke direktori `public/`. Perintah `php artisan storage:link` hanya menghubungkan `storage/app/public`, sehingga direktori `private_archives` tetap terlindungi 100% dari web server (Apache/LiteSpeed/Nginx).
2. **Penamaan Berkas Berbasis UUID Kriptografis:** Nama berkas fisik pada disk menggunakan UUID v4 acak (`01a1942a-...pdf`), bukan nama asli surat. Hal ini mencegah tebakan nama berkas (*file enumeration / predictable resource naming*).
3. **Pemberian Jalur Berstruktur Tenant & Tahun:** Jalur direktori dipisahkan berdasarkan ID organisasi dan tahun pembuatan, mempermudah manajemen kuota dan pemisahan data per entitas.

---

## 3. ANALISIS ANCAMAN & MEKANISME PERTAHANAN (THREAT MODELING)

### 3.1. Ancaman 1: Path Traversal Attack (CWE-22)
- **Skenario Serangan:** Penyerang mengirimkan parameter nama berkas manipulatif seperti `../../../../etc/passwd` atau `..\..\..\config\database.php` pada URL unduh.
- **Mitigasi P4I Office (Gate M4A-10):**
  - Rute pengunduhan berkas **tidak pernah menerima nama berkas atau path fisik** dari input pengguna.
  - Endpoint menggunakan *Route Model Binding* berbasis UUID: `/archives/{export}/download`.
  - Objek `DocumentExport` diambil langsung dari basis data, dan path berkas fisik diambil secara internal dari kolom `file_path` yang terverifikasi.
  - Percobaan manipulasi URL langsung menghasilkan HTTP `404 Not Found`.

### 3.2. Ancaman 2: Cross-Tenant Data Leakage (Kebocoran Lintas Organisasi)
- **Skenario Serangan:** Pengguna sah dari Yayasan Mitra B mencoba mengunduh atau melihat dokumen milik Kantor Pusat Yayasan P4I dengan menebak UUID berkas.
- **Mitigasi P4I Office (Gate M4A-08 & M4A-14):**
  - Pemeriksaan `organization_id` wajib dilakukan pada tingkat Controller dan Service:
    ```php
    if ($export->organization_id !== $user->organization_id) {
        abort(403, 'Akses ditolak: Dokumen bukan milik organisasi pengguna.');
    }
    ```
  - Pada halaman katalog arsip (`/archives`), seluruh query secara otomatis dibatasi pada `organization_id` pengguna yang sedang login (`$query->where('organization_id', $user->organization_id)`).

### 3.3. Ancaman 3: Kebocoran Dokumen Rahasia (Confidentiality Breach)
- **Skenario Serangan:** Staf internal biasa yang tidak terlibat dalam penelaahan dokumen mencoba mengunduh naskah dinas berklasifikasi **RAHASIA / CONFIDENTIAL**.
- **Mitigasi P4I Office (Gate M4A-09):**
  - Dokumen dengan klasifikasi `RAHASIA` atau `CONFIDENTIAL` dilindungi secara khusus:
    ```php
    if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
        $isAuthor = $letter->author_id === $user->id;
        $isReviewerOrAdmin = $user->isSystemAdmin() || $user->hasRole('REVIEWER') || $user->hasRole('PENGURUS_YAYASAN');
        if (! $isAuthor && ! $isReviewerOrAdmin) {
            abort(403, 'Akses ditolak: Dokumen rahasia hanya dapat diakses oleh konseptor dan penelaah.');
        }
    }
    ```
  - Staf biasa yang mencoba mengakses naskah rahasia milik orang lain akan menerima penolakan HTTP `403 Forbidden`.

### 3.4. Ancaman 4: Manipulasi Fisik Berkas / Korupsi Data di Penyimpanan (Tampering)
- **Skenario Serangan:** Berkas dokumen di disk diubah secara liar (misalnya oleh teknisi storage atau akibat kegagalan filesystem), sehingga isinya berbeda dari catatan naskah asli.
- **Mitigasi P4I Office (Gate M4A-11):**
  - Setiap kali proses ekspor selesai, sistem menghitung checksum kriptografis **SHA-256** dari seluruh biner berkas dan menyimpannya ke kolom `file_hash`.
  - Saat ada permintaan unduhan, sistem melakukan verifikasi integritas instan (*on-the-fly*):
    ```php
    $actualSha256 = hash_file('sha256', $disk->path($export->file_path));
    if ($actualSha256 !== $export->file_hash) {
        throw new DomainException('Integritas berkas gagal diverifikasi: Hash SHA-256 tidak cocok dengan metadata sistem.');
    }
    ```
  - Jika berkas mengalami korupsi atau manipulasi, proses streaming dibatalkan dan sistem mengembalikan HTTP `422 Unprocessable Content`.

### 3.5. Ancaman 5: Penyangkalan Aktivitas Kearsipan (Repudiation)
- **Skenario Serangan:** Pengguna mengunduh dokumen penting lalu menyangkal telah mengakses dokumen tersebut.
- **Mitigasi P4I Office (Gate M4A-15):**
  - Setiap aktivitas ekspor dokumen mencatat audit event `DOCUMENT_EXPORTED`.
  - Setiap pengunduhan berkas yang berhasil mencatat audit event `DOCUMENT_DOWNLOADED` secara atomik pada tabel `audit_events` (bersifat murni *append-only* dengan proteksi database trigger).
  - Kolom `download_count` dan `last_downloaded_at` pada tabel `document_exports` dimutasi secara presisi.

---

## 4. KESIMPULAN ARSITEKTUR KEAMANAN

Arsitektur penyimpanan privat P4I Office telah memenuhi prinsip keamanan kearsipan modern:
- Isolasi penyimpanan privat yang aman dari akses publik langsung.
- Penegakan otorisasi bertingkat (Tenant -> Role -> Mandat -> Klasifikasi Rahasia).
- Jaminan keutuhan dokumen melalui validasi SHA-256 bit-per-bit.
- Keterlacakan penuh (*end-to-end traceability*) melalui log audit immutabel.
