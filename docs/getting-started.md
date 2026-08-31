# Panduan Instalasi, Konfigurasi & Penggunaan CoFund (Getting Started)

Dokumen ini memuat panduan lengkap untuk melakukan instalasi, konfigurasi berkas `.env`, migrasi basis data, menjalankan *queue worker* dan *cron scheduler*, serta petunjuk penggunaan antarmuka aplikasi untuk setiap peran (*Role*: **Admin**, **Creator**, dan **Backer**).

---

## 1. Prasyarat Sistem (Prerequisites)

Pastikan lingkungan server lokal Anda telah terpasang perangkat lunak berikut:
- **PHP**: Versi `>= 8.2` (Ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `gd` / `imagick`)
- **Composer**: Versi `>= 2.5`
- **Node.js**: Versi `>= 18.0` & **npm** `>= 9.0`
- **MySQL / MariaDB**: Versi MySQL `>= 8.0` atau MariaDB `>= 10.4` (Direkomendasikan menggunakan stack **Laragon** atau **XAMPP**)

---

## 2. Langkah Instalasi & Konfigurasi

### A. Backend (Laravel 10 REST API)

1. **Masuk ke direktori backend:**
   ```bash
   cd backend
   ```

2. **Pasang dependensi PHP:**
   ```bash
   composer install
   ```

3. **Duplikasi file konfigurasi `.env`:**
   ```bash
   cp .env.example .env
   ```

4. **Konfigurasi koneksi basis data pada `.env`:**
   ```ini
   APP_NAME=CoFund
   APP_ENV=local
   APP_KEY=
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   FRONTEND_URL=http://localhost:5173

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=cofund
   DB_USERNAME=root
   DB_PASSWORD=

   QUEUE_CONNECTION=database
   FILESYSTEM_DISK=public
   ```

5. **Generate Application Key & Simlink Storage:**
   ```bash
   php artisan key:generate
   php artisan storage:link
   ```

6. **Jalankan Migrasi & Database Seeder:**
   ```bash
   php artisan migrate:fresh --seed
   ```
   > Seeder akan otomatis membuat akun default untuk pengujian:
   > - **Admin**: `admin@cofund.test` / `password`
   > - **Creator**: `creator@cofund.test` / `password`
   > - **Backer**: `backer@cofund.test` / `password`

---

### B. Frontend (Vue 3 Single Page Application)

1. **Masuk ke direktori frontend:**
   ```bash
   cd ../frontend
   ```

2. **Pasang dependensi Node.js:**
   ```bash
   npm install
   ```

3. **Periksa konfigurasi `.env` frontend:**
   Pastikan berkas `frontend/.env` mengarah ke backend API:
   ```ini
   VITE_API_BASE_URL=http://localhost:8000/api/v1
   VITE_STORAGE_BASE_URL=http://localhost:8000
   ```

---

## 3. Menjalankan Server Pengembangan (Running Dev Server)

Jalankan layanan berikut secara paralel pada terminal terpisah:

### 1. Server Backend Laravel
```bash
cd backend
php artisan serve --port=8000
```
*API Base URL aktif di:* `http://localhost:8000/api/v1`

### 2. Background Queue Worker (Penting untuk Virtual Escrow & Email)
```bash
cd backend
php artisan queue:work
```
*Memproses job refund otomatis (`RefundBackersJob`), pencairan dana (`DisburseCampaignJob`), dan notifikasi email.*

### 3. Server Frontend Vite SPA
```bash
cd frontend
npm run dev
```
*Aplikasi frontend dapat diakses di:* `http://localhost:5173`

---

## 4. Panduan Alur Penggunaan Berdasarkan Peran (Role Usage Guide)

### A. Alur Pengguna: Donatur (Backer)
1. **Registrasi Akun:** Buka `http://localhost:5173/register`, isi nama, email, dan password.
2. **Top-up Saldo Virtual:**
   - Kunjungi menu **Dompet** (`/wallet`).
   - Klik tombol **Isi Saldo (Deposit)**.
   - Masukkan nominal (misal: Rp 500.000) dan konfirmasi. Saldo virtual dompet akan bertambah seketika.
3. **Mendanai Kampanye (Backing):**
   - Buka menu **Jelajahi Kampanye** (`/campaigns`).
   - Pilih kampanye yang sedang aktif (`status: active`).
   - Klik tombol utama **Dukung Kampanye Sekarang**.
   - Pilih paket reward tier yang diinginkan (atau masukkan nominal bebas), lalu klik **Konfirmasi & Bayar Sekarang**.
   - Dana Anda akan ditahan secara aman di **Virtual Escrow Platform**.
4. **Pantau Kabar Proyek:**
   - Buka tab **Kabar Terbaru** pada detail kampanye untuk membaca pembaruan kemajuan dari inisiator.

---

### B. Alur Pengguna: Inisiator (Creator)
1. **Upgrade ke Creator:**
   - Setelah mendaftar, klik menu **Mulai Kampanye** atau tombol **Ajukan Jadi Kreator**.
   - Masukkan alasan inisiasi proyek untuk meningkatkan peran akun menjadi `creator`.
2. **Membuat Kampanye Baru:**
   - Kunjungi menu **Buat Kampanye** (`/campaigns/create`).
   - Lengkapi form: Judul proyek, Kategori, Target dana (misal: Rp 50.000.000), Tanggal batas akhir (deadline), dan Deskripsi lengkap ide.
   - Unggah 1 hingga 5 foto visual proyek (tentukan 1 foto sebagai cover utama).
   - Tambahkan minimal 1 paket reward donasi (*Reward Tier*).
   - Simpan draf kampanye (`status: draft`).
3. **Mengajukan Peninjauan:**
   - Pada halaman detail kampanye draf Anda, klik **Ajukan untuk Review Admin**. Status berubah menjadi `review`.
4. **Menerbitkan Kabar Proyek:**
   - Setelah kampanye disetujui admin dan berstatus `active`, inisiator dapat memposting artikel kemajuan di menu **Kabar Terbaru**.
5. **Pencairan Dana (Disbursement):**
   - Saat deadline berakhir dan total donasi terkumpul mencapai/melebihi target 100%, sistem otomatis mentransfer **95% dana ke saldo dompet kreator** (5% dipotong sebagai biaya platform).

---

### C. Alur Pengguna: Administrator (Admin)
1. **Login Admin:** Masuk menggunakan kredensial akun administrator.
2. **Meninjau Pengajuan Kampanye:**
   - Kunjungi menu **Kelola Kampanye Admin** (`/admin/campaigns`).
   - Pilih kampanye dengan status `review`.
   - Periksa kelayakan data, target dana, dan foto proposal.
   - Klik tombol **Setujui Kampanye** (status berubah menjadi `active`) atau **Tolak Kampanye** (disertai alasan penolakan).
3. **Moderasi Kampanye & Force-Fail:**
   - Jika ditemukan indikasi pelanggaran atau kreator membatalkan proyek, Admin dapat menekan tombol **Batalkan Kampanye (Force Fail)**.
   - Sistem akan langsung memicu `RefundBackersJob` untuk mengembalikan 100% dana ke saldo seluruh donatur.
4. **Manajemen Pengguna:**
   - Buka menu **Kelola Pengguna** (`/admin/users`).
   - Admin dapat melihat riwayat aktivitas user, menangguhkan (*suspend*) akun bermasalah, atau memulihkan (*unsuspend*) akun.
5. **Memantau Analitik Platform:**
   - Buka menu **Dashboard Admin** (`/admin/dashboard`) untuk melihat grafik total dana yang dihimpun, jumlah backer aktif, tingkat kesuksesan proyek, dan total perolehan *platform fee* 5%.
