# CoFund - Decoupled Crowdfunding Platform

[![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=flat-square&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![PrimeVue](https://img.shields.io/badge/PrimeVue-4.x-06B6D4?style=flat-square&logo=primevue&logoColor=white)](https://primevue.org)
[![License](https://img.shields.io/badge/License-MIT-blue?style=flat-square)](LICENSE)

CoFund adalah platform urun dana (crowdfunding) berbasis arsitektur terpisah (*decoupled system*) yang mengintegrasikan backend RESTful API berbasis Laravel 10 dengan antarmuka Single Page Application berbasis Vue 3. Sistem ini menerapkan protokol Virtual Escrow terotomatisasi untuk penahanan dana, pencairan dana proyek sukses, serta pengembalian dana donatur (*auto-refund*).

---

## Indeks Dokumentasi

Seluruh spesifikasi teknis, arsitektur, dan panduan penggunaan sistem CoFund terdokumentasi secara modular pada direktori berikut:

| Kategori Dokumentasi | Berkas / Direktori | Deskripsi |
|---|---|---|
| Arsitektur Sistem | [docs/architecture.md](docs/architecture.md) | Gambaran arsitektur decoupled, diagram aliran data, alur token Sanctum, dan protokol Virtual Escrow. |
| Basis Data & ERD | [docs/database-schema.md](docs/database-schema.md) | Spesifikasi 9 tabel aktif, relasi Foreign Keys, soft deletes, dan definisi Enum status. |
| Panduan Memulai | [docs/getting-started.md](docs/getting-started.md) | Petunjuk instalasi lokal, konfigurasi environment, migrasi, queue worker, dan alur penggunaan per peran. |
| Dokumentasi API | [backend/docs/api/](backend/docs/api/) | Spesifikasi lengkap 12 modul REST API berformat 10 bab baku beserta parameter dan skema JSON. |
| Arsitektur Frontend | [frontend/docs/ui-architecture.md](frontend/docs/ui-architecture.md) | Struktur modul Vue 3, Navigation Guards, Pinia Store Management, dan Axios API wrapper. |
| Design System | [frontend/docs/design-system.md](frontend/docs/design-system.md) | Standar UI/UX Modern Navy FinTech, token Tailwind CSS, integrasi PrimeVue Aura, dan micro-interactions. |
| Modul Frontend | [frontend/docs/modules/](frontend/docs/modules/) | Dokumentasi modular tampilan antarmuka (Auth, Campaigns, Creator, Backer, Admin, Wallet). |
| Pengujian API | [docs/postman/](docs/postman/) | Koleksi Postman JSON resmi (`CoFund-API.postman_collection.json`) untuk pengujian otomatis 46 endpoint. |

---

## Tech Stack & Prasyarat Sistem

### Teknologi Utama

| Layer | Teknologi / Library | Keterangan |
|---|---|---|
| Backend | Laravel 10.x, PHP 8.2 | RESTful JSON API, Service Layer Pattern, Form Request Validation |
| Autentikasi | Laravel Sanctum 3.2 | Stateless Bearer Token Authentication |
| Basis Data | MySQL 8.0 / MariaDB 10.4 | Relational DB dengan Transactional Integrity & Soft Deletes |
| Antrian (Queue) | Database Queue Driver | Eksekusi asinkron job pencairan dana dan refund otomatis |
| Frontend | Vue 3.5 (Composition API) | Single Page Application dibangun dengan Vite 8 |
| UI Framework | Tailwind CSS 3.4 & PrimeVue 4 | Preset Aura, PrimeIcons, dan styling Modern Navy FinTech |
| State Management | Pinia 4.0 | Global reactive state per domain entitas |
| Validasi Form | Vee-Validate 4.15 & Yup 1.7 | Validasi form deklaratif berbasis skema |

### Prasyarat Minimum

- PHP `>= 8.1` (Disarankan PHP 8.2) dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `gd`
- Composer `>= 2.5`
- Node.js `>= 18.0` & NPM `>= 9.0`
- MySQL Server `>= 8.0` atau MariaDB `>= 10.4`

---

## Petunjuk Memulai (Quick Start)

### 1. Konfigurasi Backend (Laravel API)

```bash
# Masuk ke direktori backend
cd backend

# Pasang dependensi PHP
composer install

# Salin berkas environment
cp .env.example .env

# Generate Application Key & Simlink Storage
php artisan key:generate
php artisan storage:link

# Jalankan migrasi dan database seeder
php artisan migrate:fresh --seed

# Jalankan server backend lokal (Port 8000)
php artisan serve --port=8000
```

Jalankan queue worker pada terminal terpisah untuk memproses job Virtual Escrow:
```bash
cd backend
php artisan queue:work
```

### 2. Konfigurasi Frontend (Vue 3 SPA)

```bash
# Masuk ke direktori frontend
cd frontend

# Pasang dependensi JavaScript
npm install

# Jalankan server pengembangan Vite (Port 5173)
npm run dev
```

Aplikasi frontend dapat diakses melalui browser pada alamat `http://localhost:5173`.

---

## Ringkasan Hak Akses Pengguna (RBAC)

Platform CoFund membagi hak akses ke dalam 3 tingkatan peran utama:

| Peran (Role) | Ruang Lingkup Hak Akses |
|---|---|
| Backer (Donatur) | Menjelajahi katalog proyek publik, mengelola saldo virtual (deposit & withdraw), mendanai kampanye aktif (*backing*), dan melihat mutasi buku besar transaksi. |
| Creator (Inisiator) | Seluruh hak akses Backer ditambah kemampuan membuat draf proyek, mengelola paket reward tier, mengunggah foto visual, menerbitkan kabar proyek (*milestone updates*), dan menerima 95% pencairan dana saat target tercapai. |
| Admin (Administrator) | Meninjau pengajuan kampanye (persetujuan *approve*, penolakan *reject*, dan pembatalan paksa *force-fail*), mengelola status penangguhan akun pengguna (*suspend/unsuspend*), serta memantau akumulasi biaya platform (5% *platform fee*). |

---

## Kredensial Akun Pengujian Default

Setelah menjalankan `php artisan migrate:fresh --seed`, sistem menyediakan akun pengujian default:

| Peran | Email | Kata Sandi |
|---|---|---|
| Administrator | `admin@cofund.test` | `password` |
| Inisiator (Creator) | `creator@cofund.test` | `password` |
| Donatur (Backer) | `backer@cofund.test` | `password` |

---

## Lisensi

Proyek ini dirilis di bawah lisensi [MIT License](LICENSE).
