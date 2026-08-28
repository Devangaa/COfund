# CoFund API - Modul Transaksi (Transaction Module)

## 1. Judul & Deskripsi Modul

Modul transaksi menampilkan riwayat semua transaksi keuangan pengguna seperti pembayaran backing, refund, disbursement, biaya platform, deposit, dan withdrawal. Transaksi bersifat informatif (read-only).

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/TransactionController.php` | Index transaksi dengan filter admin |
| **Model** | `app/Models/Transaction.php` | Model transaksi dengan SoftDeletes |
| **Resource** | `app/Http/Resources/TransactionResource.php` | Serialisasi data transaksi |
| **Enums** | `app/Enums/TransactionType.php` | `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal` |
| | `app/Enums/TransactionStatus.php` | `pending`, `success`, `failed` |
| **Middleware** | `auth:sanctum`, `verified` | Otentikasi dan verifikasi email |

### Alur Proses Logika Bisnis

```
                Pengguna/Login
                     |
                     v
          GET /api/v1/transactions
                     |
                     v
          TransactionController::index()
                     |
                     v
          Validate query params
          (type, status, dates, user_id for admin, sort, per_page)
                     |
                     v
          Build Query
          - Admin: optional user_id filter
          - Non-admin: locked to own user_id
                     |
                     v
          Paginate (default 12, configurable)
                     |
                     v
          Return TransactionResource collection
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── TransactionController.php
│   │   ├── Resources/
│   │   │   └── TransactionResource.php
│   │   └── Middleware/
│   │       └── (auth:sanctum, verified)
│   ├── Models/
│   │   └── Transaction.php
│   ├── Enums/
│   │   ├── TransactionType.php
│   │   └── TransactionStatus.php
│   └── Services/
│       └── TransactionService.php (for create, not used in this controller)
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: List Transactions

- **Deskripsi:** Menampilkan riwayat transaksi pengguna yang terautentikasi. Admin dapat memfilter berdasarkan `user_id`.
- **HTTP Method & URL Path:** `GET /api/v1/transactions`
- **Middleware:** `auth:sanctum`, `verified`

#### Tabel Parameter (Query)

| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `type` | string | Tidak | `in:payment,refund,disbursement,platform_fee,deposit,withdrawal` | Filter tipe transaksi |
| `status` | string | Tidak | `in:pending,success,failed` | Filter status |
| `start_date` | date | Tidak | - | Filter tanggal minimum (created_at) |
| `end_date` | date | Tidak | - | Filter tanggal maksimum |
| `user_id` | integer | Tidak (admin only) | `exists:users,id` | Filter berdasarkan user |
| `sort` | string | Tidak | `latest`, `oldest` | Urutkan (default: latest) |
| `per_page` | integer | Tidak | `min:1`, `max:50` | Item per halaman (default: 10, maksimal: 50) |

#### Contoh Request

```
GET /api/v1/transactions?type=deposit&status=success&sort=latest&per_page=10
Authorization: Bearer {token}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": [
        {
            "id": 20,
            "type": "deposit",
            "amount": 500000,
            "status": "success",
            "reference": "deposit_1706789012_1",
            "backing_id": null,
            "campaign_id": null,
            "created_at": "2024-02-01T10:30:00Z"
        },
        {
            "id": 19,
            "type": "payment",
            "amount": 100000,
            "status": "success",
            "reference": "mock_payment_1706789000",
            "backing_id": 5,
            "campaign_id": 3,
            "created_at": "2024-02-01T09:15:00Z"
        },
        {
            "id": 18,
            "type": "withdrawal",
            "amount": 250000,
            "status": "success",
            "reference": "withdrawal_1706788900_1",
            "backing_id": null,
            "campaign_id": null,
            "created_at": "2024-02-01T08:00:00Z"
        }
    ],
    "meta": {
        "pagination": {
            "current_page": 1,
            "last_page": 3,
            "per_page": 10,
            "total": 28
        }
    }
}
```

#### Efek Samping

- Backer hanya melihat transaksinya sendiri
- Creator hanya melihat transaksinya sendiri (termasuk disbursement ketika kampunnya berhasil)
- Admin bisa melihat semua transaksi dengan filter `user_id`

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 403 | `{"success":false,"message":"The user must verify their email."}` | Email belum diverifikasi |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"user_id":["The user_id field is prohibited."]}}}` | Non-admin mencoba filter `user_id` |

---

## 5. Skema Sumber Daya (Resource Schema)

### TransactionResource

```json
{
    "id": 19,
    "type": "payment",
    "amount": 100000,
    "status": "success",
    "reference": "mock_payment_1706789000",
    "backing_id": 5,
    "campaign_id": 3,
    "created_at": "2024-02-01T09:15:00Z"
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key transaksi |
| `type` | enum | `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal` |
| `amount` | decimal | Jumlah transaksi |
| `status` | enum | `pending`, `success`, `failed` |
| `reference` | string | Kode referensi unik |
| `backing_id` | integer\|null | Terkait backing (untuk payment/refund) |
| `campaign_id` | integer\|null | Terkait kampanye |
| `created_at` | datetime | Timestamp transaksi |

---

## 6. Pengujian Postman

### List Transactions

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/transactions?type=deposit&sort=latest`
3. Headers: `Authorization: Bearer {{auth_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Only deposit transactions", function () {
    var jsonData = pm.response.json();
    jsonData.data.forEach(item => {
        pm.expect(item.type).to.eql("deposit");
    });
});
```

### Admin Filter Transactions

Prasyarat: Login sebagai admin pertama.

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/transactions?user_id=5&type=payment`
3. Headers: `Authorization: Bearer {{admin_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | List semua transaksi sendiri | GET /transactions | Transaksi sesuai user yang terautentikasi |
| 2 | Filter tipe deposit | `?type=deposit` | Hanya transaksi type deposit |
| 3 | Filter tipe payment | `?type=payment` | Hanya transaksi type payment |
| 4 | Filter status success | `?status=success` | Hanya transaksi berstatus sukses |
| 5 | Filter berdasarkan tanggal | `?start_date=2024-01-01&end_date=2024-02-01` | Transaksi dalam rentang tanggal |
| 6 | Sort descending | `?sort=latest` | Terbaru di awal |
| 7 | Sort ascending | `?sort=oldest` | Terlama di awal |
| 8 | Custom per_page | `?per_page=5` | 5 item per halaman |
| 9 | Dynamic pagination | `?page=2&per_page=25` | Halaman ke-2, 25 item (maksimal 50) |
| 10 | Admin filter user_id | `?user_id=5` (sebagai admin) | Transaksi semua user |
| 11 | Backer filter user_id | `?user_id=5` (sebagai backer) | HTTP 422, "field is prohibited" |
| 12 | Pagination | `?page=2&per_page=10` | Halaman ke-2 |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| Transaksi kosong setelah aktivitas | Pastikan transaksi berhasil dibuat. Deposit/withdrawal transactions hanya dibuat pada saat event listener berjalan. |
| `user_id` filter error | Hanya admin yang dapat memfilter `user_id`. Backer akan mendapat HTTP 422. |
| Tanggal filter tidak bekerja | Pastikan format tanggal benar (`Y-m-d`). |
| Tipe transaksi tidak ditemukan | Tipe transaksi mencakup: `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal`. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /api/v1/transactions` | - | ✓ (own) | ✓ (own) | ✓ (all, with filter) |

---

## 10. Matriks Kasus Pengujian (Test Case)

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-TRANS-001` | List transaksi sendiri | Positive | Auth user biasa | `200 OK` | Semua transaksi user, meta pagination |
| `TC-TRANS-002` | List transaksi dengan filter type deposit | Positive | `?type=deposit` | `200 OK` | Hanya transaksi deposit |
| `TC-TRANS-003` | List transaksi dengan filter status | Positive | `?status=success` | `200 OK` | Hanya transaksi sukses |
| `TC-TRANS-004` | List transaksi dengan date range | Positive | `?start_date=2024-01-01&end_date=2024-12-31` | `200 OK` | Transaksi dalam rentang |
| `TC-TRANS-005` | Admin filter user_id lain | Positive | Admin + `?user_id=5` | `200 OK` | Transaksi user ke-5 |
| `TC-TRANS-006` | Backer filter user_id orang lain | Security | Backer + `?user_id=5` | `422 Unprocessable` | Error "user_id field is prohibited" |
| `TC-TRANS-007` | List transaksi dengan type invalid | Negative | `?type=invalid` | `422 Unprocessable` | Error enum tidak valid |
| `TC-TRANS-008` | List transaksi dengan status invalid | Negative | `?status=invalid` | `422 Unprocessable` | Error enum tidak valid |
| `TC-TRANS-009` | List transaksi dengan date range tidak valid | Negative | `start_date > end_date` | `422 Unprocessable` | Error validasi tanggal |
| `TC-TRANS-010` | List transaksi dengan per_page > 50 | Positive | `?per_page=999` | `200 OK` | Dibatasi ke 50 |
| `TC-TRANS-011` | List transaksi tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-TRANS-012` | List transaksi dengan format tanggal salah | Negative | `?start_date=not-a-date` | `422 Unprocessable` | Error format tanggal |
| `TC-TRANS-013` | List transaksi dengan user_id tidak ada | Negative | `?user_id=99999` (admin) | `422 Unprocessable` | Error "Selected user_id is invalid" |
| `TC-TRANS-014` | List transaksi dengan sort invalid | Negative | `?sort=invalid` | `422 Unprocessable` | Error "Invalid sort value" |
| `TC-TRANS-015` | List transaksi dengan page tidak valid | Negative | `?page=-1` | `422 Unprocessable` | Error "Page must be at least 1" |
