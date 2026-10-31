# LEMBAR KERJA KEPUTUSAN SOP PENOMORAN PERSURATAN
## P4I Office v1.0: Matriks Pengambilan Keputusan Dewan Pengurus Yayasan P4I

**Disiapkan oleh:** Tim Rekayasa Perangkat Lunak (Gemini IDE)  
**Untuk:** Tim Perancangan & Auditor Teknis (GPT-6) dan Dewan Pengurus Yayasan P4I  
**Tanggal:** 1 November 2026  
**Status Dokumen:** **DRAF REKOMENDASI UNTUK SIDANG PLENO PENGURUS**

---

## 1. TUJUAN LEMBAR KERJA

Dokumen ini disusun sebagai panduan yuridis dan teknis bagi **Ketua dan Dewan Pengurus Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia (Yayasan P4I)** guna mengesahkan Standar Operasional Prosedur (SOP) Tata Naskah Dinas dan Format Penomoran Surat Resmi.

Sebelum dokumen Surat Keputusan (SK) ini disahkan secara sah, sistem P4I Office v1.0 berada pada status **`BLOCKED`** dan dilarang menerbitkan nomor resmi apapun.

---

## 2. MATRIKS PILIHAN KEBIJAKAN ADMINISTRATIF

Dewan Pengurus dimohon untuk menetapkan pilihan resmi dari tabel opsi di bawah ini:

### 2.1 Format Pola Penomoran Surat Resmi Keluar
| Parameter | Opsi A (Standar Nasional Lembaga) | Opsi B (Format Ringkas) | Rekomendasi Teknis |
| :--- | :--- | :--- | :--- |
| **Pola Format** | `{SEQ:3}/P4I/{SERIES}/{MONTH_ROMAN}/{YEAR}` | `{SEQ:4}/Y-P4I/{YEAR}` | **Opsi A**: Standar korespondensi resmi kementerian/lembaga mitra P4I (Bappenas, BRIN, Pemda). |
| **Contoh Hasil** | `001/P4I/SRT/XI/2026` | `0001/Y-P4I/2026` | Format Opsi A memberikan informasi jenis surat dan bulan terbit secara eksplisit. |
| **Digit Sequence** | 3 digit (`001`, `002`, ...) | 4 digit (`0001`, `0002`, ...) | 3 digit memadai untuk estimasi 200–500 surat/tahun. |
| **Pilihan Pengurus** | [ ] **DISETUJUI** | [ ] **DISETUJUI** | Catatan: ................................................ |

---

### 2.2 Siklus Reset Nomor Urut (Reset Period)
| Parameter | Opsi 1 (Tahunan / Annual) | Opsi 2 (Bulanan / Monthly) | Rekomendasi Teknis |
| :--- | :--- | :--- | :--- |
| **Mekanisme Reset** | Nomor urut kembali ke `1` setiap 1 Januari. | Nomor urut kembali ke `1` setiap awal bulan. | **Opsi 1**: Sesuai kelaziman yayasan di Indonesia; mempermudah audit akuntansi dan kearsipan tahunan. |
| **Contoh Dampak** | 31 Des: `245/P4I/SRT/XII/2026`<br>01 Jan: `001/P4I/SRT/I/2027` | 31 Jan: `042/P4I/SRT/I/2026`<br>01 Feb: `001/P4I/SRT/II/2026` | Reset tahunan menghindarkan kerancuan nomor yang sama dalam satu tahun kalender. |
| **Pilihan Pengurus** | [ ] **DISETUJUI** | [ ] **DISETUJUI** | Catatan: ................................................ |

---

### 2.3 Standarisasi Kode Klasifikasi / Seri Surat
| Kategori Surat | Usulan Kode Seri | Keterangan Dokumen | Kewenangan Penandatangan |
| :--- | :---: | :--- | :--- |
| **Surat Keluar Umum** | `SRT` (atau `EXT`) | Komunikasi eksternal ke kementerian, universitas, mitra riset | Ketua Yayasan / Direktur Eksekutif |
| **Surat Tugas** | `ST` | Penugasan penelitian lapangan, seminar, rapat koordinasi | Direktur Riset / Sekretaris Utama |
| **Surat Keputusan** | `SK` | Penetapan struktur organisasi, personil, anggaran riset | Rapat Pleno Dewan Pengurus Yayasan |
| **Perjanjian / MoU** | `PKS` | Perjanjian kerjasama antar-lembaga / universitas | Ketua Yayasan bersama Mitra |
| **Surat Keterangan** | `KET` | Keterangan kerja, surat rekomendasi peneliti | Sekretaris Utama |

---

### 2.4 Kebijakan Format Buku Agenda Ganda (BR-10)
| Jenis Buku Agenda | Usulan Prefix | Pola Agenda | Siklus Reset | Status Saat Ini |
| :--- | :---: | :--- | :---: | :---: |
| **Buku Agenda Keluar** | `AG-OUT` | `{PREFIX}/{YEAR}/{SEQ:4}` | `YEAR` | **DRAFT** (Menunggu SK Pengurus) |
| **Buku Agenda Masuk** | `AG-IN` | `{PREFIX}/{YEAR}/{SEQ:4}` | `YEAR` | **DRAFT** (Menunggu SK Pengurus) |

*Catatan: Nomor internal buku agenda bersifat independen dari nomor surat asli pengirim.*

---

## 3. RANCANGAN NASKAH SURAT KEPUTUSAN DEWAN PENGURUS (DRAFT SK)

Berikut adalah draf Surat Keputusan yang disiapkan untuk ditandatangani oleh Ketua Yayasan P4I:

### DRAF 1: KEPUTUSAN PENGURUS TENTANG SOP TATA NASKAH & PENOMORAN
```
                       SURAT KEPUTUSAN DEWAN PENGURUS
       YAYASAN PUSAT PENDIDIKAN DAN PENELITIAN PEMBANGUNAN INDONESIA (P4I)
                     NOMOR: 01/SK-P4I/XI/2026
                                  TENTANG
           STANDAR OPERASIONAL PROSEDUR TATA NASKAH DINAS DAN
              SISTEM PENOMORAN SURAT ELEKTRONIK P4I OFFICE

Menimbang:
a. Bahwa dalam rangka mewujudkan tertib administrasi, akuntabilitas, dan keteraturan tata kelola persuratan di lingkungan Yayasan P4I;
b. Bahwa implementasi aplikasi persuratan digital P4I Office v1.0 memerlukan landasan operasional resmi terkait format nomor, siklus registrasi, dan buku agenda;
c. Bahwa berdasarkan pertimbangan sebagaimana dimaksud pada huruf a dan b, perlu menetapkan Surat Keputusan Pengurus Yayasan P4I.

Mengingat:
1. Akta Pendirian Yayasan P4I dan Anggaran Dasar Yayasan;
2. Hasil Rapat Pleno Pengurus Yayasan P4I tanggal 1 November 2026.

MEMUTUSKAN:
Menetapkan:
KESATU   : Memberlakukan format resmi penomoran surat keluar Yayasan P4I dengan pola:
           {SEQ:3}/P4I/{SERIES}/{MONTH_ROMAN}/{YEAR}
KEDUA    : Menetapkan bahwa nomor urut surat resmi direset kembali ke angka 1 setiap tanggal 1 Januari (siklus reset tahunan / YEAR).
KETIGA   : Menetapkan pemisahan nomor buku agenda internal dengan format AG-OUT/{YEAR}/{SEQ:4} untuk surat keluar dan AG-IN/{YEAR}/{SEQ:4} untuk surat masuk.
KEEMPAT  : Menginstruksikan Administrator Sistem untuk mempertahankan penonaktifan penomoran resmi sampai dengan Berita Acara Rekonsiliasi Register Historis disahkan.
KELIMA   : Keputusan ini berlaku sejak tanggal ditetapkan.

Ditetapkan di : Jakarta
Pada tanggal  : 1 November 2026

DEWAN PENGURUS YAYASAN P4I
Ketua Yayasan,



Drs. H. Mulyadi, M.M.
```

---

### DRAF 2: KEPUTUSAN PENGURUS TENTANG MANDAT PEJABAT PENANDATANGAN
```
                       SURAT KEPUTUSAN DEWAN PENGURUS
       YAYASAN PUSAT PENDIDIKAN DAN PENELITIAN PEMBANGUNAN INDONESIA (P4I)
                     NOMOR: 02/SK-P4I/XI/2026
                                  TENTANG
          PENETAPAN PEJABAT DAN MANDAT PENELAAHAN PERSURATAN
                            P4I OFFICE v1.0

MEMUTUSKAN:
Menetapkan:
KESATU   : Memberikan mandat penelaahan (Reviewer) dan persetujuan naskah dinas digital kepada:
           1. Drs. H. Mulyadi, M.M. (Ketua Yayasan) — Otoritas Penuh Naskah Eksternal & SK.
           2. Dr. Penelaah Riset, M.Si. (Direktur Riset) — Otoritas Naskah Bidang Akademik & Surat Tugas Riset.
KEDUA    : Menegaskan prinsip Segregation of Duties (SoD) di mana staf konseptor dilarang menyetujui surat buatannya sendiri (self-approval prohibited).
KETIGA   : Keputusan ini berlaku sejak tanggal ditetapkan.
```

---

## 4. TAHAPAN TINDAK LANJUT ADMINISTRATIF

1. **Rapat Pleno Pengurus:** Pembahasan dan penandatanganan SK No. 01/SK-P4I/XI/2026 dan SK No. 02/SK-P4I/XI/2026 oleh Dewan Pengurus.
2. **Penyampaian SK ke Auditor GPT-6:** Menyerahkan salinan SK yang telah ditandatangani sebagai bukti penyelesaian Pilar 1, 3, dan 4.
3. **Pemberian Wewenang Aktivasi Teknis:** Setelah seluruh pilar disahkan, Pengurus Yayasan menerbitkan Instruksi Kerja Teknis Aktivasi untuk Sprint M3C mendatang.
