# CoFund API - Modul Backer (Backer Module)

## 1. Judul & Deskripsi Modul

Modul ini menyediakan endpoint statistik untuk pengguna dengan peran **backer** (donatur). Statistik mencakup total dana yang didanai, total refund, jumlah backing, dan jumlah kampanye yang didukung.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/Backer/BackerStatisticsController.php` | Endpoint statistik backer |
| **Model** | `app/Models/Backing.php` | Model backing dengan relasi campaign |
| **Enums** | `app/Enums/BackingStatus.php` | `pending`, `completed`, `refunded` |
| **Middleware** | `auth:sanctum`, `verified` | Otentikasi dan verifikasi email |

### Alur Proses Logika Bisnis

```
Backer login
        |
        v
BackerStatisticsController::index()
        |
        v
Get user from request
        |
        v
Load backings with campaign
        |
        +---> total_backed (sum of COMPLETED backings)
        +---> total_refunded (sum of REFUNDED backings)
        +---> total_backings (count)
        +---> total_campaigns_backed (distinct campaign_id)
        |
        v
Return JSON response
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── Backer/
│   │   │           └── BackerStatisticsController.php
│   └── Models/
│       ├── Backing.php
│       ├── User.php
│       └── Enums/
│           └── BackingStatus.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Index Backer Statistics

- **Deskripsi:** Mendapatkan statistik dukungan (backing) untuk backer yang sedang login.
- **HTTP Method & URL Path:** `GET /api/v1/backer/statistics`
- **Middleware:** `auth:sanctum`, `verified`

#### Contoh Request

```
GET /api/v1/backer/statistics
Authorization: Bearer {token}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": {
        "total_backed": 2500000,
        "total_refunded": 500000,
        "total_backings": 15,
        "total_campaigns_backed": 8
    }
}
```

#### Deskripsi Field

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `total_backed` | decimal | Total dana yang didanai (backing status `completed`) |
| `total_refunded` | decimal | Total dana yang dikembalikan (backing status `refunded`) |
| `total_backings` | integer | Jumlah total backing |
| `total_campaigns_backed` | integer | Jumlah kampanye unik yang didukung |

#### Side Effects

- Read-only query
- Menjumlahkan backing dengan status `completed` dan `refunded`
- Menghitung kampanye unik yang didukung melalui distinct `campaign_id`

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 403 | `{"success":false,"message":"The user must verify their email."}` | Email belum diverifikasi |

---

## 5. Skema Sumber Daya (Resource Schema)

### Backer Statistics Response Schema

```json
{
    "total_backed": 2500000.00,
    "total_refunded": 500000.00,
    "total_backings": 15,
    "total_campaigns_backed": 8
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `total_backed` | decimal(15,2) | Total dana didanai (completed) |
| `total_refunded` | decimal(15,2) | Total dana dikembalikan (refunded) |
| `total_backings` | integer | Jumlah backing |
| `total_campaigns_backed` | integer | kampanye unik yang didukung |

---

## 6. Pengujian Postman

### Backer Statistics

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/backer/statistics`
3. Headers: `Authorization: Bearer {{auth_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Required fields exist", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.total_backed).to.be.a("number");
    pm.expect(jsonData.data.total_backings).to.be.a("number");
    pm.expect(jsonData.data.total_campaigns_backed).to.be.a("number");
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | Get backer statistics | GET /backer/statistics | Statistik backing backer |
| 2 | Backer tanpa backing | Login backer baru | Semua nilai 0 |
| 3 | Backer setelah backing | Setelah membuat backing | total_backings bertambah |
| 4 | Backer setelah refund | Setelah kampanye gagal | total_refunded bertambah |
| 5 | Unauthenticated | Tanpa token | HTTP 401, "Unauthenticated" |
| 6 | Creator mengakses | Login sebagai creator | HTTP 200 (endpoint tidak menge-check role) |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| Total nilainya 0 meskipun pernah backing | Pastikan backing memiliki status `completed`. Backing dengan status `pending` tidak dihitung. |
| `total_campaigns_backed` tidak akurat | Nilai ini dihitung dengan `distinct('campaign_id')->count('campaign_id')` pada query backing. |
| Creator bisa mengakses endpoint ini | Endpoint ini tidak mengecek role secara eksplisit. Jika ingin dibatasi untuk backer saja, tambahkan middleware `role:backer`. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /api/v1/backer/statistics` | - | ✓ | ✓* | ✓* |

> *Catatan:* Endpoint ini tidak mengecek role, hanya membutuhkan autentikasi dan verifikasi email. Semua peran terautentikasi dapat mengakses.

---

## 10. Matriks Kasus Pengujian (Test Case)

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-BACKER-001` | Get statistik backer | Positive | Auth user, email verified | `200 OK` | Statistik lengkap |
| `TC-BACKER-002` | Get statistik tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-BACKER-003` | Get statistik dengan email belum verified | Security | Email belum verified | `403 Forbidden` | Error "Email verification required" |
| `TC-BACKER-004` | Get statistik sebagai creator | Positive | Login sebagai creator | `200 OK` | Bisa akses (role tidak dibatas) |
| `TC-BACKER-005` | Get statistik sebagai admin | Positive | Login admin | `200 OK` | Bisa akses (role tidak dibatas) |
| `TC-BACKER-006` | Backer belum pernah backing | Positive | User baru, belum ada backing | `200 OK` | Semua nilai 0, empty |
| `TC-BACKER-007` | Spam request statistik | Throttling | Rapid requests | `429 Too Many Requests` | Rate limited |
