# SAP - Safety Accountability Program

Modul AKPSatu PT Adhi Kartiko Pratama Tbk untuk **laporan hazard** dan **inspeksi rutin**, dengan fokus pada **akuntabilitas**:
setiap temuan punya penanggung jawab (PIC), tenggat sesuai risikonya, bukti penyelesaian, dan verifikasi serta penutupan oleh pelapor sendiri.

Stack: Laravel 12, PHP 8.3, MySQL/MariaDB, Bootstrap 5 (tema HRIS), Chart.js 4.4.3, login lewat AKPSatu (`akp/portal-sso`).
Karyawan disinkronkan dari **HRIS** (API baca-saja) dan notifikasi dikirim lewat **AKP Mail**.
Kode modul di Portal tetap `ssap-safety`.

## Fitur

| Modul | Isi |
|---|---|
| **Hazard Report** | Formulir dengan foto dan penilaian risiko matriks 5x5; tenggat otomatis (Ekstrem 1 hari, Tinggi 3, Sedang 7, Rendah 14). HSE menugaskan PIC; PIC mengerjakan dengan bukti; HSE memverifikasi atau mengembalikan; hazard otomatis selesai saat semua tindakannya terverifikasi. |
| **Inspeksi Rutin** | Template checklist (5 bawaan, bisa diubah) dengan target per bulan; isian Sesuai / Tidak sesuai / N/A; skor otomatis; **temuan "Tidak sesuai" otomatis menjadi hazard**. |
| **Dashboard** (admin, HSE, manajer) | Ringkasan hazard, inspeksi, dan akuntabilitas: penyelesaian, tepat waktu (SLA), umur hazard terbuka, peta risiko, tren, kepatuhan jadwal inspeksi, papan PIC, inspeksi tertinggal target. Pelapor biasa melihat ringkasan pribadi. |
| **Dashboard Hazard** | Tren per tingkat risiko, kategori, lokasi, departemen pelapor, aging, peta risiko, status tindakan, rekap risiko x status, pelapor teraktif. |
| **Dashboard Inspeksi** | Inspeksi vs target per bulan, skor, per lokasi, pencapaian per template, temuan yang paling sering berulang. |
| **Akuntabilitas PIC** | Kinerja per PIC dan per departemen (ditugaskan, terbuka, terlambat, selesai, % tepat waktu, rata-rata hari), tindakan terlambat, papan inspektor, template tertinggal. |
| **Laporan & Grafik** | Filter periode/lokasi, grafik hazard + inspeksi, rekap tabel (risiko, kategori, lokasi, PIC, template, bulanan), **unduh CSV** (hazard, inspeksi, temuan inspeksi, tindakan), dan **Ringkasan Bulanan** siap cetak/PDF dengan kolom tanda tangan. |
| **Pengelolaan** | Template inspeksi, lokasi/site, penerima notifikasi, status & tes integrasi HRIS / AKP Mail. |
| **Standar Pelaporan** | Matriks risiko, tenggat, alur, dan rumus indikator (dibaca dari `config/ssap.php`). |

### Target per grade & hierarki (HRIS)

SAP membaca dari HRIS: NIK, nama, **departemen, divisi, seksi, jabatan, grade, status kepegawaian**, dan **atasan langsung** (`supervisor_nik`).
Ringkasan hasil sinkron (jumlah karyawan, departemen, jabatan, grade) tampil di **Pengaturan > Sinkron HRIS**.

Target diatur admin di **Pengaturan**, per grade (daftar grade diambil dari HRIS): periode (**per minggu**, Senin-Minggu, atau per bulan), jumlah laporan hazard, dan jumlah inspeksi.
Contoh: grade 3, per minggu, hazard 2 = tiap karyawan grade 3 membuat 2 laporan hazard seminggu. Nilai awal grade 3, 4, 5 = 2 hazard per minggu; isi 0 + 0 bila suatu grade tidak punya target.

| Grade | Yang dilihat |
|---|---|
| 3 dan 4 | hanya miliknya sendiri |
| 5 | dirinya dan seluruh bawahan (langsung maupun tidak langsung) |
| 6 ke atas (kepala departemen) | seluruh departemen dan targetnya |

Menu **Target Saya / Tim / Departemen** menampilkan capaian periode, siapa yang belum memenuhi target, dan grafik per departemen; admin, HSE, dan manajer melihat seluruh departemen.
Hazard yang dihitung adalah laporan yang dibuat sendiri; temuan otomatis dari inspeksi tidak dihitung. Ringkasan harian HSE memuat jumlah karyawan yang belum mencapai target (mulai Kamis untuk target mingguan, tanggal 20 untuk bulanan).
Bila grade tidak terbaca dari HRIS, pengguna diperlakukan seperti grade 3-4 (hanya miliknya sendiri). Data demo menyertakan hierarki rekaan di cache HRIS (hanya terbaca bila `HRIS_API_URL`/`HRIS_API_TOKEN` terisi).

### Definisi indikator

- **Penyelesaian** = hazard selesai / seluruh hazard pada periode.
- **Tepat waktu (SLA)** = hazard ditutup pada atau sebelum tenggat / seluruh hazard selesai.
- **Terlambat** = hazard (atau tindakan) terbuka yang melewati tenggat.
- **Skor inspeksi** = butir sesuai / (sesuai + tidak sesuai) x 100%; N/A tidak dihitung. Baik >= 90%, cukup >= 75%.
- **Kepatuhan inspeksi** = inspeksi terlaksana / (target bulanan template x bulan berjalan).

## Level akses (`config/sso.php`)

| Level | Boleh |
|---|---|
| `admin` | Semua, termasuk lokasi, pengaturan, hapus laporan |
| `hse` | Menugaskan PIC, memverifikasi tindakan, menutup/menolak hazard, template inspeksi, dashboard & laporan |
| `manager` | Melihat seluruh data, dashboard, laporan & ekspor (tanpa mengubah) |
| `user` | Membuat hazard & inspeksi, melihat laporannya sendiri, mengerjakan tindakan yang ditugaskan |

Level dicek di route (`portal.role:`) dan di controller (`App\Support\Access`). Jangan ubah kode level setelah ada pengguna.

## Integrasi

- **HRIS**: `HRIS_API_URL`, `HRIS_API_TOKEN` (sama dengan `HRIS_API_TOKEN` di HRIS). Membaca `GET /api/integrations/employees` (cache 10 menit, disegarkan tiap jam).
  Hanya NIK, nama, departemen, jabatan, email yang dipakai; tidak ada tabel karyawan. Bila HRIS mati atau token kosong, formulir tetap bisa diisi manual.
- **AKP Mail**: `MAILSVC_URL`, `MAILSVC_TOKEN` (token `akpm_...` dari AKP Mail > Aplikasi). Email dicatat di tabel `email_logs`, bisa dikirim ulang dari Pengaturan.
  Tanpa token, email memakai `MAIL_*` Laravel (lokal: log). Email: hazard baru (HSE; ekstrem juga ke manajemen), penugasan PIC, tindakan selesai, hasil verifikasi, pengingat harian, ringkasan harian HSE.
- Penerima HSE/manajemen/hazard ekstrem diatur di **Pengaturan** (nilai awal dari `SSAP_NOTIFY_*`).

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
3. `php artisan portal-sso:check`, lalu buka Portal dan klik modul. Beri level di Portal > Pengguna & Akses (lalu **Deteksi level**).
4. Login sebagai admin, tambahkan **Lokasi / Site** dan atur penerima notifikasi.

**Data demo (rekaan, hanya non-produksi):**

```
php artisan ssap:demo --fresh     # 14 bulan hazard, inspeksi, tindakan; PIC andal dan PIC yang sering terlambat
```

Perintah lain: `php artisan ssap:remind` (pengingat PIC + ringkasan HSE), `php artisan ssap:sync-hris`, `php artisan schedule:work` (penjadwal).

## Tes

```
php artisan test
```

Mencakup akses per level, menu, alur hazard (lapor, tugaskan, kerjakan, verifikasi, tutup otomatis), inspeksi (skor, temuan menjadi hazard), perhitungan SLA/aging/papan PIC, ekspor CSV, lampiran privat, pengingat, HRIS & AKP Mail (dipalsukan, tidak memanggil layanan asli).

## Pemasangan di server (Docker, Portainer, CI/CD)

Mengikuti `STANDAR-APLIKASI.md` (bagian 10-17). Port server 8112, port database 127.0.0.1:3314, image `ghcr.io/eddyyucca/akp-ssap-safety`, alamat `ssap.lameruru.web.id`.
Stack terdiri dari `db`, `ssap` (web), dan `scheduler` (penjadwal, image sama). Periksa dulu di Portainer bahwa port 8112 dan 3314 belum terpakai.

1. GitHub: buat repo privat (mis. `SAP-Safety-AKP`), isi secret `GHCR_TOKEN` dan `PORTAINER_WEBHOOK_URL`, lalu `git remote add origin ...` dan push `main`.
2. Server: pasang runner (label `akp-server`) sebagai layanan Windows di `C:\actions-runner-ssap-safety`.
3. Portal server: daftarkan modul kode `ssap-safety`, URL `https://ssap.lameruru.web.id`, salin rahasia SSO, lalu **Deteksi level** (admin, hse, manager, user).
4. Laptop: `powershell -ExecutionPolicy Bypass -File deploy\portainer\buat-isian-ssap.ps1`, lalu tempel isiannya di Portainer. Isi `HRIS_API_TOKEN` dan `MAILSVC_TOKEN`.
5. Portainer: Add stack > nama `ssap-safety` > Repository > `deploy/portainer/docker-compose.git.yml` > aktifkan webhook > salin ke secret GitHub.
6. Cloudflare: tambah subdomain `ssap` ke port 8112, lalu Tes koneksi di Portal.
7. Setelah login pertama sebagai admin: tambah lokasi, isi penerima notifikasi di Pengaturan, uji **Tes koneksi HRIS** dan **Email uji**.

Lampiran tersimpan di volume `ssap_storage` (privat, hanya lewat aplikasi). Workflow menjalankan tes lebih dulu; image hanya dibuat dan dipasang bila semua tes lulus.

## Modul yang diarsipkan

Modul **laporan insiden, monitoring LPI kontraktor (IUP/IUJP), dan jam kerja (LTIFR/TRIR)** dikeluarkan dari aplikasi untuk memfokuskan SAP pada hazard dan inspeksi.
Kodenya disimpan di `arsip/modul-insiden-lpi/` (tidak dimuat aplikasi dan tidak ikut image Docker), dan tabel databasenya
(`incidents`, `incident_persons`, `contractors`, `man_hours`) **tidak dihapus**, jadi data lama tetap utuh dan modulnya bisa dipulihkan.
Tindakan lama yang bersumber dari insiden tetap tersimpan tetapi tidak ditampilkan.

## Catatan data

- Migrasi hanya menambah tabel/kolom; template inspeksi bawaan dibuat oleh migrasi dan bisa diubah admin/HSE.
- Laporan menyimpan salinan identitas pelapor saat melapor (nama, departemen, jabatan) agar tetap utuh walau data HRIS berubah.
- Laporan yang dihapus admin disembunyikan (soft delete), bukan dihapus dari database.
