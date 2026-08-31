# Modul Frontend: Eksplorasi & Pembuatan Kampanye (Campaigns Frontend Module)

## 1. Judul & Deskripsi Modul

Modul Frontend Kampanye mengelola antarmuka katalog publik, pencarian dan filter kategori interaktif (*dynamic search & chips*), halaman detail kampanye dengan tab visual (Cerita, Reward Tiers, dan Kabar Proyek), dialog modal pendanaan (*BackingDialog*), serta form pembuatan kampanye multi-langkah (*stepper wizard*) dengan upload gambar dropzone.

**Base Route:** `/campaigns`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **View Pages** | `frontend/src/pages/public/CampaignListPage.vue` | Katalog pencarian & filter proyek |
| | `frontend/src/pages/public/CampaignDetailPage.vue` | Detail lengkap kampanye & hero CTA |
| | `frontend/src/pages/creator/CreateCampaignPage.vue` | Form pembuatan kampanye baru |
| | `frontend/src/pages/creator/EditCampaignPage.vue` | Form edit draf kampanye |
| **Domain Components** | `frontend/src/components/campaign/CampaignCard.vue` | Kartu kampanye katalog interaktif |
| | `frontend/src/components/campaign/RewardTierCard.vue` | Kartu paket reward & sisa kuota |
| | `frontend/src/components/campaign/BackingDialog.vue` | Modal dialog pembayaran donasi |
| **Pinia Store** | `frontend/src/stores/useCampaignStore.js` | State katalog, filter, dan detail kampanye |
| **Service Layer** | `frontend/src/services/campaignService.js` | Wrapper API kampanye & galeri foto |

### Diagram Alur Interaksi Backing

```
User Klik "Dukung Kampanye"
        │
        ▼
[ Buka BackingDialog.vue ]
        │
        ├─► Pilih Preset Nominal / Input Bebas
        ├─► Pilih Paket Reward Tier (Opsional)
        │
        ▼
[ Klik Lanjut ke Pembayaran ]
        │
        ▼
[ Konfirmasi Simulasi Transaksi Virtual Escrow ]
        │
        ▼
[ Submit ke Backend: POST /api/v1/campaigns/{slug}/back ]
        │
        ├─► Sukses ──► Tampilkan Toast Sukses, Update Saldo User, Tutup Modal
        └─► Gagal  ──► Tampilkan Pesan Error Validasi Saldo / Kuota
```

---

## 3. Struktur File Terkait

```
frontend/src/
├── components/
│   └── campaign/
│       ├── BackingDialog.vue
│       ├── CampaignCard.vue
│       ├── CampaignGrid.vue
│       ├── CampaignUpdateList.vue
│       └── RewardTierCard.vue
├── pages/
│   ├── creator/
│   │   ├── CreateCampaignPage.vue
│   │   └── EditCampaignPage.vue
│   └── public/
│       ├── CampaignDetailPage.vue
│       └── CampaignListPage.vue
├── services/
│   └── campaignService.js
└── stores/
    └── useCampaignStore.js
```

---

## 4. Rincian Rute & Halaman (Route Pages)

### 4.1 Halaman Katalog Kampanye (`/campaigns`)
- **Fitur:** Input pencarian dengan debouncing 400ms, filter kategori horizontal chips, dropdown sort (`latest`, `popular`, `oldest`), dan skeleton loading shimmer.

---

### 4.2 Halaman Detail Kampanye (`/campaigns/:slug`)
- **Fitur:**
  - Hero Header dengan persentase capaian dan progress bar dinamis.
  - Tombol Warm Amber CTA *"Dukung Kampanye Sekarang"*.
  - Tab navigasi (*Cerita Proyek*, *Pilihan Reward*, *Kabar Kemajuan*).
  - Galeri thumbnail gambar proyek.

---

### 4.3 Halaman Buat Kampanye (`/campaigns/create`)
- **Meta:** `{ requiresAuth: true, role: 'creator', requiresVerified: true }`
- **Fitur:** Form stepper multi-langkah (Informasi Dasar, Media Foto, Reward Tiers, dan Ringkasan).

---

## 5. Skema Sumber Daya (State Interface)

```typescript
interface CampaignState {
  campaigns: Array<Campaign>;
  currentCampaign: Campaign | null;
  isLoading: boolean;
  pagination: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
}
```

---

## 6. Pengujian Unit & Komponen (Vitest Script)

```javascript
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import CampaignCard from '@/components/campaign/CampaignCard.vue'

describe('CampaignCard.vue', () => {
  it('renders campaign title and progress percentage properly', () => {
    const wrapper = mount(CampaignCard, {
      props: {
        campaign: {
          id: 1,
          title: 'Sensor Udara Pintar',
          slug: 'sensor-udara-pintar',
          progress_percentage: 65,
          target_amount: 50000000,
          collected_amount: 32500000,
          images: []
        }
      }
    })
    expect(wrapper.text()).toContain('Sensor Udara Pintar')
    expect(wrapper.text()).toContain('65%')
  })
})
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario UI | Input | Hasil UI yang Diharapkan |
|---|---|---|---|
| 1 | Klik filter kategori 'Teknologi' | Klik chip | Grid kampanye reload otomatis menampilkan kategori teknologi |
| 2 | Cari kata kunci 'Sensor' | Ketik di search bar | Daftar proyek tersaring setelah jeda debounce |
| 3 | Donatur klik donasi | Klik Dukung | Terbuka BackingDialog modal |
| 4 | Donatur saldo kurang | Konfirmasi donasi | Muncul pesan error saldo dompet tidak mencukupi |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Tombol dukung tidak merespon | User belum login | Sistem otomatis membuka modal login atau me-redirect ke `/login`. |
| Foto thumbnail kampanye blank | Path storage URL keliru | Pastikan `VITE_STORAGE_BASE_URL` mengarah ke root backend server. |

---

## 9. Matriks RBAC

| Halaman / Aksi | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| Lihat Katalog & Detail | ✓ | ✓ | ✓ | ✓ |
| Buka Modal Backing | Redirect Login | ✓ | ✓ (Bukan owner) | ✗ |
| Buka Halaman Buat Kampanye | Redirect Login | Redirect 404 | ✓ | ✗ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected Result |
|---|---|---|---|---|
| `FE-CAMP-001` | Render daftar kampanye | Positive | Route `/campaigns` | Menampilkan minimal 1 kartu proyek |
| `FE-CAMP-002` | Reset filter pencarian | Positive | Klik tombol reset | Filter kembali ke nilai default |
| `FE-CAMP-003` | Pilih tier yang sold out | Business Logic | Tier stok 0 | Tombol tier terdisable / 'Slot Habis' |
