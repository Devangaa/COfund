# CoFund API - Modul Creator (Creator Module)

## 1. Judul & Deskripsi Modul

Modul ini menyediakan endpoint statistik eksklusif untuk kreator. Statistik mencakup total kampanye, total dukungan, total dana terkumpul, target, biaya platform, tingkat penyelesaian, distribusi status kampanye, dan data grafik berdasarkan periode waktu.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/Creator/CreatorStatisticsController.php` | Endpoint statistik creator |
| **Model** | `app/Models/Campaign.php`, `app/Models/Backing.php` | Model yang digunakan untuk query statistik |
| **Enums** | `app/Enums/CampaignStatus.php` | Status kampanye untuk distribusi |
| **Middleware** | `auth:sanctum`, `role:creator`, `verified` | Hanya kreator yang terautentikasi |
| **Config** | `config/cofund.php` | `platform_fee` rate (default: 0.05 = 5%) |

### Alur Proses Logika Bisnis

```
Creator meminta statistik
        |
        v
CreatorStatisticsController::index()
        |
        v
Validate query params
  - period: daily|weekly|monthly|yearly (default: daily)
  - start_date, end_date (optional)
        |
        v
Query campaigns milik creator
  (filter by date jika start_date/end_date)
        |
        +---> Total campaigns
        +---> Total collected (SUM collected_amount)
        +---> Total target (SUM target_amount)
        +---> Total fees (backings SUM amount * platform_fee)
        +---> Completion rate
        +---> Status distribution (normalize)
        +---> Chart data (group by period)
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
│   │   │       └── Creator/
│   │   │           └── CreatorStatisticsController.php
│   └── Models/
│       ├── Campaign.php
│       └── Backing.php
├── config/
│   └── cofund.php (platform_fee config)
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Index Creator Statistics

- **Deskripsi:** Mendapatkan statistik lengkap kampanye dan keuangan untuk kreator yang sedang login.
- **HTTP Method & URL Path:** `GET /api/v1/creator/statistics`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter (Query)

| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `period` | string | Tidak | `in:daily,weekly,monthly,yearly` | Periode pengelompokan data grafik (default: `daily`) |
| `start_date` | date | Tidak | - | Tanggal mulai filter |
| `end_date` | date | Tidak | - | Tanggal akhir filter |

#### Contoh Request

```
GET /api/v1/creator/statistics?period=monthly&start_date=2024-01-01&end_date=2024-03-31
Authorization: Bearer {token}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": {
        "total_campaigns": 5,
        "total_backings": 127,
        "total_collected": 25000000,
        "total_target": 50000000,
        "total_fees": 2500000,
    "platform_fee_rate": 0.05,
        "completion_rate": 50.0,
        "status_distribution": {
            "draft": 1,
            "review": 0,
            "active": 2,
            "success": 2,
            "failed": 0
        },
        "chart": [
            {
                "period": "2024-01",
                "campaigns": 2,
                "collected": 15000000
            },
            {
                "period": "2024-02",
                "campaigns": 3,
                "collected": 10000000
            }
        ]
    }
}
```

#### Deskripsi Field

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `total_campaigns` | integer | Jumlah total kampanye yang dibuat kreator |
| `total_backings` | integer | Jumlah total dukungan untuk semua kampanye kreator |
| `total_collected` | decimal | Total dana terkumpul dari semua kampanye |
| `total_target` | decimal | Total target dana dari semua kampanye |
| `total_fees` | decimal | Total biaya platform (5% dari backing amount) |
| `platform_fee_rate` | decimal | Persentase biaya platform (default 0.05 = 5%) |
| `completion_rate` | decimal | Persentase pencapaian target |
| `status_distribution` | object | Distribusi kampanye berdasarkan status |
| `chart.period` | string | Label periode (tanggal/bulan/tahun) |
| `chart.campaigns` | integer | Jumlah kampanye pada periode |
| `chart.collected` | decimal | Dana terkumpol pada periode |

#### Side Effects

- Read-only query, tidak ada perubahan data
- Query ke tabel `campaigns` dan `backings`

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 403 | `{"success":false,"message":"Unauthorized. This action requires Creator role."}` | Bukan role creator |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Parameter query tidak valid |

---

## 5. Skema Sumber Daya (Resource Schema)

### Creator Statistics Response Schema

```json
{
    "total_campaigns": 5,
    "total_backings": 127,
    "total_collected": 25000000.00,
    "total_target": 50000000.00,
    "total_fees": 2500000.00,
     "platform_fee_rate": 0.05,
    "completion_rate": 50.0,
    "status_distribution": {
        "draft": 1,
        "review": 0,
        "active": 2,
        "success": 2,
        "failed": 0
    },
    "chart": [
        {
            "period": "2024-01",
            "campaigns": 2,
            "collected": 15000000.00
        }
    ]
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `total_campaigns` | integer | Jumlah kampanye creator |
| `total_backings` | integer | Jumlah backing keseluruhan |
| `total_collected` | decimal(15,2) | Total dana terkumpul |
| `total_target` | decimal(15,2) | Total target dana |
| `total_fees` | decimal(15,2) | Biaya platform (5%) |
| `platform_fee_rate` | decimal | Rate fee (0.05) |
| `completion_rate` | decimal(5,2) | Persentase pencapaian (%) |
| `status_distribution` | object | Count per status kampanye |
| `chart[]` | array | Data grafik berdasarkan periode |

---

## 6. Pengujian Postman

### Creator Statistics (Default)

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/creator/statistics`
3. Headers: `Authorization: Bearer {{auth_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Has required fields", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.total_campaigns).to.be.a("number");
    pm.expect(jsonData.data.total_collected).to.be.a("number");
    pm.expect(jsonData.data.status_distribution).to.be.an("object");
    pm.expect(jsonData.data.chart).to.be.an("array");
});
```

### Creator Statistics (Monthly)

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/creator/statistics?period=monthly&start_date=2024-01-01&end_date=2024-12-31`
3. Headers: `Authorization: Bearer {{auth_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Chart data grouped by month", function () {
    var jsonData = pm.response.json();
    jsonData.data.chart.forEach(item => {
        pm.expect(item.period).to.match(/^\d{4}-\d{2}$/);
    });
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | Get default statistics | GET /creator/statistics | Data statistik default (daily) |
| 2 | Get monthly statistics | `?period=monthly` | chart data dalam format `YYYY-MM` |
| 3 | Get yearly statistics | `?period=yearly` | chart data dalam format `YYYY` |
| 4 | Filter by date range | `?start_date=2024-01-01&end_date=2024-06-30` | Data hanya dari rentang tanggal |
| 5 | Backer mengakses creator statistik | Login sebagai backer, GET /creator/statistics | HTTP 403, "Creator role required" |
| 6 | Unauthenticated access | Tanpa token | HTTP 401, "Unauthenticated" |
| 7 | Creator tanpa kampanye | Login sebagai creator baru | total_campaigns=0, chart kosong |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| `platform_fee_rate` — konsisten | Biaya platform telah diselaraskan ke 5% (`0.05`) melalui `config/cofund.php` (`PLATFORM_FEE_RATE=0.05`). `CreatorStatisticsController`, `AdminStatisticsController`, dan `DisburseCampaignJob` semua kini menggunakan nilai yang sama. |
| Chart data kosong | Pastikan kreator memiliki setidaknya 1 kampanye. Data grafik dihasilkan dari query kampanye. |
| Status distribution tidak lengkap | Semua status enum (`draft`, `review`, `active`, `success`, `failed`) selalu disertakan dengan nilai 0 jika tidak ada. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /api/v1/creator/statistics` | - | - | ✓ | - |

---

## 10. Matriks Kasus Pengujian (Test Case)

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CREATOR-001` | Get statistik default (daily) | Positive | `GET /creator/statistics` | `200 OK` | Statistik lengkap, chart daily |
| `TC-CREATOR-002` | Get statistik monthly | Positive | `?period=monthly` | `200 OK` | Chart dalam format YYYY-MM |
| `TC-CREATOR-003` | Get statistik yearly | Positive | `?period=yearly` | `200 OK` | Chart dalam format YYYY |
| `TC-CREATOR-004` | Get statistik dengan date filter | Positive | `?start_date=2024-01-01&end_date=2024-06-30` | `200 OK` | Data dalam rentang tanggal |
| `TC-CREATOR-005` | Get statistik tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-CREATOR-006` | Get statistik sebagai backer | Security | Role backer | `403 Forbidden` | Error "Creator role required" |
| `TC-CREATOR-007` | Get statistik sebagai admin | Positive | Login admin | `200 OK` | Admin bisa akses (role check melewati) |
| `TC-CREATOR-008` | Get statistik dengan period invalid | Negative | `?period=invalid` | `422 Unprocessable` | Error "Invalid period value" |
| `TC-CREATOR-009` | Get statistik dengan date range tidak valid | Negative | `start_date > end_date` | `422 Unprocessable` | Error validasi tanggal |
| `TC-CREATOR-010` | Creator tanpa kampanye | Positive | Creator baru, belum punya kampanye | `200 OK` | `total_campaigns=0`, chart kosong |
| `TC-CREATOR-011` | Email belum terverifikasi | Security | Creator email belum verified | `403 Forbidden` | Error "Email verification required" |
