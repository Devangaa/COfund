# API Modul Admin

Endpoint administratif untuk manajemen pengguna, moderasi kampanye, dan statistik platform.

## Arsitektur

Modul admin hanya dapat diakses oleh pengguna dengan peran `admin`. Menyediakan:
- Manajemen pengguna (daftar, lihat, suspend, unsuspend)
- Moderasi kampanye (setujui, tolak, gugurkan paksa)
- Statistik platform (jumlah pengguna, metrik kampanye, ringkasan keuangan)

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controllers | `app/Http/Controllers/Api/Admin/{UserController, StatisticsController}.php` | Manajemen pengguna admin + moderasi kampanye |
| Moderasi Kampanye | `app/Http/Controllers/Api/CampaignController.php` | Metode approve, reject, force-fail |
| Services | `app/Services/{UserService, CampaignService, TransactionService}.php` | Logika bisnis untuk aksi admin |
| Requests | `app/Http/Requests/SubmitCampaignReviewRequest.php` | Validasi untuk aksi tinjauan |
| Resources | `app/Http/Resources/{UserResource, CampaignResource}.php` | Pemformatan respons JSON |
| Events | `app/Events/{UserSuspended, UserUnsuspended}.php` | Dipicu untuk perubahan status pengguna |

### Alur

```
Admin → auth:sanctum + role:admin → Controller Admin
      → Metode service (setujui/tolak/suspend/unsuspend)
      → DB Transaction → Trigger Event → Listener → Notifikasi
      → Statistik: kueri agregat → kembalikan metrik
```

## Struktur File

```
app/
├── Http/Controllers/Api/
│   ├── CampaignController.php (approve, reject, force-fail)
│   └── Admin/
│       ├── UserController.php
│       └── StatisticsController.php
├── Services/
│   ├── UserService.php
│   ├── CampaignService.php
│   └── TransactionService.php
└── Http/Requests/SubmitCampaignReviewRequest.php
```

## API Endpoints

### Moderasi Kampanye

#### 1. Setujui Kampanye

Mentransisikan kampanye dari REVIEW ke status ACTIVE.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/approve`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Menyetujui kampanye dalam status review.

##### Respons (Sukses: 200)

```json
{
  "message": "Campaign approved",
  "campaign": { ... full CampaignResource ... }
}
```

##### Efek Samping

- `status` → `active`
- `reviewed_by` → ID admin
- `reviewed_at` → timestamp saat ini
- Memicu event `CampaignApproved` → membuat notifikasi dalam aplikasi + mengirim email ke creator

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |
| 404 | Campaign not found | Slug tidak valid |

---

#### 2. Tolak Kampanye

Menolak kampanye, memindahkan dari REVIEW kembali ke DRAFT.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/reject`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Menolak pengajuan kampanye dengan alasan.

##### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `rejection_note` | string | Ya | `required` | Alasan penolakan |

##### Contoh Request

```json
{
  "rejection_note": "Campaign description is too vague."
}
```

##### Respons (Sukses: 200)

```json
{
  "message": "Campaign rejected",
  "campaign": { ... full CampaignResource ... }
}
```

##### Efek Samping

- `status` → `draft`
- `rejection_note` → diatur
- `reviewed_by` → ID admin
- `reviewed_at` → timestamp saat ini
- Memicu event `CampaignRejected` → membuat notifikasi dalam aplikasi + mengirim email ke creator

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |
| 404 | Campaign not found | Slug tidak valid |

---

#### 3. Gugurkan Paksa Kampanye

Menandai kampanye sebagai gagal secara manual.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/force-fail`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Memaksa kampanye ke status gagal.

##### Respons (Sukses: 200)

```json
{
  "message": "Campaign marked as failed"
}
```

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |

---

### Manajemen Pengguna

#### 4. Daftar Pengguna

Mengembalikan daftar pengguna yang dipaginasi.

**Endpoint:** `GET /api/admin/users`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Mencantumkan pengguna dengan opsi filter.

##### Parameter Kueri

| Parameter | Tipe | Wajib | Default | Deskripsi |
|-----------|------|----------|---------|-------------|
| `page` | integer | Tidak | 1 | Nomor halaman |
| `per_page` | integer | Tidak | 15 | Jumlah item per halaman |
| `role` | string | Tidak | — | Filter berdasarkan peran (`backer`, `creator`, `admin`) |
| `is_suspended` | boolean | Tidak | — | Filter berdasarkan status penangguhan |
| `search` | string | Tidak | — | Cari berdasarkan nama atau email |

##### Contoh Request

```
GET /api/admin/users?role=creator&is_suspended=0&search=ali
```

##### Respons (Sukses: 200)

```json
{
  "data": [
    {
      "id": 2,
      "name": "Ali Creator",
      "email": "ali@example.com",
      "role": "creator",
      "balance": "500000.00",
      "email_verified_at": "2026-08-24T10:00:00.000000Z",
      "is_suspended": false,
      "backings_count": 0,
      "created_at": "2026-08-24T10:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |

---

#### 5. Dapatkan Detail Pengguna

Mengembalikan informasi rinci tentang pengguna tertentu, termasuk statistik.

**Endpoint:** `GET /api/admin/users/{user}`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Mengambil detail dan statistik satu pengguna.

##### Respons (Sukses: 200)

```json
{
  "user": {
    "id": 2,
    "name": "Ali Creator",
    "email": "ali@example.com",
    "role": "creator",
    "balance": "500000.00",
    "email_verified_at": "2026-08-24T10:00:00.000000Z",
    "is_suspended": false,
    "created_at": "2026-08-24T10:00:00.000000Z"
  },
  "stats": {
    "backings_count": 3,
    "campaigns_count": 2,
    "total_spent": "300000.00",
    "total_contributed": "500000.00"
  }
}
```

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |
| 404 | User not found | ID pengguna tidak valid |

---

#### 6. Suspend Pengguna

Menangguhkan akun pengguna.

**Endpoint:** `PUT /api/admin/users/{user}/suspend`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Menangguhkan pengguna, mencegah login dan transaksi dompet.

##### Body Permintaan

| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|----------|-------------|
| `reason` | string | Tidak | Alasan penangguhan |

##### Contoh Request

```json
{
  "reason": "Violated platform terms of service"
}
```

##### Respons (Sukses: 200)

```json
{
  "message": "User suspended successfully",
  "user": {
    "id": 3,
    "name": "Test User",
    "email": "test@example.com",
    "role": "backer",
    "is_suspended": true,
    "suspended_at": "2026-08-26T10:00:00.000000Z"
  }
}
```

##### Efek Samping

- Mengatur `is_suspended = true`, `suspended_at = now()`
- Memicu event `UserSuspended` (⚠️ tidak terdaftar di `EventServiceProvider`)

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |
| 422 | You cannot suspend yourself | Admin mencoba menangguhkan akunnya sendiri |

---

#### 7. Unsuspend Pengguna

Mengaktifkan kembali akun pengguna yang sebelumnya ditangguhkan.

**Endpoint:** `PUT /api/admin/users/{user}/unsuspend`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Membatalkan penangguhan pengguna.

##### Respons (Sukses: 200)

```json
{
  "message": "User unsuspended successfully",
  "user": {
    "id": 3,
    "name": "Test User",
    "is_suspended": false,
    "suspended_at": null
  }
}
```

##### Efek Samping

- Mengatur `is_suspended = false`, `suspended_at = null`
- Memicu event `UserUnsuspended` (⚠️ tidak terdaftar di `EventServiceProvider`)

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |
| 404 | User not found | ID pengguna tidak valid |

---

### Statistik Platform

#### 8. Dapatkan Statistik Platform

Mengembalikan statistik platform yang digregasikan secara keseluruhan.

**Endpoint:** `GET /api/admin/statistics`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Mengembalikan metrik platform komprehensif termasuk jumlah pengguna, metrik kampanye, ringkasan keuangan, grafik, dan kampanye teratas.

##### Parameter Kueri

| Parameter | Tipe | Wajib | Default | Deskripsi |
|-----------|------|----------|---------|-------------|
| `period` | string | Tidak | `7d` | Periode waktu untuk data grafik: `7d`, `30d`, `90d`, `all` |
| `start_date` | date | Tidak | — | Tanggal mulai kustom (YYYY-MM-DD) |
| `end_date` | date | Tidak | — | Tanggal akhir kustom (YYYY-MM-DD) |

##### Contoh Request

```
GET /api/admin/statistics?period=30d
```

##### Respons (Sukses: 200)

```json
{
  "users": {
    "total": 150,
    "by_role": {
      "backer": 90,
      "creator": 50,
      "admin": 10
    }
  },
  "campaigns": {
    "total": 42,
    "status_distribution": {
      "active": 25,
      "draft": 5,
      "review": 3,
      "success": 6,
      "failed": 3
    },
    "total_target": "100000000.00",
    "total_collected": "65000000.00"
  },
  "backings": {
    "total": 180,
    "total_amount": "65000000.00"
  },
  "fees": {
    "total_platform_fee": "3250000.00",
    "rate": "10%"
  },
  "chart_data": [
    {
      "date": "2026-08-20",
      "backings": 5,
      "amount": "5000000.00",
      "campaigns": 2
    },
    {
      "date": "2026-08-21",
      "backings": 3,
      "amount": "3000000.00",
      "campaigns": 0
    }
  ],
  "top_campaigns": [
    {
      "id": 1,
      "title": "Top Campaign",
      "slug": "top-campaign",
      "creator_name": "Creator Name",
      "target_amount": "5000000.00",
      "collected_amount": "4500000.00",
      "progress_percentage": 90,
      "backings_count": 150,
      "status": "active"
    }
  ]
}
```

##### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan admin |

## Skema Sumber Daya Pengguna (Admin)

```json
{
  "id": 2,
  "name": "Ali Creator",
  "email": "ali@example.com",
  "role": "creator",
  "balance": "500000.00",
  "email_verified_at": "2026-08-24T10:00:00.000000Z",
  "is_suspended": false,
  "backings_count": 0,
  "created_at": "2026-08-24T10:00:00.000000Z"
}
```

## Pengujian Postman

### Skrip Pengujian (Admin)

#### Pengaturan: Login sebagai Admin

```
POST {{base_url}}/login
{
  "email": "admin@example.com",
  "password": "password123"
}
```

Simpan `token` yang dikembalikan ke `{{admin_token}}`.

#### Pengujian 1: Daftar Semua Pengguna

1. Atur permintaan: `GET {{base_url}}/admin/users`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan daftar pengguna terpaginasi.

#### Pengujian 2: Filter Pengguna Berdasarkan Peran

1. Atur permintaan: `GET {{base_url}}/admin/users?role=creator`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` hanya dengan pengguna creator.

#### Pengujian 3: Cari Pengguna

1. Atur permintaan: `GET {{base_url}}/admin/users?search=ali`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan pengguna yang cocok "ali" di nama atau email.

#### Pengujian 4: Dapatkan Detail Pengguna

1. Atur permintaan: `GET {{base_url}}/admin/users/2`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan info pengguna + statistik.

#### Pengujian 5: Suspend Pengguna

1. Atur permintaan: `PUT {{base_url}}/admin/users/3/suspend`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Body:
   ```json
   {
     "reason": "Test suspension"
   }
   ```
4. Diperkirakan: `200 OK` dengan `is_suspended = true`.

#### Pengujian 6: Unsuspend Pengguna

1. Atur permintaan: `PUT {{base_url}}/admin/users/3/unsuspend`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan `is_suspended = false`.

#### Pengujian 7: Akses Endpoing Admin sebagai Non-Admin

1. Atur permintaan: `GET {{base_url}}/admin/users`
2. Header: `Authorization: Bearer {{backer_token}}`
3. Diperkirakan: `403 Forbidden`.

#### Pengujian 8: Dapatkan Statistik Platform

1. Atur permintaan: `GET {{base_url}}/admin/statistics`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan objek statistik lengkap.

#### Pengujian 9: Dapatkan Statistik dengan Periode Kustom

1. Atur permintaan: `GET {{base_url}}/admin/statistics?period=30d`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan data grafik 30 hari.

#### Pengujian 10: Setujui Kampanye (Admin)

1. Atur permintaan: `PUT {{base_url}}/admin/campaigns/test-campaign/approve`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan status=active.

#### Pengujian 11: Tolak Kampanye (Admin)

1. Atur permintaan: `PUT {{base_url}}/admin/campaigns/test-campaign/reject`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Body:
   ```json
   {
     "rejection_note": "Content needs improvement"
   }
   ```
4. Diperkirakan: `200 OK` dengan status=draft + rejection_note diatur.

#### Pengujian 12: Coba Suspend Diri Sendiri

1. Atur permintaan: `PUT {{base_url}}/admin/users/{admin_id}/suspend`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `422` — "You cannot suspend yourself".

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Daftar pengguna (admin) | Token admin | 200 + daftar pengguna terpaginasi |
| 2 | Filter pengguna berdasarkan peran | `role=backer` | 200 + daftar yang disaring |
| 3 | Filter pengguna berdasarkan penangguhan | `is_suspended=1` | 200 + hanya pengguna yang ditangguhkan |
| 4 | Cari pengguna | `search=test` | 200 + pengguna yang cocok |
| 5 | Dapatkan detail pengguna | ID pengguna | 200 + pengguna + statistik |
| 6 | Dapatkan pengguna tidak ada | ID tidak valid | 404 tidak ditemukan |
| 7 | Suspend pengguna (admin) | ID pengguna + reason | 200 + is_suspended=true |
| 8 | Suspend diri sendiri | ID admin sendiri | 422 + "You cannot suspend yourself" |
| 9 | Unsuspend pengguna (admin) | ID pengguna yang ditangguhkan | 200 + is_suspended=false |
| 10 | Akses admin sebagai backer | Token backer | 403 dilarang |
| 11 | Akses admin sebagai tidak terotentikasi | Tidak ada token | 401 tidak terotentikasi |
| 12 | Dapatkan statistik | Token admin | 200 + objek statistik lengkap |
| 13 | Dapatkan statistik dengan periode | `period=30d` | 200 + data grafik 30 hari |
| 14 | Dapatkan statistik dengan rentang tanggal | `start_date=...&end_date=...` | 200 + data rentang tanggal kustom |
| 15 | Setujui kampanye (admin) | Slug kampanye | 200 + status=active |
| 16 | Tolak kampanye (admin) | slug + rejection_note | 200 + status=draft + note |
| 17 | Gugurkan paksa kampanye (admin) | Slug kampanye | 200 + pesan |

## Pemecahan Masalah

### 1. "You cannot suspend yourself"

Admin tidak dapat menangguhkan akunnya sendiri. Ini adalah pemeriksaan keamanan di `UserService::suspend()`.

**Perbaikan:** Gunakan akun admin yang berbeda, atau terapkan mekanisme terpisah untuk non-aktivasi diri.

---

### 2. Statistik Menampilkan Biaya Platform 10% (bukan 5%)

`StatisticsController` menggunakan `config('cofund.platform_fee', 0.1)` untuk menampilkan tarif biaya platform. Namun, **tidak ada file `config/cofund.php`**, sehingga selalu mengembalikan fallback `0.1` (10%). Sementara itu, `TransactionService::disburseCampaign()` menggunakan **biaya 5% yang di-hardcode**.

Ini adalah ketidakkonsistenan yang diketahui. Biaya sebenarnya yang diterapkan saat pencairan adalah 5%, tetapi tampilan statistik menampilkan 10%.

**Perbaikan:** Buat `config/cofund.php`:
```php
// config/cofund.php
return [
    'platform_fee' => 0.05, // 5%
];
```

---

### 3. Event UserSuspended/UserUnsuspended Tidak Memicu Listener

Event `UserSuspended` dan `UserUnsuspended` dipancarkan di `UserService::suspend()` dan `UserService::unsuspend()`, tetapi **TIDAK terdaftar** di `EventServiceProvider::$listen`. Karena `shouldDiscoverEvents()` mengembalikan `false`, tidak akan terjadi auto-discovery.

**Perbaikan:** Daftarkan kedua event di `app/Providers/EventServiceProvider.php`:
```php
use App\Events\UserSuspended;
use App\Events\UserUnsuspended;
use App\Listeners\HandleUserSuspended;
use App\Listeners\HandleUserUnsuspended;

protected $listen = [
    // ... pemetaan yang ada ...
    UserSuspended::class => [
        HandleUserSuspended::class,
    ],
    UserUnsuspended::class => [
        HandleUserUnsuspended::class,
    ],
];
```

Anda juga perlu membuat kelas listener `HandleUserSuspended` dan `HandleUserUnsuspended`.

---

### 4. Admin yang Mencoba Menyetujui Kampanye Non-REVIEW

Jika mencoba menyetujui kampanye yang tidak dalam status REVIEW, kampanye akan tetap diatur ke ACTIVE, tetapi alur mungkin tidak logis.

**Perbaikan:** Tambahkan pemeriksaan status di `CampaignService::approve()`:
```php
if ($campaign->status !== CampaignStatus::REVIEW) {
    throw new ConflictHttpException('Campaign is not in review status');
}
```

---

### 5. Endpoing Statistik Mengembalikan Data yang Usang

Statistik dihitung secara real-time menggunakan kueri agregat. Jika caching diterapkan nanti, ingat untuk menghapus cache saat:
- Pengguna dibuat/di-perbarui
- Kampanye dibuat/di-perbarui
- Backing dibuat
- Transaksi dibuat
- Kampanye mencapai success/failure

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Daftar pengguna | Admin | `auth:sanctum, role:admin` |
| Dapatkan detail pengguna | Admin | `auth:sanctum, role:admin` |
| Suspend pengguna | Admin | `auth:sanctum, role:admin` |
| Unsuspend pengguna | Admin | `auth:sanctum, role:admin` |
| Setujui kampanye | Admin | `auth:sanctum, role:admin` |
| Tolak kampanye | Admin | `auth:sanctum, role:admin` |
| Gugurkan paksa kampanye | Admin | `auth:sanctum, role:admin` |
| Lihat statistik | Admin | `auth:sanctum, role:admin` |
