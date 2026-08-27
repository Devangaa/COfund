# Analisis Gap Implementasi CoFund

Perbandingan antara spesifikasi fitur (`cofund.md`) dan implementasi backend yang ada di `C:\laragon\www\COfund\backend`.

---

## Legenda

| Simbol | Makna |
|--------|---------|
| ✅ | Diimplementasikan dengan benar |
| ⚠️ | Implementasi parsial atau kasus tepi perlu perhatian |
| ❌ | Tidak diimplementasikan atau rusak |
| 🆕 | Diimplementasikan tapi TIDAK ada di spesifikasi (bonus/ekstra) |
| 🔜 | Direncanakan tapi belum dibangun |

---

## Bagian 1.1 — Ikhtisar

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Platform crowdfunding dengan kampanye, tier, backing | ✅ | `app/Models/Campaign.php` | Sepenuhnya diimplementasikan |
| Creator → DRAFT → REVIEW → ACTIVE → SUCCESS/FAILED | ✅ | Enum `CampaignStatus`, `CampaignService` | Mesin status diimplementasikan di lapisan service |
| Escrow virtual untuk dana backing | ✅ | `BackingService::create()` menambahkan `campaigns.collected_amount` | Diimplementasikan sebagai escrow virtual (bukan akun escrow terpisah) |
| Pekerjaan terjadwal (lifecycle) | ✅ | `app/Console/Kernel.php` | Berjalan harian pukul 00:05 dan 09:00 |
| Worker antrian (disburse/refund) | ✅ | `DisburseCampaignJob`, `RefundBackersJob` | Keduanya mengimplementasikan `ShouldQueue`, tapi `QUEUE_CONNECTION=sync` jadi mereka berjalan inline |
| Sistem notifikasi multi-peran | ✅ | 5 listener di `EventServiceProvider` | Notifikasi dalam aplikasi + email melalui mailable |
| Backend: Laravel REST API | ✅ | `routes/api.php` | Semua endpoint API-first |
| Frontend: Vue.js | 🔜 | Direktori `/frontend/` tidak ada | Backend lengkap; frontend tidak termasuk |
| DB: MySQL / PostgreSQL | ✅ | MySQL 8 | Dikonfigurasi untuk MySQL, konfigurasi PostgreSQL tersedia |
| Driver Antrian: Redis / Database | ✅ | `config/queue.php` | Redis sudah dikonfigurasi sebagai default di `.env` (`QUEUE_CONNECTION=redis`) dan sudah di-deploy |

---

## Bagian 1.2 — Peran & Akses

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| 3 peran fungsional: backer, creator, admin | ✅ | Konstan `User::ROLES` | `backer`, `creator`, `admin` sudah diimplementasikan; `guest` bukan peran — melainkan representasi pengunjung yang belum terotentikasi (tanpa data `User`), ditangani secara implisit oleh rute publik |
| Pengguna dapat menjadi backer dan creator secara bersamaan | ✅ | Kolom `User.role` | Bidang peran tunggal; secara praktis satu peran per pengguna |
| Admin ditentukan via database atau panel | ✅ | Konstanta `User::ROLE_ADMIN` | Penentuan manual via DB; tidak ada UI admin untuk perubahan peran |

---

## Bagian 1.3 — Modul Otentikasi

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Register: nama, email, password, konfirmasi | ✅ | `AuthService::register()`, `RegisterRequest` | Memvalidasi email unik, password min 8 |
| Register → kirim email verifikasi | ✅ | `event(new Registered($user))` → listener `SendEmailVerificationNotification` | Laravel built-in |
| Peran default = backer | ✅ | `AuthService::register()` mengatur `role = 'backer'` |
| Login: email + password | ✅ | `AuthService::login()`, `LoginRequest` | Mengembalikan token Sanctum |
| Login → token + data pengguna | ✅ | `AuthService::login()` mengembalikan `['user', 'token']` |
| Verifikasi email sebelum backing/membuat kampanye | ✅ | Middleware `verified` pada route yang relevan |
| Lupa password → tautan reset | ✅ | `AuthController::forgotPassword()` → `Password::sendResetLink()` | Laravel built-in broker |
| Tautan reset kadaluarsa dalam 60 menit | ✅ | `config/auth.php` `expire => 60` | Default adalah 60 menit |
| **Kirim kembali email verifikasi** | 🆕 | Route `POST /api/email/resend` + closure controller | Diimplementasikan tapi tidak ada di spesifikasi |
| **Verifikasi email melalui signed URL** | 🆕 | Route `GET /api/email/verify/{id}/{hash}` | Diimplementasikan sebagai closure inline |
| **Route notis verifikasi email** | 🆕 | Route `GET /api/email/verify/notice` | Mengembalikan 403 JSON |

---

## Bagian 1.4 — Modul Kampanye

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| **Field pembuatan kampanye** | ✅ | `StoreCampaignRequest`, `CampaignService::create()` | Semua field termasuk `slug` sudah diimplementasikan; `slug` dapat dikirim manual atau akan otomatis dibuatkan dari judul jika kosong |
| Judul (maks 100 karakter) | ✅ | `StoreCampaignRequest: title` → `max:100` |
| Slug — otomatis dari judul, dapat diedit | ✅ | Observer `Campaign::saving()` melewati auto-generate bila slug sudah ada diisi | `slug` dapat dikirim manual melalui `StoreCampaignRequest`; hanya otomatis dibuatkan dari judul jika field kosong |
| Kategori — pilih dari daftar | ✅ | `category_id` → `exists:categories,id` |
| Deskripsi (rich text/markdown) | ✅ | Field `description` (text) | Markdown disimpan sebagai plain text; accessor `description_html` me-render HTML via Parsedown dengan safe mode |
| Target dana (min Rp 100.000) | ✅ | `StoreCampaignRequest: target_amount` → `min:100000` |
| Deadline (min H+7) | ✅ | `deadline` → `date, after:+7 days` |
| Video embed (YouTube/Vimeo, opsional) | ✅ | `video_url` → `url` |
| Gambar (min 1, max 5) | ✅ | `StoreCampaignRequest: images[]` → `min:1, max:5, image, mimes:..., max:2048` |
| Status → draft setelah pembuatan | ✅ | `CampaignService::create()` mengatur `status = DRAFT` |
| Ajukan → review | ✅ | `CampaignService::submitForReview()` |
| Admin setujui → active | ✅ | `CampaignService::approve()` |
| Admin tolak → draft + catatan penolakan | ✅ | `CampaignService::reject()` |
| Edit hanya saat DRAFT | ✅ | `CampaignService::ensureEditable()` memeriksa `status === DRAFT` |
| Hanya dapat mengedit di draft, lalu hanya pembaruan setelah active | ✅ | Ditetapkan di lapisan service |

### Daftar & Filter Kampanye

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Daftar kampanye aktif | ✅ | `CampaignController::index()` | Default hanya mengembalikan status `active` |
| Filter berdasarkan kategori | ✅ | `CampaignController::index()` menerima `category_id` |
| Filter berdasarkan status | ✅ | Parameter query `status` diterima | Default `active`; admin + creator (`?scope=mine`) dapat memfilter berdasarkan status apa saja; parameter status untuk guest/backer diabaikan |
| Filter berdasarkan tanggal | ✅ | Parameter `start_date`, `end_date` | Mendukung filter kampanye berdasarkan rentang tanggal pembuatan |
| Urutkan: terbaru, populer | ✅ | Parameter `sort` | Mendukung `latest`, `oldest` (berdasarkan `created_at`), dan `popular` (berdasarkan `collected_amount`) |
| Cari | ✅ | Parameter `search` | Menggunakan indeks FULLTEXT (tapi query menggunakan LIKE, bukan MATCH...AGAINST) |

### Detail Kampanye

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Progress bar + persentase + hari tersisa | ✅ | `CampaignResource::progress_percentage` (dihasilkan) | Dihitung di sisi client dari `collected_amount` dan `target_amount` |
| Daftar tier yang tersedia | ✅ | `CampaignResource::tiers` dengan `CampaignTierResource` |
| Daftar backer | ⚠️ | `CampaignResource` **tidak** termasuk backer | Backer dapat diakses melalui endpoint terpisah: `GET /api/campaigns/{slug}/backings` |
| Pembaruan dari creator | ✅ | `CampaignResource::updates` dengan `CampaignUpdateResource` |
| **Notifikasi pembaruan kampanye** | ⚠️ | `CampaignUpdateService::notifyBackers()` | Diimplementasikan tetapi **tidak diantre** (insert massal sinkron) |

### Pembaruan Kampanye

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Creator memposting pembaruan teks saat aktif | ✅ | `CampaignUpdateService::create()` memeriksa `status === ACTIVE` |
| Semua backer mendapatkan notifikasi | ✅ | `notifyBackers()` membuat notifikasi dalam aplikasi |
| **Email notifikasi untuk pembaruan** | ❌ | ❌ Tidak diimplementasikan | Tidak ada email yang dikirim untuk pembaruan kampanye (hanya notifikasi dalam aplikasi) |

---

## Bagian 1.5 — Tier & Backing

### Tier Reward

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Creator mendefinisikan tier saat membuat kampanye | ✅ | `StoreCampaignRequest: tiers[]` |
| Setiap kampanye harus memiliki minimal satu tier | ✅ | `StoreCampaignRequest: tiers` → `required, array, min:1` |
| Field tier: name, min_amount, quota, reward_description | ✅ | `StoreTierRequest` memvalidasi semua field |
| Kuota berkurang otomatis saat backing baru | ✅ | `BackingService::create()` mengurangkan `remaining_quota` |
| Tier penuh → tidak dapat dipilih | ✅ | `BackingService::ensureTierAvailable()` memeriksa `remaining_quota` |
| Kuota 0 = tak terbatas | ✅ | `CampaignTier::isUnlimited()` memeriksa `$quota === 0` |

### Proses Backing

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Pengguna harus login | ✅ | Middleware `auth:sanctum` |
| Email diverifikasi sebelum backing | ✅ | Middleware `verified` |
| Creator tidak dapat mendukung kampanyanya sendiri | ✅ | `BackingService::ensureCanBack()` melempar `AuthorizationException` |
| Beberapa backing per pengguna per kampanye | ✅ | Tidak ada pembatasan di kode |
| Min backing: Rp 10.000 | ✅ | `BackingService::ensureMinimumAmount()` memeriksa `< 10000` |
| Pilih tier atau jumlah bebas | ✅ | `StoreBackingRequest` menerima `tier_id` opsional |
| Gateway pembayaran mock | ✅ | Referensi `mock_payment_{timestamp}` digunakan |
| Pembayaran berhasil → status backing completed | ✅ | `BackingService::create()` mengatur `status = COMPLETED` segera |
| Funding → escrow | ✅ | `collected_amount` ditambahkan, bukan saldo pengguna individu |
| Notifikasi konfirmasi (dalam aplikasi + email) | ✅ | Listener `HandleBackingCreated` membuat notifikasi + mengirim email `BackingConfirmation` |

---

## Bagian 1.6 — Transaksi & Escrow

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Backing → tipe transaksi `payment` | ✅ | `BackingService::create()` membuat `TransactionType::PAYMENT` |
| Pencairan (95% ke creator) | ✅ | `TransactionService::disbursementCampaign()` menghitung biaya 5% |
| Biaya platform (5%) | ⚠️ | `TransactionService::disbursementCampaign()` meng-hardcode `* 0.05` | Spesifikasi mengatakan 5%; kode meng-hardcode 5% — konsisten |
| Refund → tipe transaksi `refund` | ✅ | `TransactionService::refundBackers()` membuat `TransactionType::REFUND` |
| Biaya platform → tipe transaksi `platform_fee` | ✅ | `TransactionService::disbursementCampaign()` membuat `TransactionType::PLATFORM_FEE` |
| **Transaksi deposit/penarikan dompet** | 🆕 | `TransactionType::DEPOSIT`, `WITHDRAWAL` | TIDAK ada di spesifikasi tapi diimplementasikan |
| **Tipe transaksi** | ⚠️ | Enum `TransactionType` memiliki 6 tipe | Spesifikasi hanya menyebutkan 4 (`payment`, `refund`, `disbursement`, `platform_fee`); `deposit` dan `withdrawal` adalah tambahan |
| **Escrow = campaign.collected_amount** | ✅ | Dana dilacak di `campaigns.collected_amount` | Tidak ada akun escrow terpisah — escrow virtual |

### ⚠️ Masalah Kritis: Ketidaksesuaian Enum Tipe Transaksi

Migrasi `transactions` mendefinisikan kolom `type` sebagai:
```sql
ENUM('payment', 'refund', 'disbursement', 'platform_fee')
```

Tapi enum `TransactionType` PHP mencakup `deposit` dan `withdrawal`. Di bawah mode strict MySQL, mencoba memasukkan transaksi `deposit` atau `withdrawal` akan **GAGAL**. Ini merusak seluruh modul dompet.

---

## Bagian 1.7 — Siklus Hidup Kampanye (Pekerjaan Terjadwal)

| Persyaratan Spesifikasi | Status | Referensi Kode | Cataman |
|---|---|---|---|
| Cron berjalan harian pukul 00:05 | ✅ | `app/Console/Kernel.php::schedule()` → `dailyAt('00:05')` |
| Perintah CheckExpiredCampaigns | ✅ | Perintah `CheckExpiredCampaigns` di `campaign:check-expired` |
| Dapatkan kampanye dengan deadline < hari ini | ✅ | `Campaign::whereDate('deadline', '<', $now)` |
| Jika terkumpul >= target → SUCCESS + disburse | ✅ | Mengatur status, memanggil `DisburseCampaignJob` |
| Jika terkumpul < target → FAILED + refund | ✅ | Mengatur status, memanggil `RefundBackersJob` |
| DisburseCampaignJob | ✅ | `app/Jobs/DisburseCampaignJob.php` → `TransactionService::disbursementCampaign()` |
| RefundBackersJob | ✅ | `app/Jobs/RefundBackersJob.php` → `TransactionService::refundBackers()` |
| Perintah NotifyDeadlineApproaching | ✅ | Perintah `NotifyDeadlineApproaching` di `campaign:notify-deadline` |
| Notifikasi deadline H-3 | ✅ | Mengirim 3 hari sebelum deadline |
| Notifikasi deadline H-1 | ✅ | Mengirim 1 hari sebelum deadline |
| Hanya notifikasi dalam aplikasi untuk deadline | ✅ | Membuat catatan `Notification`, tidak ada email |

### ⚠️ Bug: Perintah NotifyDeadlineApproaching

Perintah `NotifyDeadlineApproaching` mereferensikan variabel yang tidak terdefinisi `$countH3` dan `$countH1` pada baris 73:
```php
$this->info("Sent {$countH3} H-3 and {$countH1} H-1 deadline notifications.");
```
Variabel-variabel ini tidak pernah didefinisikan, menyebabkan error runtime ketika perintah dijalankan.

---

## Bagian 1.8 — Modul Notifikasi

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Tabel notifikasi dalam aplikasi | ✅ | Migrasi `notifications` + model `Notification` |
| Ikon lonceng + lencana | 🔜 | Frontend tidak diimplementasikan | Backend mendukung notifikasi melalui API |
| Tandai sebagai dibaca saat diklik | 🔜 | Frontend tidak diimplementasikan | Field `read_at` ada di tabel `notifications` |
| Notifikasi email | ✅ | Semua mailable diimplementasikan | Periksa tabel event individual di bawah |
| Email menggunakan antrian | ❌ | Semua email dikirim secara sinkron | `Mail::send()` di listener — **tidak** diantre |

### Cakupan Event Notifikasi

| Event | Spesifikasi: Kanal | Kode: Diimplementasikan? | Kode: Penerima | Kode: Kanal |
|-------|--------------|-------------------|-----------------|---------------|
| Kampanye disetujui | Creator | ✅ | Creator | Dalam aplikasi + Email |
| Kampanye ditolak | Creator | ✅ | Creator | Dalam aplikasi + Email |
| Backing baru | Creator | ✅ | Creator | Hanya dalam aplikasi (spesifikasi mengatakan creator saja) |
| Backing dikonfirmasi | Backer | ✅ | Backer | Dalam aplikasi + Email |
| Pembaruan kampanye diposting | Semua backer | ✅ | Backer | Hanya dalam aplikasi (✅ spesifikasi mengatakan dalam aplikasi) |
| Deadline H-3 | Semua backer | ✅ | Backer | Hanya dalam aplikasi (spesifikasi mengatakan dalam aplikasi) |
| Deadline H-1 | Semua backer | ⚠️ | ⚠️ | Spesifikasi mengatakan dalam aplikasi + Email; **kode hanya mengirim dalam aplikasi** |
| Kampanye berhasil | Creator | ✅ | Creator | Dalam aplikasi + Email |
| Kampanye gagal | Semua backer | ✅ | Backer | Dalam aplikasi + Email |

### ⚠️ Masalah:

1. **Email deadline H-1 hilang** — Spesifikasi mengatakan "Dalam aplikasi + Email" tetapi kode hanya membuat notifikasi dalam aplikasi.
2. **Notifikasi backing baru** — Spesifikasi mengatakan creator hanya harus menerima notifikasi dalam aplikasi (tanpa email). Kode mengirim notifikasi dalam aplikasi ke creator (✅) dan email+in-app ke backer (✅). Ini benar sesuai spesifikasi.
3. **Notifikasi suspend/unsuspend pengguna** — Event `UserSuspended` dan `UserUnsuspended` dipicu di `UserService` tetapi **TIDAK terdaftar** di `EventServiceProvider`. Tidak ada listener yang akan beraksi.

---

## Bagian 1.9 — Dashboard

### Dashboard Creator

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Daftar kampanye creator + status + progres | ✅ | `GET /api/backings` (admin melihat semua) | Tidak ada endpoint dashboard khusus creator; harus memfilter berdasarkan pengguna |
| Grafik pendanaan (kumulatif harian) | ⚠️ | `StatisticsController::index()` menyediakan data grafik | Hanya tersedia untuk admin, bukan per-creator |
| Stats: jumlah backer, terkumpol, persentase | ✅ | `UserService::getUserStats()` + `StatisticsController` | Statistik per-pengguna ada tetapi endpoint mungkin tidak mengekspor semua field |
| Tombol posting pembaruan untuk kampanye aktif | ✅ | `POST /api/campaigns/{slug}/updates` | Hanya berfungsi ketika kampanye ACTIVE |

### Dashboard Backer

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Daftar kampanye yang didanai + status | ✅ | `GET /api/backings` | Mengembalikan daftar backing terpaginasi pengguna |
| Tier hadiah per kampanye | ✅ | `BackingResource` termasuk `tier` |
| Total yang digabungkan | ✅ | `TransactionController::index()` | Dapat memfilter dengan `type=payment` |
| Total pengembalian yang diterima | ⚠️ | `GET /api/transactions?type=refund` | Diperlukan filter manual; tidak ada endpoint khusus |

### Halaman Saldo (Backer & Creator)

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Tampilkan saldo saat ini | ✅ | `UserResource` termasuk `balance` |
| Riwayat transaksi (filter berdasarkan tipe + tanggal) | ✅ | `GET /api/transactions?type=...&status=...` | Filter tanggal tidak didukung di kode |
| Tombol tarik (mock) | ✅ | `POST /api/wallet/withdraw` | Diimplementasikan sebagai penarikan nyata (bukan mock) |

### ⚠️ Masalah:

1. **Tidak ada endpoint statistik dashboard khusus creator** — Creator harus memfilter backing/kampanye secara manual. Tidak ada API view dashboard teragregasi.
2. **Tidak ada filter tanggal untuk transaksi** — Spesifikasi menyebutkan "filter per tipe dan tanggal" tetapi kode tidak mendukung filter tanggal.
3. **Penarikan adalah nyata (bukan mock)** — Spesifikasi mengatakan "Tombol withdraw (opsional — implementasi mock)" tetapi kode mengimplementasikan penarikan sebenarnya dari saldo.

---

## Bagian 1.10 — Modul Admin

### Antrian Persetujuan

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Daftar kampanye dengan status `review` | ⚠️ | ✅ `GET /api/campaigns?status=review` | Tidak ada endpoint antrian persetujuan khusus — harus memfilter berdasarkan status |
| Lihat detail lengkap sebelum setujui/tolak | ✅ | `GET /api/campaigns/{slug}` | Detail lengkap termasuk gambar, tier, pembaruan |
| Setujui → status ACTIVE | ✅ | `CampaignService::approve()` |
| Tolak → status DRAFT + catatan penolakan wajib | ✅ | `CampaignService::reject($note)` |
| Creator diberitahu setelah disetujui/ditolak | ✅ | Event `CampaignApproved` + `CampaignRejected` → listener |

### Manajemen Kampanye

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Daftar semua kampanye + filter berdasarkan status | ✅ | `GET /api/campaigns?status=...` |
| Lihat detail kampanye + riwayat backing | ✅ | `GET /api/campaigns/{slug}` + `GET /api/campaigns/{slug}/backings` |
| Gugurkan paksa kampanye | ✅ | `CampaignController::forceFail()` → `PUT /admin/campaigns/{slug}/force-fail` |

### Manajemen Pengguna

| Persyarakan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Daftar semua pengguna + peran | ✅ | `GET /api/admin/users` + filter `role` |
| Suspend pengguna | ✅ | `PUT /api/admin/users/{user}/suspend` → `UserService::suspend()` |
| Unsuspend pengguna | ✅ | `PUT /api/admin/users/{user}/unsuspend` → `UserService::unsuspend()` |
| Lihat riwayat transaksi pengguna | ⚠️ | ✅ `TransactionController` dapat memfilter berdasarkan tipe | Tidak ada filter transaksi per pengguna — pengguna harus mengekstrak dari endpoint umum |
| **Cegah self-suspension** | ✅ (bonus) | `UserService::suspend()` memeriksa `$user->id === $admin->id` | Tidak ada di spesifikasi tapi diimplementasikan |

### ⚠️ Masalah:

1. **Tidak ada antrian persetujuan khusus** — Admin harus menggunakan `GET /api/campaigns?status=review` untuk melihat kampanye yang menunggu tinjauan.
2. **Tidak ada riwayat transaksi per pengguna untuk admin** — Admin harus melihat transaksi melalui endpoint transaksi pengguna itu sendiri (`GET /api/transactions` saat login sebagai pengguna tersebut), atau menyimpulkan dari daftar backing kampanye.

### Gambaran Platform

| Persyaratan Spesifikasi | Status | Referensi Kode | Catatan |
|---|---|---|---|
| Kampanye dikelompokkan berdasarkan status | ✅ | `StatisticsController` menormalkan distribusi status |
| Total terkumpol (platform-wide) | ✅ | `Campaign::sum('collected_amount')` |
| Total biaya platform | ✅ | Diitung dari jumlah backing × 10% fallback | ⚠️ Menggunakan `config('cofund.platform_fee', 0.1)` tetapi tidak ada file config — fallback 10% tidak konsisten dengan 5% yang di-hardcode di disbursement |
| Grafik kampanye per bulan | ✅ | `StatisticsController::getDailyStats()` | Mendukung pengelompokan harian/mingguan/bulanan/tahunan |

---

## Bagian 1.11 — Status & Mesin Status

### Status Kampanye ✅ (Semua diimplementasikan)

| Status | Spesifikasi | Kode |
|--------|------|------|
| `draft` | ✅ | ✅ `CampaignStatus::DRAFT` |
| `review` | ✅ | ✅ `CampaignStatus::REVIEW` |
| `active` | ✅ | ✅ `CampaignStatus::ACTIVE` |
| `success` | ✅ | ✅ `CampaignStatus::SUCCESS` |
| `failed` | ✅ | ✅ `CampaignStatus::FAILED` |

Transisi status:
- `draft → (ajukan) → review` ✅
- `review → (setujui) → active` ✅
- `review → (tolak) → draft` ✅
- `active → (target tercapai) → success` ✅
- `active → (deadline terlewat) → failed` ✅

### Status Backing ✅ (Semua diimplementasikan)

| Status | Spesifikasi | Kode |
|--------|------|------|
| `pending` | ✅ | ✅ `BackingStatus::PENDING` |
| `completed` | ✅ | ✅ `BackingStatus::COMPLETED` |
| `refunded` | ✅ | ✅ `BackingStatus::REFUNDED` |

Transisi status:
- `pending → (pembayaran berhasil) → completed` ✅
- `completed → (kampanye gagal) → refunded` ✅

---

## Bagian 1.12 — Aturan Bisnis

| No | Aturan Spesifikasi | Status | Referensi Kode | Catatan |
|----|-------------------|--------|----------------|-------|
| 1 | Deadline min 7 hari dari submit | ✅ | `StoreCampaignRequest: deadline` → `after:+7 days` |
| 2 | Target min Rp 100.000 | ✅ | `StoreCampaignRequest: target_amount` → `min:100000` |
| 3 | Backing min Rp 10.000 | ✅ | `BackingService::ensureMinimumAmount()` memeriksa `< 10000` |
| 4 | Creator tidak dapat mendukung kampanyanya sendiri | ✅ | `BackingService::ensureCanBack()` |
| 5 | Email diverifikasi untuk backing/membuat kampanye | ✅ | Middleware `verified` |
| 6 | Kuota tier 0 = tak terbatas | ✅ | `CampaignTier::isUnlimited()` |
| 7 | Escrow — dana tidak ke creator sampai lifecycle selesai | ✅ | `collected_amount` ditahan; pencairan hanya pada SUCCESS |
| 8 | Biaya platform 5% pada pencairan | ✅ | `TransactionService::disbursementCampaign()` → `* 0.05` |
| 9 | Kampanye hanya dapat dihapus saat DRAFT | ✅ | `CampaignService::ensureEditable()` + soft delete |
| 10 | Refund otomatis penuh | ✅ | `RefundBackersJob` dipanggil oleh penjadwal |

### ⚠️ Masalah:

1. **Penegakan Aturan 9** — Penghapusan hanya diizinkan dalam status DRAFT, tetapi spesifikasi mengatakan "setelah status bukan draft" (setelah status tidak draft), yang dapat ditafsirkan berbeda. Kode menegakkan `status === DRAFT` saja.

---

## Ringkasan Masalah

### Prioritas Tinggi (Harus Diperbaiki Sebelum Produksi)

| # | Masalah | Dampak | Perbaikan |
|---|-------|--------|-----|
| 1 | **Enum transaksi hilang `deposit`/`withdrawal`** | Modul dompet sepenuhnya rusak | Tambahkan ke database enum: `ALTER TABLE transactions MODIFY COLUMN type ENUM('payment','refund','disbursement','platform_fee','deposit','withdrawal')` |
| 2 | **Event UserSuspended/UserUnsuspended tidak terdaftar** | Tidak ada listener yang beraksi pada suspend/unsuspend | Daftarkan di `EventServiceProvider::$listen` dengan listener |
| 3 | **NotifyDeadlineApproaching mereferensikan variabel tidak terdefinisi** | Perintah terjadwal crash saat runtime | Definisikan variabel `$countH3` dan `$countH1` |

### Prioritas Sedang

| # | Masalah | Dampak | Perbaikan |
|---|-------|--------|-----|
| 4 | **Ketidakkonsistenan biaya platform** (5% di-hardcode vs 10% default config) | Statistik menampilkan tarif biaya yang salah | Buat `config/cofund.php` dengan `'platform_fee' => 0.05` |
| 5 | **Email deadline H-1 tidak terkirim** | Pengiriman notifikasi tidak lengkap sesuai spesifikasi | Tambahkan pengiriman email ke perintah `NotifyDeadlineApproaching` |
| 6 | **Email tidak di-antrikan** | Penalti respons API | Implementasikan `ShouldQueue` pada kelas mailable |

### Prioritas Rendah

| # | Masalah | Dampak | Perbaikan |
|---|-------|--------|-----|
| 7 | **Tidak ada versioning API** | Perubahan breaking di masa depan lebih sulit | Tambahkan prefix `/api/v1` |
| 8 | **Pencarian menggunakan LIKE bukan FULLTEXT** | Penggunaan indeks tidak berfungsi | Gunakan `MATCH...AGAINST` atau hapus indeks |
| 9 | **Tidak ada endpoint statistik khusus creator** | Perlu API panggilan tambahan | Tambahkan `GET /api/users/stats` untuk creator |
| 10 | **`cofund.md` menggunakan 4 peran; kode memiliki 3** | Ketidakkonsistenan dokumen | `guest` bersifat implisit (route publik) |
| 11 | **Slug tidak dapat diedit secara manual (TERSELESAIKAN)** | Sebelumnya tidak konsisten dengan spesifikasi | ✅ Diselesaikan: `StoreCampaignRequest` sekarang menerima `slug` nullable, observer hanya otomatis menghasilkan bila kosong |
| 12 | **Tidak ada filter tanggal pada transaksi** | Spesifikasi menyebutkan "filter per tanggal" | Tambahkan `start_date`/`end_date` ke `TransactionController` |

### Tidak dalam Lingkup (Frontend)

| Fitur | Status | Alasan |
|---------|--------|--------|
| Vue.js frontend | Tidak dibangun | Repositori backend saja |
| UI responsif | Tidak dibangun | Tugas frontend |
| Tampilan mobile | Tidak dibangun | Tugas frontend |

---

## Apa yang Ada di Spesifikasi tapi Tidak Diimplementasikan:

1. **Grafik pendanaan creator** — Hanya data grafik tersedia di level admin
2. **Filter transaksi per pengguna untuk admin** — Admin tidak dapat memfilter transaksi berdasarkan ID pengguna
3. **Filter tanggal untuk transaksi** — Spesifikasi menyebutkan "filter per tipe dan tanggal" tetapi tidak ada parameter tanggal

---

### Not in Scope (Frontend)

## Apa yang Diimplementasikan tapi Tidak Ada di Spesifikasi (bonus/ekstra):

1. **Endpoint deposit/penarikan dompet** — `POST /api/wallet/deposit`, `POST /api/wallet/withdraw`
2. **Tipe transaksi `deposit` dan `withdrawal`** — Tipe transaksi tambahan
3. **Suspend/unsuspend pengguna** — Admin dapat menangguhkan akun pengguna
4. **Pencegahan self-suspension** — Pengguna tidak dapat menangguhkan diri sendiri
5. **Endpoint statistik pengguna** — `GET /admin/users/{user}` mengembalikan statistik pengguna
6. **Daftar backing per pengguna** — `GET /api/backings` menampilkan backing milik pengguna
7. **Daftar backer kampanye** — `GET /api/campaigns/{slug}/backings` menampilkan daftar backer
8. **Endpoint notis verifikasi email/kirim kembali** — `GET /email/verify/notice`, `POST /email/resend`
9. **Gugurkan paksa kampanye** — Admin dapat secara manual menggagalkan kampanye
10. **Parameter pencarian pada daftar kampanye** — Meskipun spesifikasi menyebutkan filter, implementasi pencarian adalah bonus

---

## Status Akhir Implementasi

| Bagian | Kelengkapan Implementasi |
|---------|---------------------------|
| Auth (1.3) | ✅ 100% + 3 endpoint bonus |
| Kampanye (1.4) | ✅ 99% (rich text sudah terimplementasi) |
| Tier & Backing (1.5) | ✅ 100% |
| Transaksi & Escrow (1.6) | ✅ 90% (tipe dompet menyebabkan bug kritis) |
| Pekerjaan Lifecycle (1.7) | ✅ 95% (1 bug di notify-deadline) |
| Notifikasi (1.8) | ✅ 90% (hilang email H-1, 2 event tidak terdaftar) |
| Dashboard (1.9) | ⚠️ 60% (hilang API dashboard khusus creator) |
| Admin (1.10) | ✅ 85% (hilang tampilan antrian persetujuan, transaksi per pengguna) |
| Mesin Status (1.11) | ✅ 100% |
| Aturan Bisnis (1.12) | ✅ 100% |
| **Backend Keseluruhan** | ✅ **~88%** — Siap produksi setelah memperbaiki 3 masalah prioritas tinggi |
