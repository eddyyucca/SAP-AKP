# SAP - Safety Accountability Program

Modul AKPSatu untuk **pelaporan dan pemantauan keselamatan kerja** PT Adhi Kartiko Pratama Tbk: laporan insiden, hazard, inspeksi rutin,
tindakan korektif, serta dashboard dan laporan (LTIFR, TRIR, peta risiko, kepatuhan inspeksi).

Stack: Laravel 12, PHP 8.3, MySQL/MariaDB, Bootstrap 5 (tema HRIS), Chart.js 4.4.3, login lewat AKPSatu (`akp/portal-sso`).
Karyawan disinkronkan dari **HRIS** (API baca-saja) dan notifikasi dikirim lewat **AKP Mail**.

## Fitur

| Modul | Isi |
|---|---|
| **Dashboard** | Hari tanpa LTI, LTIFR/TRIR/Severity Rate, tren per bulan, jenis & keparahan, peta panas risiko 5x5, per lokasi/departemen, akar masalah, status tindakan, pelapor teraktif. Pelapor biasa melihat ringkasan pribadi. |
| **Laporan Insiden** | 10 jenis (Fatal, LTI, RWC, MTC, P3K, kebakaran, kendaraan, properti, lingkungan, near miss), korban/saksi (cari dari HRIS), potensi risiko, foto/dokumen, investigasi 5-Why + akar masalah, alur Dilaporkan → Investigasi → Tindak lanjut → Ditutup, cetak A4. |
| **Jenis kejadian** | Mengikuti format laporan kecelakaan: **A** Kecelakaan Tambang (KEPMEN ESDM 1827/2018: Fatality, Cidera Berat, Cidera Ringan), **B** Kecelakaan Umum (ANSI: RWI, MTI, FAI, Property Damage), **C** Pencemaran Lingkungan, **D** Fire Case, **E** Nearmiss. Rekap A–E ada di Laporan & Grafik dan Ringkasan Bulanan (Total Mining Accident Case, Total Accident Case, dst). Definisi & ambang hari kerja hilang ada di `config/ssap.php`. |
| **Monitoring LPI Kontraktor** | Master kontraktor area **IUP / IUJP**; insiden dapat dikaitkan ke kontraktor, lalu LPI (Laporan Penyelidikan Insiden) wajib masuk sebelum tenggat (Fatal 3 hari, Cidera Berat 5 hari, lainnya 7 hari; Nearmiss dikecualikan). HSE mencatat LPI diterima, menyetujui, atau meminta revisi. Dashboard per kontraktor: diterima, tepat waktu, terlambat, revisi, rata-rata hari; pengingat email harian ke PIC kontraktor; ekspor CSV. |
| **Hazard Report** | Penilaian risiko matriks 5x5 dengan tenggat otomatis (Ekstrem 1 hari … Rendah 14 hari), penugasan PIC, tutup otomatis saat semua tindakan terverifikasi. |
| **Inspeksi Rutin** | Template checklist (5 bawaan, bisa diubah), isian Sesuai / Tidak sesuai / N/A, skor otomatis, **temuan otomatis menjadi hazard**, dashboard kepatuhan terhadap target bulanan. |
| **Tindakan Korektif** | HSE menugaskan PIC + tenggat → PIC memperbarui dengan bukti → HSE memverifikasi/mengembalikan. Pengingat harian. |
| **Laporan & Grafik** | Filter periode/lokasi, rekap per jenis/lokasi/bulan, ekspor CSV (insiden, hazard, tindakan), **Ringkasan Bulanan** siap cetak/PDF dengan kolom tanda tangan. |
| **Pengelolaan** | Jam kerja bulanan per lokasi, lokasi/site, template inspeksi, penerima notifikasi, status & tes integrasi HRIS / AKP Mail. |
| **Standar Pelaporan** | Halaman rujukan klasifikasi, matriks risiko, tenggat, dan rumus (dibaca dari `config/ssap.php`). |

## Level akses (`config/sso.php`)

| Level | Boleh |
|---|---|
| `admin` | Semua, termasuk lokasi, pengaturan, hapus laporan |
| `hse` | Menangani laporan (investigasi, tutup, tugaskan & verifikasi tindakan), jam kerja, template inspeksi, laporan |
| `manager` | Melihat seluruh data, dashboard, laporan & ekspor (tanpa mengubah) |
| `user` | Membuat laporan/inspeksi, melihat laporannya sendiri, mengerjakan tindakan yang ditugaskan |

Level dicek di route (`portal.role:`) dan di controller (`App\Support\Access`). Jangan ubah kode level setelah ada pengguna.

## Integrasi

- **HRIS**: `HRIS_API_URL`, `HRIS_API_TOKEN` (sama dengan `HRIS_API_TOKEN` di HRIS). Membaca `GET /api/integrations/employees` (cache 10 menit, disegarkan tiap jam).
  Hanya NIK, nama, departemen, jabatan, email yang dipakai; tidak ada tabel karyawan. Bila HRIS mati atau token kosong, formulir tetap bisa diisi manual.
- **AKP Mail**: `MAILSVC_URL`, `MAILSVC_TOKEN` (token `akpm_…` dari AKP Mail > Aplikasi). Email dicatat di tabel `email_logs`, bisa dikirim ulang dari Pengaturan.
  Tanpa token, email memakai `MAIL_*` Laravel (lokal: log).
- Penerima HSE/manajemen/insiden berat diatur di **Pengaturan** (nilai awal dari `SSAP_NOTIFY_*`).

## Menjalankan di lokal

```
composer install
copy .env.example .env      # isi DB_*, PORTAL_SSO_SECRET, HRIS_API_TOKEN, MAILSVC_TOKEN
php artisan key:generate
php artisan migrate
php artisan serve --port=8009
```

1. Nyalakan MySQL XAMPP, buat database `akp_ssap_safety` (utf8mb4).
2. Portal > Modul > Tambah modul: kode `ssap-safety`, URL `http://localhost:8009`; salin rahasia dari halaman Integrasi ke `.env`.
3. `php artisan portal-sso:check`, lalu buka Portal dan klik modul **SAP Safety**. Beri level di Portal > Pengguna & Akses.
4. Login sebagai admin, tambahkan **Lokasi / Site** dan isi **Jam Kerja**.

**Data demo (rekaan, hanya non-produksi):**

```
php artisan ssap:demo --fresh     # 14 bulan insiden, hazard, inspeksi, tindakan, jam kerja
```

Perintah lain: `php artisan ssap:remind` (pengingat + ringkasan HSE), `php artisan ssap:sync-hris`, `php artisan schedule:work` (penjadwal).

## Tes

```
php artisan test
```

Mencakup akses per level, sidebar, alur insiden/hazard/tindakan/inspeksi, perhitungan LTIFR, ekspor, lampiran privat, HRIS & AKP Mail (dipalsukan, tidak memanggil layanan asli).

## Pemasangan di server (Docker, Portainer, CI/CD)

Mengikuti `STANDAR-APLIKASI.md` (bagian 10-17). Port server 8112, port database 127.0.0.1:3314, image `ghcr.io/eddyyucca/akp-ssap-safety`, alamat `ssap.lameruru.web.id`.
Stack terdiri dari `db`, `ssap` (web), dan `scheduler` (penjadwal, image sama). Periksa dulu di Portainer bahwa port 8112 dan 3314 belum terpakai.

1. GitHub: buat repo privat (mis. `SSAP-Safety-AKP`), isi secret `GHCR_TOKEN` dan `PORTAINER_WEBHOOK_URL`, lalu `git remote add origin ...` dan push `main`.
2. Server: pasang runner (label `akp-server`) sebagai layanan Windows di `C:\actions-runner-ssap-safety`.
3. Portal server: daftarkan modul kode `ssap-safety`, URL `https://ssap.lameruru.web.id`, salin rahasia SSO, lalu **Deteksi level** (admin, hse, manager, user).
4. Laptop: `powershell -ExecutionPolicy Bypass -File deploy\portainer\buat-isian-ssap.ps1`, lalu tempel isiannya di Portainer. Isi `HRIS_API_TOKEN` dan `MAILSVC_TOKEN`.
5. Portainer: Add stack > nama `ssap-safety` > Repository > `deploy/portainer/docker-compose.git.yml` > aktifkan webhook > salin ke secret GitHub.
6. Cloudflare: tambah subdomain `ssap` ke port 8112, lalu Tes koneksi di Portal.
7. Setelah login pertama sebagai admin: tambah lokasi, isi jam kerja, isi penerima notifikasi di Pengaturan, uji **Tes koneksi HRIS** dan **Email uji**.

Lampiran tersimpan di volume `ssap_storage` (privat, hanya lewat aplikasi). Workflow menjalankan tes lebih dulu; image hanya dibuat dan dipasang bila semua tes lulus.

## Catatan data

- Migrasi hanya menambah tabel/kolom; template inspeksi bawaan dibuat oleh migrasi dan bisa diubah admin/HSE.
- Laporan menyimpan salinan identitas pelapor saat melapor (nama, departemen, jabatan) agar tetap utuh walau data HRIS berubah.
- Laporan yang dihapus admin disembunyikan (soft delete), bukan dihapus dari database.
