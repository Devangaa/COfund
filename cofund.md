# CoFund — Dokumen Fitur & Standar Penggunaan Perusahaan

Project pelatihan magang - Laravel 10 + Vue.js

---

## BAGIAN 1 — CARA KERJA COFUND

### 1.1 Overview Sistem

CoFund adalah platform crowdfunding lokal sederhana. Creator membuat kampanye
dengan target dana dan deadline. Backer mendanai kampanye dengan memilih tier
atau nominal bebas.

Dana ditahan dalam escrow virtual hingga kampanye berhasil (target tercapai
sebelum deadline) atau gagal — yang memicu refund otomatis ke semua backer
melalui queue job Laravel.

Kompleksitas bisnis yang dirancang: state machine, virtual escrow, scheduled
jobs, queue worker, dan multi-role notification system.

**Stack Teknologi:**
- Backend: Laravel (REST API + Scheduled Commands + Queue Worker)
- Frontend Web: Vue.js
- Database: MySQL / PostgreSQL
- Queue Driver: Redis / Database Queue

### 1.2 Role & Akses

Empat role dengan hak akses berbeda:

| Role | Deskripsi | Default Akses | Akses Utama |
|---|---|---|---|
| guest | Belum login | Otomatis (tidak login) | Lihat kampanye publik |
| backer | User yang mendanai | Setelah register | Backing, riwayat, dashboard backer |
| creator | Pembuat kampanye | Request upgrade | CRUD kampanye, post update, analytics |
| admin | Pengelola platform | Assign manual | Approve kampanye, monitor transaksi |

Satu user dapat memiliki role backer dan creator sekaligus. Role admin hanya
bisa di-assign secara manual melalui database atau panel admin.

### 1.3 Modul Autentikasi

**Register**
- Input: nama, email, password, konfirmasi password
- Validasi: email unik, password minimum 8 karakter
- Setelah register: kirim email verifikasi
- Default role: backer

**Login**
- Input: email dan password
- Response: token (Sanctum/Passport) + data user
- Redirect ke halaman sesuai role setelah login berhasil

**Verifikasi Email**
- Link verifikasi dikirim otomatis saat register
- Akun belum terverifikasi tidak bisa membuat kampanye atau melakukan backing

**Lupa Password**
- User input email, sistem kirim link reset
- Link reset akan expired setelah 60 menit

### 1.4 Modul Campaign

**Buat Kampanye (Creator)** — Input yang diperlukan:
- Judul kampanye (maksimal 100 karakter)
- Slug — auto-generate dari judul, bisa di-edit manual
- Kategori — pilih dari daftar yang tersedia
- Deskripsi lengkap (mendukung rich text atau markdown)
- Target dana (minimal Rp 100.000)
- Deadline (minimal H+7 dari tanggal submit)
- Video embed URL dari YouTube atau Vimeo (opsional)
- Foto kampanye (minimal 1, maksimal 5 gambar)

Alur pembuatan kampanye:
1. Creator isi form lengkap — status kampanye menjadi `draft`
2. Creator submit untuk review — status berubah menjadi `review`
3. Admin tinjau dan approve — status berubah menjadi `active` (kampanye live)
4. Jika admin reject — status kembali ke `draft` dengan catatan penolakan

**Edit Kampanye**
- Hanya bisa diedit saat status masih `draft`
- Setelah `active`, creator hanya bisa menambahkan campaign update

**Lihat Kampanye (Publik)**
- Halaman list: kampanye aktif dengan filter berdasarkan kategori, status,
  terbaru, dan terpopuler
- Halaman detail: informasi kampanye, progress funding (progress bar +
  persentase + sisa hari), daftar tier tersedia, daftar backer, dan update
  dari creator

**Campaign Update (Creator)**
- Creator bisa post update teks selama kampanye aktif
- Semua backer kampanye mendapat notifikasi saat ada update baru

### 1.5 Modul Tier & Backing

**Tier Reward**

Creator mendefinisikan tier saat membuat kampanye. Setiap kampanye wajib
memiliki minimal satu tier.

| Field | Deskripsi | Contoh |
|---|---|---|
| Nama tier | Label tier yang tampil ke publik | "Early Bird" |
| Min. nominal | Batas bawah nominal donasi untuk masuk tier ini | Rp 50.000 |
| Kuota | Jumlah slot tersedia (0 = tidak terbatas) | 100 |
| Deskripsi reward | Apa yang didapat backer di tier ini | Akses beta + stiker |

Kuota tier berkurang otomatis setiap ada backing baru. Tier yang sudah penuh
(`remaining_quota = 0`) tidak bisa dipilih oleh backer baru.

**Proses Backing (Backer)**

Alur backer mendanai kampanye:
1. Backer pilih kampanye yang sedang aktif
2. Pilih tier atau masukkan nominal bebas (tanpa reward tier)
3. Konfirmasi backing dan review ringkasan
4. Simulasi pembayaran melalui mock payment gateway
5. Jika pembayaran sukses — backing status `completed`, dana masuk escrow
6. Notifikasi konfirmasi dikirim ke backer via in-app dan email

Aturan backing: user wajib login dan email terverifikasi. Creator tidak bisa
backing kampanye miliknya sendiri. Satu user boleh backing kampanye yang sama
lebih dari sekali. Nominal minimum Rp 10.000.

### 1.6 Modul Transaksi & Escrow

**Virtual Escrow**

Setiap backing yang sukses menyebabkan dana masuk ke escrow (dicatat sebagai
transaksi tipe `payment`). Field `campaigns.collected_amount` bertambah
otomatis. Dana tidak pernah langsung masuk ke saldo creator selama kampanye
masih berlangsung.

**Pencairan (Disbursement)**

Dipicu otomatis oleh sistem saat kampanye berstatus `success`:
- Platform fee dipotong (5% dari total collected amount)
- Sisa dana dicairkan ke saldo creator (`users.balance`)
- Transaksi `disbursement` dan `platform_fee` dibuat secara otomatis

**Refund Otomatis**

Dipicu otomatis oleh sistem saat kampanye berstatus `failed`:
- Semua backing dengan status `completed` di-refund
- Dana dikembalikan ke saldo masing-masing backer (`users.balance`)
- Transaksi `refund` dibuat per backer
- Status backing diubah menjadi `refunded`

**Tipe Transaksi**

| Tipe | Keterangan |
|---|---|
| payment | Backer melakukan backing — dana masuk virtual escrow |
| refund | Pengembalian dana ke backer saat kampanye gagal |
| disbursement | Pencairan dana ke creator saat kampanye sukses |
| platform_fee | Potongan fee platform (5%) diambil saat disbursement |

### 1.7 Campaign Lifecycle (Scheduled Jobs)

Semua proses lifecycle kampanye dijalankan secara otomatis oleh Laravel
Scheduler setiap hari pada pukul 00:05.

**`CheckExpiredCampaigns` Command** (`php artisan campaign:check-expired`)
1. Ambil semua kampanye dengan status `active` dan deadline < hari ini
2. Jika `collected_amount >= target_amount` — status menjadi `success`,
   dispatch `DisburseCampaignJob`
3. Jika `collected_amount < target_amount` — status menjadi `failed`, dispatch
   `RefundBackersJob`

**`DisburseCampaignJob` (Queue Job)**
- Hitung platform fee (5% dari total collected amount)
- Buat transaksi `platform_fee`
- Tambah saldo creator: collected_amount dikurangi platform fee
- Buat transaksi `disbursement`
- Kirim notifikasi ke creator: kampanye berhasil, dana sudah dicairkan

**`RefundBackersJob` (Queue Job)**
- Ambil semua backing `completed` dari kampanye ini
- Untuk setiap backing: tambah saldo backer sebesar nominal backing
- Buat transaksi `refund` per backer
- Update status backing menjadi `refunded`
- Kirim notifikasi ke backer: kampanye gagal, dana sudah dikembalikan

**`NotifyDeadlineApproaching` Command** (`php artisan campaign:notify-deadline`)

Dijalankan setiap hari. Mencari kampanye aktif dengan deadline H-3 dan H-1,
kemudian mengirimkan notifikasi ke semua backer kampanye tersebut.

### 1.8 Modul Notifikasi

**In-App Notification**
- Disimpan di tabel `notifications` database
- Bell icon di navbar dengan badge jumlah notifikasi belum dibaca
- Klik notifikasi: tandai dibaca dan redirect ke halaman terkait

**Email Notification**
- Dikirim via Laravel Mail menggunakan Queue (tidak blocking request)
- Template email tersedia untuk setiap jenis event

**Event Notifikasi**

| Event | Penerima | Channel |
|---|---|---|
| Kampanye disetujui admin | Creator | In-app + Email |
| Kampanye ditolak admin | Creator | In-app + Email |
| Ada backing baru masuk | Creator | In-app |
| Backing berhasil dikonfirmasi | Backer | In-app + Email |
| Creator post update kampanye | Semua backer | In-app |
| Deadline H-3 | Semua backer | In-app |
| Deadline H-1 | Semua backer | In-app + Email |
| Kampanye sukses — dana cair | Creator | In-app + Email |
| Kampanye gagal — dana direfund | Semua backer | In-app + Email |

### 1.9 Modul Dashboard

**Dashboard Creator**
- Daftar kampanye miliknya beserta status dan progress funding
- Grafik funding harian (kumulatif backing per hari)
- Statistik: total backer, total terkumpul, persentase target
- Tombol post update untuk kampanye yang aktif

**Dashboard Backer**
- Daftar semua kampanye yang pernah didanai beserta statusnya
- Reward tier yang didapat per kampanye
- Ringkasan: total dana pernah dibacking, total refund diterima

**Halaman Saldo (Backer & Creator)**
- Saldo virtual user saat ini
- Riwayat transaksi dengan filter per tipe dan tanggal
- Tombol withdraw (opsional — implementasi mock)

### 1.10 Modul Admin

**Approval Queue**
- List kampanye dengan status `review` yang menunggu diproses
- Admin bisa lihat detail lengkap sebelum approve atau reject
- Jika reject: wajib mengisi catatan alasan penolakan
- Creator mendapat notifikasi setelah kampanye diproses

**Manajemen Kampanye**
- List semua kampanye beserta filter status
- Lihat detail kampanye dan seluruh riwayat backing-nya
- Force-fail kampanye untuk penanganan kasus khusus

**Manajemen User**
- List semua user beserta role masing-masing
- Suspend atau aktifkan kembali akun user
- Lihat riwayat transaksi per user

**Overview Platform**
- Total kampanye dikelompokkan per status
- Total dana terkumpul di seluruh platform
- Total platform fee yang telah diterima
- Grafik kampanye baru per bulan

### 1.11 Status & State Machine

**Campaign Status**

| Status | Deskripsi |
|---|---|
| draft | Baru dibuat atau ditolak — bisa diedit creator |
| review | Menunggu persetujuan admin sebelum tayang ke publik |
| active | Kampanye live, dapat menerima backing dari backer |
| success | Target dana tercapai — dana dicairkan ke creator |
| failed | Deadline lewat, target tidak tercapai — backer di-refund otomatis |

Alur status kampanye:
```
draft -> (submit) -> review -> (approve) -> active -> (deadline & sukses) -> success
```

Jalur alternatif:
- `review -> (reject) -> draft` — bisa diedit dan disubmit ulang
- `active -> (deadline & gagal) -> failed` — refund otomatis ke semua backer

**Backing Status**

| Status | Deskripsi |
|---|---|
| pending | Backing dibuat, menunggu konfirmasi pembayaran |
| completed | Pembayaran sukses, dana masuk escrow |
| refunded | Dana dikembalikan karena kampanye gagal |

```
pending -> (payment success) -> completed -> (campaign failed) -> refunded
```

### 1.12 Business Rules

Aturan bisnis yang wajib diimplementasikan dan tidak boleh dilanggar:

1. Deadline minimum: kampanye harus memiliki deadline minimal 7 hari dari
   tanggal submit.
2. Target minimum: target dana minimal Rp 100.000 per kampanye.
3. Backing minimum: nominal backing minimal Rp 10.000 per transaksi.
4. Creator tidak bisa backing kampanye miliknya sendiri.
5. Email terverifikasi: backing dan pembuatan kampanye hanya bisa dilakukan
   oleh akun terverifikasi.
6. Kuota tier: saat `remaining_quota = 0`, tier tidak bisa dipilih oleh backer
   baru.
7. Escrow: dana tidak pernah langsung masuk ke creator — selalu melalui
   proses lifecycle otomatis.
8. Platform fee: 5% dipotong saat pencairan (disbursement), bukan saat
   backing masuk.
9. Kampanye tidak bisa dihapus setelah status bukan `draft`.
10. Refund sepenuhnya otomatis — tidak ada intervensi manual dalam proses
    pengembalian dana.

---

## BAGIAN 2 — STANDAR PENGGUNAAN PERUSAHAAN

### 2.1 Laravel — Penamaan File & Class

| Tipe | Pola | Benar | Salah |
|---|---|---|---|
| Model | Singular + PascalCase | `Campaign` | `Campaigns` |
| Controller | PascalCase + Controller | `CampaignController` | `campaignController` |
| Request | Verb + Noun + Request | `StoreCampaignRequest` | `CampaignRequest` |
| Service | Noun + Service | `CampaignService` | `campaignSvc` |
| Job | Action + Job | `RefundBackersJob` | `refundJob` |
| Event | PascalCase, past tense | `CampaignFunded` | `campaign_funded` |
| Migration | snake_case + deskriptif | `create_campaigns_table` | `campaigns` |

### 2.2 Laravel — Penamaan Variabel & Method

**Variabel — camelCase, collection selalu plural:**
```php
$campaignId = 1;
$totalAmount = 500000;
$isActive = true;
$campaigns = Campaign::all();       // plural untuk collection
$backings = $campaign->backings;    // plural untuk collection
```

**Method — verb + noun, deskriptif:**
```php
public function getCampaign(int $id) {}
public function storeBacking() {}
public function calculateFee(): float {}
```

**Boolean — wajib prefix `is`/`has`:**
```php
public function isExpired(): bool {}
public function hasReachedTarget(): bool {}
```

Hindari: snake_case untuk variabel, singular untuk collection, nama method
tidak jelas (`process()`, `save()`, `calc()`), dan boolean tanpa prefix
(`expired()`, `target()`).

### 2.3 Laravel — Struktur Folder

```
app/
  Models/                 -> Eloquent models
  Http/
    Controllers/Api/      -> API controllers
    Requests/             -> Form validation (opsional, direkomendasikan)
    Resources/            -> Bentuk response API
  Services/               -> Business logic
  Jobs/                   -> Queue jobs
  Events/                 -> Domain events
  Listeners/
database/migrations/
routes/api.php
```

**Tanggung jawab tiap layer:**

| Layer | Isi |
|---|---|
| Model | Relasi, scope, cast, accessor saja. Tidak ada business logic. |
| Controller | Terima request -> delegate ke Service -> return response. Validasi sederhana boleh di sini. |
| Service | Semua business logic. Di-inject via constructor. |
| Request | Opsional, direkomendasikan untuk validasi kompleks/reusable. |

### 2.4 Laravel — Route & API Convention

```php
Route::prefix('v1')->group(function () {
    Route::apiResource('campaigns', CampaignController::class);
    Route::post('campaigns/{id}/back', [BackingController::class, 'store']);
});
```

| Route name | Method | Endpoint |
|---|---|---|
| `campaigns.index` | GET | `/campaigns` |
| `campaigns.show` | GET | `/campaigns/{id}` |
| `campaigns.store` | POST | `/campaigns` |
| `campaigns.update` | PUT | `/campaigns/{id}` |
| `backings.store` | POST | `/campaigns/{id}/back` |

**Format response wajib konsisten di semua endpoint:**

```php
// Success
return response()->json([
    'success' => true,
    'message' => 'Campaign created',
    'data' => new CampaignResource($campaign),
], 201);

// Error
return response()->json([
    'success' => false,
    'message' => 'Validation failed',
    'errors' => $validator->errors(),
], 422);

// Paginated
return response()->json([
    'success' => true,
    'data' => CampaignResource::collection($campaigns->paginate(10)),
    'meta' => ['total' => ..., 'page' => ...],
]);
```

Validasi: boleh di FormRequest (rekomendasi untuk validasi reusable) atau
langsung `$request->validate([...])` di controller — pilih yang paling sesuai
konteks.

### 2.5 Catatan Khusus Laravel 10

- Scheduler didaftarkan di `app/Console/Kernel.php` method `schedule()` (bukan
  `routes/console.php` seperti Laravel 11+).
- Middleware custom didaftarkan di `app/Http/Kernel.php`.
- Constructor property promotion (`__construct(protected BackingService $backingService)`)
  tetap berlaku normal (fitur PHP 8.1+).

### 2.6 Vue.js — Penamaan File & Komponen

| Tipe | Pola | Benar | Salah |
|---|---|---|---|
| Component | PascalCase.vue | `CampaignCard.vue` | `campaignCard.vue` |
| Page | PascalCase.vue (di `pages/`) | `CampaignDetail.vue` | `campaignDetail.vue` |
| Layout | PascalCase.vue | `MainLayout.vue` | `mainlayout.vue` |
| Composable | use + camelCase.js | `useCampaign.js` | `campaignHelper.js` |
| Store | use + Noun + Store.js | `useCampaignStore.js` | `campaignStore.js` |
| Service | camelCase + Service.js | `campaignService.js` | `CampaignService.js` |
| Utils | camelCase.js | `formatCurrency.js` | `FormatCurrency.js` |

### 2.7 Vue.js — Props, Emits & Composables

**Props — camelCase di script, kebab-case di template:**
```javascript
const props = defineProps({
  campaignId: Number,
  isActive: Boolean,
  targetAmount: {
    type: Number,
    required: true,
  },
})
// di template: :campaign-id="id"
```

**Emits — kebab-case selalu, hindari camelCase:**
```javascript
const emit = defineEmits([
  'backing-submitted',
  'update:modelValue',
  'campaign-updated',
])

emit('backing-submitted', {
  amount: 50000,
  tier: selectedTier,
})
```

**Composable — selalu prefix "use", satu tanggung jawab, return reactive state:**
```javascript
export function useCampaign() {
  const store = useCampaignStore()
  const isLoading = ref(false)

  async function fetchOne(id) {
    isLoading.value = true
    await store.fetch(id)
    isLoading.value = false
  }

  return { isLoading, fetchOne }
}
```

Jangan taruh API call langsung di komponen — pakai composable.

**Reactive variables:**
```javascript
const isLoading = ref(false)   // boolean — is/has prefix
const campaigns = ref([])      // collection — plural noun
const totalAmount = computed(() => backings.value.reduce((s, b) => s + b.amount, 0))
```

### 2.8 Vue.js — Service Layer (Axios per Modul)

Pisahkan HTTP call ke `services/` — composable tidak boleh tahu tentang axios.

**Struktur folder:**
```
src/
  services/
    api.js              -> axios instance
    authService.js      -> /auth endpoints
    campaignService.js
    backingService.js
    notifService.js
  composables/
    useCampaign.js       -> pakai service
    useAuth.js
  stores/
    useCampaignStore.js
```

**Alur tanggung jawab:**
```
Component -> Composable -> Service -> api.js -> Laravel API
```

**`api.js` — instance tunggal:**
```javascript
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export default api
```

**`campaignService.js` — endpoint per modul:**
```javascript
import api from './api'

export const campaignService = {
  getAll: (params) => api.get('/campaigns', { params }),
  getOne: (id) => api.get(`/campaigns/${id}`),
  store: (data) => api.post('/campaigns', data),
  update: (id, d) => api.patch(`/campaigns/${id}`, d),
  back: (id, d) => api.post(`/campaigns/${id}/back`, d),
}
```

**Penggunaan di composable:**
```javascript
import { campaignService } from '@/services/campaignService'
const res = await campaignService.getOne(id) // composable tidak import axios langsung
```

### 2.9 Vue.js — Library Stack Wajib

| Library | Versi | Fungsi |
|---|---|---|
| Vue Router | v4 | Routing & navigation. Gunakan Nested Routes untuk layout. |
| Pinia | v2 | Global state management. Satu store per domain entity. |
| Axios | v1 | HTTP client. Bungkus di `services/api.js` — jangan import langsung di composable. |
| Tailwind CSS | v3 | Utility-first styling. Jangan nulis custom CSS kecuali terpaksa. |
| PrimeVue | v4 | UI component library. Gunakan preset Aura/Lara. Includes Button, DataTable, Dialog, Calendar, dll. |
| PrimeIcons | bundled | 270+ icon via class `pi pi-check`. Sudah bundled bersama PrimeVue. |
| Vee-Validate + Yup | v4 | Form validation. Definisikan schema Yup — jangan validasi manual di event handler. |
| Vue Toastification | v2 | Notifikasi toast. Jangan pakai `alert()` atau `console.log` untuk user feedback. |
| Day.js | v1 | Date formatting. Gunakan untuk format tanggal deadline, sisa hari, dll. |

### 2.10 Timeline Pelatihan — 7 Hari (Rekomendasi)

Target harian bersifat rekomendasi — selesaikan semampu, utamakan pemahaman
daripada mengejar semua fitur.

**Backend Track:**

| Hari | Target | Detail |
|---|---|---|
| H-1 | Setup & Auth | Instalasi Laravel, .env & DB config. Migrasi users. Register, Login, Logout, Email Verification (Sanctum). |
| H-2 | Campaign CRUD | Migration campaigns + categories. Controller. Endpoint: index, show, store, update. |
| H-3 | Tier & Backing | Migration tiers & backings. Tier CRUD. Endpoint backing store + escrow logic. |
| H-4 | Escrow & Transaksi | Migration transactions. Payment mock -> disbursement logic, refund logic. Virtual balance user. |
| H-5 | Jobs & Scheduler | CheckExpiredCampaigns command. DisburseCampaignJob + RefundBackersJob via queue. |
| H-6 | Notifikasi & Dashboard | Notifications table. In-app notif + Mail. Dashboard API creator & backer. Saldo endpoint. |
| H-7 | Admin & Polish | Admin endpoints (approval, user list, force-fail). Testing endpoint + dokumentasi Postman. |

**Frontend Track:**

| Hari | Target | Detail |
|---|---|---|
| H-1 | Setup & Auth | Vite + Vue 3. Install Pinia, Vue Router, Tailwind, Axios, PrimeVue. Halaman Login & Register. |
| H-2 | Campaign Pages | Halaman list kampanye (grid card + filter). Halaman detail: info, progress bar, tier, backer. |
| H-3 | Create & Tier UI | Halaman buat kampanye (form step). UI management tier (add/edit/delete). Preview sebelum submit. |
| H-4 | Backing Flow | Pilih tier / nominal bebas. Payment confirmation dialog. Riwayat backing backer. |
| H-5 | Dashboard | Dashboard creator: stats card, grafik funding. Dashboard backer: list kontribusi & saldo. |
| H-6 | Notifikasi & Admin | Bell icon + badge + dropdown notifikasi. Halaman admin approval queue + user list. |
| H-7 | Polish & Responsive | Responsive mobile. Loading skeleton, error state, empty state. Final QA. |

**FE Only — Mock JSON API** (untuk peserta yang hanya mengerjakan Frontend):

Opsi 1 (rekomendasi) — `json-server` (local, CoFund-shaped):
```bash
npm install -g json-server
npx json-server db.json --port 3001
```
Endpoint otomatis tersedia: `GET /campaigns`, `GET /campaigns/1`,
`POST /campaigns`, `PATCH /campaigns/1`, `GET /backings?campaign_id=1`, dst,
berdasarkan struktur `db.json` yang dibuat menyerupai bentuk data CoFund.

Opsi 2 — DummyJSON (online, no setup): `https://dummyjson.com/products` sebagai
mock campaigns, `https://dummyjson.com/users` sebagai mock users (struktur
beda, perlu adapter di service).

### 2.11 Checklist Akhir Pelatihan

**Backend:**
- Auth: register, login, verify email
- Campaign CRUD + status flow lengkap
- Tier management + quota tracking
- Backing + escrow (collected_amount)
- Transaction: payment, refund, disbursement
- Scheduled job: check-expired berjalan
- Queue job: RefundBackers + Disburse
- Notification: in-app + email terkirim
- Dashboard API creator & backer
- Admin: approval queue + user management
- Semua endpoint terdokumentasi di Postman

**Frontend:**
- Auth pages: login, register, verify
- Campaign list + filter + search
- Campaign detail: progress bar + tiers
- Campaign create form (multi-step)
- Tier management UI
- Backing flow + payment mock dialog
- Dashboard creator (grafik + stats)
- Dashboard backer (riwayat + saldo)
- Notification bell + dropdown
- Admin panel (approval + user list)
- Responsive mobile + error/empty states