# API Modul Gambar Kampanye

Pengunggahan dan pengelolaan gambar kampanye.

## Arsitektur

Modul Gambar Kampanye menangani pengunggahan, validasi, dan penghapusan gambar kampanye. Gambar pertama yang ditambahkan menjadi gambar utama secara otomatis. Gambar disimpan pada disk `campaigns` (public/local).

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/CampaignImageController.php` | Menangani pengunggahan dan penghapusan gambar |
| Service | `app/Services/CampaignImageService.php` | Logika bisnis untuk pengelolaan gambar |
| Requests | `app/Http/Requests/{StoreCampaignImageRequest, DeleteCampaignImageRequest}.php` | Aturan validasi |
| Resource | `app/Http/Resources/CampaignImageResource.php` | Pemformatan respons JSON |
| Model | `app/Models/CampaignImage.php` | Entitas gambar |

### Alur

```
Creator → StoreCampaignImageRequest
       → CampaignImageService::create()
       → CampaignService::ensureEditable() check
       → Periksa maks 5 gambar
       → Simpan berkas ke disk campaigns
       → Buat catatan CampaignImage
       → (jika tidak ada primary → atur sebagai primary)

Creator → DeleteCampaignImageRequest
       → CampaignImageService::deleteMany()
       → CampaignService::ensureEditable() check
       → Kunci jumlah baris
       → Pastikan ≥1 gambar tersisa
       → Validasi ID
       → Hapus berkas fisik dari penyimpanan
       → Soft-delete catatan
```

## Struktur File

```
app/
├── Http/Controllers/Api/CampaignImageController.php
├── Services/CampaignImageService.php
├── Http/Requests/
│   ├── StoreCampaignImageRequest.php
│   └── DeleteCampaignImageRequest.php
├── Http/Resources/CampaignImageResource.php
└── Models/CampaignImage.php
```

## API Endpoints

### 1. Unggah Gambar

Mengunggah gambar baru ke kampanye. Gambar pertama secara otomatis menjadi gambar utama.

**Endpoint:** `POST /api/campaigns/{slug}/images`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Mengunggah gambar ke kampanye.

#### Otorisasi

Pengguna harus memiliki kampanye. Harus dalam keadaan dapat diedit (status DRAFT).

#### Body Permintaan (Multipart Form Data)

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `image` | file | Ya | `required, image, mimes:jpeg,png,jpg,gif, max:2048` | Berkas gambar (maks 2MB) |

#### Contoh Request

```
POST /api/campaigns/{slug}/images
Content-Type: multipart/form-data
Authorization: Bearer {token}

Body: (form-data)
- image: (file upload)
```

#### Respons (Sukses: 201)

```json
{
  "id": 5,
  "url": "http://localhost/storage/campaigns/IMG-abc123.jpg",
  "is_primary": false,
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

> Jika ini gambar pertama, `is_primary` akan `true`.

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan pemilik kampanye |
| 409 | Campaign is not in editable state | Kampanye bukan DRAFT |
| 422 | Validation error | Berkas terlalu besar (>2MB) / bukan gambar / format tidak valid |
| 422 | Campaign has reached maximum 5 images | Sudah memiliki 5 gambar |

---

### 2. Hapus Gambar (Massal)

Menghapus beberapa gambar dari sebuah kampanye.

**Endpoint:** `DELETE /api/campaigns/{slug}/images`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Menghapus beberapa gambar dalam satu permintaan.

#### Otorisasi

Pengguna harus memiliki kampanye. Harus dalam keadaan dapat diedit (status DRAFT).

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `ids` | array | Ya | `required, array, min:1` | Array ID gambar |
| `ids.*` | integer | Ya | `integer, exists:campaign_images,id` | Harus ada |

#### Contoh Request

```json
{
  "ids": [5, 6]
}
```

#### Respons (Sukses: 200)

```json
{
  "message": "Images deleted successfully"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan pemilik kampanye |
| 409 | Campaign is not in editable state | Kampanye bukan DRAFT |
| 409 | Cannot delete all images | Hanya 1 gambar tersisa dan pengguna mencoba menghapusnya |
| 422 | Validation error | ID gambar tidak valid |

## Skema Sumber Daya Gambar

```json
{
  "id": 5,
  "url": "http://localhost/storage/campaigns/IMG-abc123.jpg",
  "is_primary": false,
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

### Referensi Kolom

| Kolom | Tipe | Deskripsi |
|-------|------|-------------|
| `id` | integer | ID gambar |
| `url` | string | URL lengkap ke berkas gambar |
| `is_primary` | boolean | Apakah ini gambar utama |
| `created_at` | datetime | Timestamp pembuatan |

## Aturan Bisnis

### 1. Maksimum 5 Gambar

Kampanye dapat memiliki maksimal 5 gambar. Mencoba mengunggah gambar ke-6 akan mengembalikan:
```json
{
  "message": "Campaign has reached maximum 5 images"
}
```

### 2. Harus Mempertahankan Setidaknya 1 Gambar

Saat menghapus gambar, sistem memastikan setidaknya 1 gambar tersisa. Jika mencoba menghapus semua gambar:
- Baris jumlah tabel `campaign_images` dikunci
- Setelah penghapusan, jika tidak ada gambar yang tersisa, `ValidationException` dilempar: "Cannot delete all images"

### 3. Pengelolaan Gambar Utama

Saat sebuah kampanye dibuat:
- Gambar pertama yang diunggah secara otomatis diatur sebagai `is_primary = true`
- Jika gambar utama dihapus, gambar pertama yang tersisa menjadi gambar utama baru

Saat gambar dihapus:
- Jika gambar yang dihapus adalah primary, gambar pertama yang tersisa akan promosikan menjadi primary (ditangani di `CampaignService::create` dan `destroy`)

### 4. Hanya Status yang Dapat Diedit

Gambar hanya dapat diunggah atau dihapus ketika kampanye dalam status **DRAFT**. Hal ini ditegakkan oleh `CampaignService::ensureEditable()`.

### 5. Penyimpanan Berkas

Gambar disimpan pada disk `campaigns` (dikonfigurasi sebagai penyimpanan local public di `config/filesystems.php`). URL dihasilkan melalui `Storage::disk('public')->url($path)`.

### 6. Penghapusan Berkas

Saat gambar dihapus:
1. Berkas fisik dihapus dari penyimpanan menggunakan `Storage::disk('campaigns')->delete($url)`
2. Catatan dilakukan soft-delete (tidak benar-benar dihapus)
3. Jika gambar yang dihapus adalah primary, gambar berikutnya akan promosikan

## Pengujian Postman

### Skrip Pengujian (Gambar Kampanye)

#### Pengujian 1: Unggah Gambar (Multipart)

1. `POST {{base_url}}/campaigns/{draft-slug}/images`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Body: `form-data` → kunci: `image`, tipe: `file`, pilih berkas gambar
4. Diperkirakan: `201 Created` dengan URL gambar.

#### Pengujian 2: Gambar Pertama adalah Primary

1. Buat kampanye baru.
2. Unggah satu gambar.
3. Diperkirakan: `is_primary = true`.

#### Pengujian 3: Unggah Gambar ke-6

1. Unggah 5 gambar.
2. Coba unggah gambar ke-6.
3. Diperkirakan: `422 Validation error`.

#### Pengujian 4: Hapus Gambar

1. Unggah 3 gambar.
2. `DELETE {{base_url}}/campaigns/{slug}/images`
3. Body:
   ```json
   { "ids": [5, 6] }
   ```
4. Diperkirakan: `200 OK`.

#### Pengujian 5: Hapus Semua Gambar (Hanya 1 yang Tersisa)

1. Pastikan hanya 1 gambar tersisa.
2. Hapus gambar tersebut.
3. Diperkirakan: `409 Conflict` / `422 Validation error`.

#### Pengujian 6: Unggah pada Kampanye Non-DRAFT

1. Gunakan slug kampanye ACTIVE.
2. Coba unggah gambar.
3. Diperkirakan: `409 Conflict` — "Campaign is not in editable state".

#### Pengujian 7: Akses sebagai Non-Pemilik

1. Gunakan token creator yang berbeda.
2. Coba unggah/hapus gambar pada kampanye orang lain.
3. Diperkirakan: `403 Forbidden`.

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Unggah gambar valid (pertama) | Berkas (jpeg/png) | 201 + is_primary=true |
| 2 | Unggah gambar valid (bukan pertama) | Berkas | 201 + is_primary=false |
| 3 | Unggah berkas terlalu besar (>2MB) | Gambar 3MB | 422 error validasi |
| 4 | Unggah berkas bukan gambar | Berkas .txt | 422 error validasi |
| 5 | Unggah format tidak valid | .webp/.svg | 422 error validasi |
| 6 | Unggah gambar ke-6 | Setelah 5 ada | 422 "Campaign has reached maximum 5 images" |
| 7 | Hapus gambar (beberapa) | 2-3 ID valid | 200 + berhasil dihapus |
| 8 | Hapus semua gambar (terakhir) | Hapus terakhir yang tersisa | 409 "Cannot delete all images" |
| 9 | Unggah pada kampanye non-DRAFT | Kampanye aktif/REVIEW | 409 kampanye tidak dapat diedit |
| 10 | Unggup sebagai non-pemilik | Kampanye creator lain | 403 dilarang |
| 11 | Hapus sebagai non-pemilik | Gambar creator lain | 403 dilarang |
| 12 | Hapus gambar utama | ID gambar utama | 200 + primary baru dipromosikan |
| 13 | Unggah ke slug kampanye tidak valid | Slug tidak ada | 404 tidak ditemukan |

## Pemecahan Masalah

### 1. Error "File too large"

Berkas harus ≤ 2MB (ditentukan sebagai `max:2048` dalam kilobyte).

**Perbaikan:** Kompres gambar atau ubah ukurannya sebelum mengunggah. Frontend harus menampilkan pratinjau dan memperingatkan pengguna sebelum mengunggah.

---

### 2. "Campaign is not in editable state"

Gambar hanya dapat ditambahkan saat kampanye dalam status DRAFT. Setelah diajukan untuk tinjauan atau disetujui, pengelolaan gambar dikunci.

**Perbaikan:** Pastikan kampanye dalam status DRAFT sebelum mengunggah atau menghapus gambar. Batasan status ini berlaku untuk semua sub-sumber daya (tier, pembaruan, gambar).

---

### 3. "Campaign has reached maximum 5 images"

Metode `CampaignImageService::create()` memeriksa `if ($campaign->images()->count() >= 5)` sebelum membuat gambar baru.

**Perbaikan:** Hapus beberapa gambar yang ada sebelum mengunggah gambar baru.

---

### 4. "Cannot delete all images"

Metode `CampaignImageService::deleteMany()` mendapatkan kunci baris, menghapus gambar yang dipilih, lalu memeriksa `if ($campaign->images()->count() === 0)`. Jika demikian, `ValidationException` dilempar.

Perhatikan bahwa `images()` hanya mengembalikan catatan yang tidak dihapus karena `CampaignImage` menggunakan trait `SoftDeletes`. Jadi jika sebelumnya sudah melakukan soft-delete pada beberapa gambar, hitungan akan tetap akurat.

---

### 5. URL Gambar Tidak Berfungsi

URL dihasilkan oleh `Storage::disk('public')->url($url)`. Pastikan:

1. Symlink `public/storage` ada (`php artisan storage:link`)
2. Server web memiliki izin baca untuk `storage/app/public`
3. `.env` `APP_URL` diatur dengan benar (URL didahului dengan `APP_URL`)

---

### 6. Penghapusan Tidak Menghapus Berkas dari Disk

Metode `CampaignImageService::deleteMany()` memang memanggil `Storage::disk('campaigns')->delete($image->url)`, yang menghapus berkas fisik. Namun, jika metode melempar `ValidationException` setelah penghapusan (akibat pemeriksaan "cannot delete all"), berkas fisik sudah dihapus tetapi transaksi database **tidak** dirollback.

Ini berarti:
- Berkas fisik **sudah** dihapus dari disk
- Namun catatan DB **tidak** dilakukan soft-delete (karena pengecualian dilempar sebelum `$image->delete()` dipanggil untuk semua item)

**Potensi bug:** Jika kasus tepi ini gagal di tengah jalan, beberapa berkas mungkin dihapus pada disk tetapi catatan tetap ada di database, menyisakan catatan "terasing" yang menunjuk ke berkas yang tidak ada.

**Perbaikan:** Pertimbangkan untuk membungkus seluruh operasi dalam transaksi DB dan menghapus berkas hanya setelah pemeriksaan kuantitas lulus.

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Unggah gambar | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Hapus gambar | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
