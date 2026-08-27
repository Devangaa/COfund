# Skema Database & Relasi

Dokumentasi database untuk backend platform crowdfunding CoFund.

**Mesin Database:** MySQL 8.0+  
**Karakter Set:** `utf8mb4`  
**Kolasi:** `utf8mb4_unicode_ci` (default)  
**Strict Mode:** Diaktifkan (default di MySQL 8)

---

## Urutan Migrasi

Migrasi harus dijalankan sesuai urutan nama filenya (Laravel menangani ini secara otomatis melalui tabel `migrations`):

| No | File Migrasi | Tabel | Deskripsi |
|----|---------------|-------|-------------|
| 1 | `2014_10_12_000000_create_users_table.php` | `users` | Akun pengguna |
| 2 | `2014_10_12_100000_create_password_reset_tokens_table.php` | `password_reset_tokens` | Token reset kata sandi |
| 3 | `2019_08_19_000000_create_failed_jobs_table.php` | `failed_jobs` | Job antrian yang gagal |
| 4 | `2019_12_14_000001_create_personal_access_tokens_table.php` | `personal_access_tokens` | Token API Sanctum |
| 5 | `2024_01_01_000001_create_categories_table.php` | `categories` | Kategori kampanye |
| 6 | `2024_01_02_000000_create_campaigns_table.php` | `campaigns` | Kampanye crowdfunding |
| 7 | `2024_01_02_100000_create_campaign_images_table.php` | `campaign_images` | Aset gambar kampanye |
| 8 | `2024_01_03_000000_create_campaign_tiers_table.php` | `campaign_tiers` | Tier hadiah |
| 9 | `2024_01_03_100000_create_campaign_updates_table.php` | `campaign_updates` | Postingan pembaruan kampanye |
| 10 | `2024_01_04_000000_create_backings_table.php` | `backings` | Janji dukungan pengguna |
| 11 | `2024_01_04_100000_create_transactions_table.php` | `transactions` | Catatan transaksi keuangan |
| 12 | `2024_01_05_000000_create_notifications_table.php` | `notifications` | Notifikasi dalam aplikasi |
| 13 | `2026_08_24_063448_add_deleted_at_to_backings_and_transactions_table.php` | — | Kolom soft-delete |
| 14 | `2026_08_26_165000_add_fulltext_search_to_campaigns_table.php` | — | Indeks FULLTEXT |

---

## Skema Tabel

### `users`

Menyimpan akun pengguna di semua peran (backer, creator, admin).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `name` | `varchar(255)` | NOT NULL | — | Nama lengkap |
| `email` | `varchar(255)` | NOT NULL, UNIQUE | — | Alamat email |
| `password` | `varchar(255)` | NOT NULL | — | Password yang dihash |
| `role` | `enum` | NOT NULL | `'backer'` | Salah satu dari: `backer`, `creator`, `admin` |
| `balance` | `decimal(15,2)` | NOT NULL | `0.00` | Saldo dompet |
| `email_verified_at` | `timestamp` | NULLABLE | NULL | Timestamp verifikasi email |
| `is_suspended` | `tinyint(1)` | NOT NULL | `0` | Apakah akun ditangguhkan |
| `suspended_at` | `timestamp` | NULLABLE | NULL | Kapan akun ditangguhkan |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |
| `remember_token` | `varchar(100)` | NULLABLE | NULL | Token "remember me" |

**Indeks:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`email`)

---

### `password_reset_tokens`

Token reset kata sandi Laravel standar.

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `email` | `varchar(255)` | PK | — | Email pengguna |
| `token` | `varchar(255)` | NOT NULL | — | Token reset |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan token |

**Indeks:**
- PRIMARY KEY (`email`)

---

### `failed_jobs`

Menyimpan job antrian yang gagal untuk debug.

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `uuid` | `varchar(255)` | NOT NULL, UNIQUE | — | UUID |
| `connection` | `varchar(255)` | NOT NULL | — | Nama koneksi antrian |
| `queue` | `varchar(255)` | NOT NULL | — | Nama antrian |
| `payload` | `longtext` | NOT NULL | — | Payload yang diserialisasi |
| `exception` | `longtext` | NOT NULL | — | Pengecualian yang diserialisasi |
| `failed_at` | `timestamp` | NOT NULL | CURRENT_TIMESTAMP | Kapan job gagal |

**Indeks:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`uuid`)

---

### `personal_access_tokens`

Penyimpanan token API Sanctum.

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `tokenable_type` | `varchar(255)` | NOT NULL | — | Nama kelas model |
| `tokenable_id` | `bigint UNSIGNED` | NOT NULL | — | ID model |
| `name` | `varchar(255)` | NOT NULL | — | Nama token |
| `token` | `varchar(64)` | NOT NULL, UNIQUE | — | Nilai token yang dihash |
| `abilities` | `text` | NULLABLE | NULL | Array kemampuan JSON |
| `last_used_at` | `timestamp` | NULLABLE | NULL | Timestamp penggunaan terakhir |
| `expires_at` | `timestamp` | NULLABLE | NULL | Kedaluwarsa (saat ini NULL) |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`token`)
- KEY (`tokenable_type`, `tokenable_id`) — polimorfik

---

### `categories`

Kategori kampanye (dapat dihapus).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `name` | `varchar(255)` | NOT NULL | — | Nama kategori |
| `slug` | `varchar(255)` | NOT NULL, UNIQUE | — | Slug ramah URL |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`slug`)

---

### `campaigns`

Kampanye crowdfunding (dapat dihapus).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id, ON DELETE CASCADE | — | Pembuat kampanye |
| `category_id` | `bigint UNSIGNED` | FK → categories.id | — | Kategori kampanye |
| `title` | `varchar(100)` | NOT NULL | — | Judul kampanye |
| `slug` | `varchar(255)` | NOT NULL, UNIQUE | — | Slug ramah URL |
| `description` | `text` | NOT NULL | — | Deskripsi kampanye |
| `target_amount` | `decimal(15,2)` | NOT NULL | — | Target pendanaan |
| `collected_amount` | `decimal(15,2)` | NOT NULL | `0.00` | Terkumpol sejauh ini |
| `deadline` | `date` | NOT NULL | — | Deadline kampanye |
| `status` | `enum` | NOT NULL | `draft` | Salah satu dari: `draft`, `review`, `active`, `success`, `failed` |
| `video_url` | `varchar(255)` | NULLABLE | NULL | URL embed video |
| `rejection_note` | `text` | NULLABLE | NULL | Alasan penolakan admin |
| `reviewed_by` | `bigint UNSIGNED` | FK → users.id | NULL | Admin yang meninjau |
| `reviewed_at` | `timestamp` | NULLABLE | NULL | Timestamp tinjauan |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`slug`)
- FOREIGN KEY (`user_id`) ON DELETE CASCADE
- FOREIGN KEY (`category_id`)
- FOREIGN KEY (`reviewed_by`)
- FULLTEXT KEY (`fulltext_search`) pada (`title`, `description`) — ditambahkan di migrasi 14

---

### `campaign_images`

Aset gambar kampanye (dapat dihapus).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, ON DELETE CASCADE | — | Kampanye pemilik |
| `url` | `varchar(255)` | NOT NULL | — | Path berkas di disk |
| `is_primary` | `tinyint(1)` | NOT NULL | `0` | Apakah gambar utama |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`campaign_id`) ON DELETE CASCADE

---

### `campaign_tiers`

Tier hadiah untuk kampanye (dapat dihapus).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, ON DELETE CASCADE | — | Kampanye pemilik |
| `name` | `varchar(255)` | NOT NULL | — | Nama tier |
| `min_amount` | `decimal(15,2)` | NOT NULL | — | Jumlah dukungan minimum |
| `quota` | `integer` | NOT NULL | `0` | Maksimum backer (0 = tak terbatas) |
| `remaining_quota` | `integer` | NOT NULL | `0` | Slot yang tersisa |
| `reward_description` | `text` | NULLABLE | NULL | Deskripsi hadiah |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`campaign_id`) ON DELETE CASCADE

---

### `campaign_updates`

Postingan pembaruan kampanye (dapat dihapus).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, ON DELETE CASCADE | — | Kampanye pemilik |
| `title` | `varchar(255)` | NOT NULL | — | Judul pembaruan |
| `content` | `text` | NOT NULL | — | Konten pembaruan |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`campaign_id`) ON DELETE CASCADE

---

### `backings`

Janji dukungan pengguna (dapat dihapus).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id | — | Pengguna yang mendukung |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id | — | Kampanye yang didukung |
| `tier_id` | `bigint UNSIGNED` | FK → campaign_tiers.id, NULLABLE | NULL | Tier hadiah (jika ada) |
| `amount` | `decimal(15,2)` | NOT NULL | — | Jumlah backing |
| `status` | `enum` | NOT NULL | `pending` | Salah satu dari: `pending`, `completed`, `refunded` |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete — ditambahkan di migrasi 13 |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`user_id`)
- FOREIGN KEY (`campaign_id`)
- FOREIGN KEY (`tier_id`)

---

### `transactions`

Catatan transaksi keuangan (dapat dihapus).

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id | — | Pengguna pemilik |
| `backing_id` | `bigint UNSIGNED` | FK → backings.id, NULLABLE | NULL | Backing (jika terkait) |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, NULLABLE | NULL | Kampanye (jika terkait) |
| `type` | `enum` | NOT NULL | `pending` | Salah satu dari: `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal` |
| `amount` | `decimal(15,2)` | NOT NULL | — | Jumlah transaksi |
| `status` | `enum` | NOT NULL | `pending` | Salah satu dari: `pending`, `success`, `failed` |
| `reference` | `varchar(255)` | NULLABLE | NULL | Referensi eksternal (ID gerbang pembayaran) |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete — ditambahkan di migrasi 13 |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**⚠️ Masalah Kritis:** Migrasi untuk kolom `type` hanya mendefinisikan: `ENUM('payment', 'refund', 'disbursement', 'platform_fee')`. Nilai `deposit` dan `withdrawal` **hilang** dari database enum, meskipun ada di enum `TransactionType` PHP. Di bawah mode strict MySQL, INSERT dengan `type = 'deposit'` atau `'withdrawal'` akan gagal.

**Indeks:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`user_id`)
- FOREIGN KEY (`backing_id`)
- FOREIGN KEY (`campaign_id`)

---

### `notifications`

Notifikasi dalam aplikasi untuk pengguna.

| Kolom | Tipe | Kendala | Default | Deskripsi |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id, ON DELETE CASCADE | — | Pengguna penerima |
| `type` | `varchar(255)` | NOT NULL | — | Tipe notifikasi (mis., `campaign_approved`, `backing_created`, `deposit`, `withdrawal`) |
| `title` | `varchar(255)` | NOT NULL | — | Judul notifikasi |
| `body` | `text` | NOT NULL | — | Isi notifikasi |
| `data` | `json` | NULLABLE | NULL | Data JSON tambahan |
| `read_at` | `timestamp` | NULLABLE | NULL | Kapan notifikasi dibaca |
| `created_at` | `timestamp` | NULLABLE | NULL | Timestamp pembuatan |
| `updated_at` | `timestamp` | NULLABLE | NULL | Timestamp pembaruan |

**Indeks:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`user_id`) ON DELETE CASCADE

---

## Ringkasan Constraint Kunci Asing

| Tabel Anak | Kolom | Tabel Induk | Kolom Induk | On Delete | On Update |
|------------|--------|--------------|---------------|-----------|-----------|
| `campaigns` | `user_id` | `users` | `id` | CASCADE | RESTRICT |
| `campaigns` | `category_id` | `categories` | `id` | RESTRICT | RESTRICT |
| `campaigns` | `reviewed_by` | `users` | `id` | SET NULL | RESTRICT |
| `campaign_images` | `campaign_id` | `campaigns` | `id` | CASCADE | RESTRICT |
| `campaign_tiers` | `campaign_id` | `campaigns` | `id` | CASCADE | RESTRICT |
| `campaign_updates` | `campaign_id` | `campaigns` | `id` | CASCADE | RESTRICT |
| `backings` | `user_id` | `users` | `ids` | RESTRICT | RESTRICT |
| `backings` | `campaign_id` | `campaigns` | `id` | RESTRICT | RESTRICT |
| `backings` | `tier_id` | `campaign_tiers` | `id` | SET NULL | RESTRICT |
| `transactions` | `user_id` | `users` | `id` | RESTRICT | RESTRICT |
| `transactions` | `backing_id` | `backings` | `id` | SET NULL | RESTRICT |
| `transactions` | `campaign_id` | `campaigns` | `id` | SET NULL | RESTRICT |
| `notifications` | `user_id` | `users` | `id` | CASCADE | RESTRICT |

> **Catatan tentang perilaku FK:** Berbeda dengan kebanyakan default Laravel, sebagian besar foreign key di proyek ini menggunakan `RESTRICT` (no action) pada delete — artinya catatan terkait mencegah penghapusan induk. Hanya `campaign_images`, `campaign_tiers`, `campaign_updates` yang menggunakan `CASCADE` (penghapusan cascades), dan `notifications` menggunakan `CASCADE`.

## Peta Relasi Eloquent

```
User
├── hasMany → Campaigns (sebagai creator)
├── hasMany → Backings (sebagai backer)
├── hasMany → Transactions
├── hasMany → Notifications
└── hasMany → Campaigns (sebagai reviewer, melalui reviewed_by)

Campaign
├── belongsTo → User (creator)
├── belongsTo → Category
├── belongsTo → User (reviewer)
├── hasMany → CampaignImages
├── hasMany → CampaignTiers
├── hasMany → CampaignUpdates
├── hasMany → Backings
└── hasMany → Transactions

CampaignTier
└── belongsTo → Campaign
    └── hasMany → Backings

Backings
├── belongsTo → User
├── belongsTo → Campaign
└── belongsTo → CampaignTier

Transaction
├── belongsTo → User
├── belongsTo → Backing
└── belongsTo → Campaign

Category
└── hasMany → Campaigns

Notification
└── belongsTo → User
```

## Referensi Kolom Enum

### `users.role`

| Nilai | Deskripsi |
|-------|-------------|
| `backer` | Peran default; dapat menelusuri, mendukung, deposit, menarik |
| `creator` | Dapat membuat dan mengelola kampanye |
| `admin` | Akses penuh; dapat memoderasi kampanye, mengelola pengguna |

### `campaigns.status`

| Nilai | Deskripsi |
|-------|-------------|
| `draft` | Keadaan awal setelah pembuatan |
| `review` | Diajukan untuk tinjauan admin |
| `active` | Diterbitkan, menerima backing |
| `success` | Mencapai target pendanaan |
| `failed` | Deadline lewat tanpa mencapai target |

### `backings.status`

| Nilai | Deskripsi |
|-------|-------------|
| `pending` | Backing dibuat namun belum final |
| `completed` | Pembayaran dikonfirmasi |
| `refunded` | Backing dikembalikan (kampanye gagal) |

### `transactions.type` ⚠️

| Nilai | Deskripsi | DB Enum? |
|-------|-------------|----------|
| `payment` | Pembayaran backing | ✅ Ya |
| `disbursement` | Dana kampanye dilepaskan ke creator | ✅ Ya |
| `refund` | Jumlah backer yang dikembalikan | ✅ Ya |
| `platform_fee` | Komisi platform | ✅ Ya |
| `deposit` | Deposit dompet pengguna | ❌ **HILANG** |
| `withdrawal` | Penarikan dompet pengguna | ❌ **HILANG** |

### `transactions.status`

| Nilai | Deskripsi |
|-------|-------------|
| `pending` | Transaksi tertunda |
| `success` | Transaksi berhasil |
| `failed` | Transaksi gagal |

## Aturan Penebakan FK

Perintah `Back` Laravel (untuk menghasilkan model) menggunakan konvensi berikut untuk menebak relasi:

1. **Foreign Key Discovery**: Melihat nama constraint FK
   - `campaigns_user_id_foreign` → model `User`
   - `campaigns_category_id_foreign` → model `Category`

2. **Relationship Type Guessing**:
   - `hasMany`: Jika nama foreign key mengandung nama kelas model induk
   - `belongsTo`: Kebalikan dari `hasMany`
   - `hasOne`: Sama seperti `hasMany` tapi tunggal
   - `belongsToMany`: Jika tabel pivot ada dengan pola `_<related>_id`

3. **Pemetaan Kolom ke Model**:
   - Akhiran `_id` + nama model yang diketahui → menggunakan model tersebut
   - Akhiran tidak dikenal → menebak berdasarkan nama kolom

## Soft Deletes

Tabel berikut menggunakan soft delete (kolom `deleted_at` + trait `SoftDeletes`):

| Tabel | Model |
|-------|-------|
| `categories` | `Category` |
| `campaigns` | `Campaign` |
| `campaign_images` | `CampaignImage` |
| `campaign_tiers` | `CampaignTier` |
| `campaign_updates` | `CampaignUpdate` |
| `backings` | `Backing` |
| `transactions` | `Transaction` |

> **Catatan:** Tabel `users` dan `notifications` **tidak** menggunakan soft delete. Menghapus pengguna akan menghapus rekaman secara permanen, dan backing, transaksi, serta notifikasi pengguna akan menjadi terasing.

## Daftar Berkas

| Path | Deskripsi |
|------|-------------|
| `database/migrations/` | Semua 14 file migrasi |
| `database/seeders/` | 6 file seeder + `DatabaseSeeder.php` |
| `database/factories/` | 1 file factory (`UserFactory.php`) |

### Seeder

| File | Membuat |
|------|---------|
| `CategorySeeder.php` | 6 kategori (Teknologi, Seni, Lingkungan, Sosial, Pendidikan, Kesehatan) |
| `UserSeeder.php` | 4 pengguna (backer, creator1, creator2, admin) — semua password: `password` |
| `CampaignSeeder.php` | 2 kampanye (1 per creator) dengan gambar utama |
| `CampaignTierSeeder.php` | 3 tier total (2 untuk creator1, 1 untuk creator2) |
| `BackingSeeder.php` | 3 backing dengan `collected_amount` yang disesuaikan secara manual |

### Factory

| File | Model | Digunakan Oleh |
|------|-------|---------|
| `UserFactory.php` | `User` | `UserFactory` — digunakan oleh panggilan `User::factory()` (saat ini tidak ada) |
