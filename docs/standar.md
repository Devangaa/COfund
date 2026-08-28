# Vue.js Coding Standards — CoFund Frontend

Dokumen ini berisi standarisasi penamaan, struktur, dan konvensi kode untuk project Vue.js frontend CoFund.

---

## 1. Penamaan File & Komponen

| Tipe | Pola | Benar | Salah |
|---|---|---|---|
| Component | `PascalCase.vue` | `CampaignCard.vue` | `campaignCard.vue` |
| Page | `PascalCase.vue` (di folder `pages/`) | `CampaignDetail.vue` | `campaignDetail.vue` |
| Layout | `PascalCase.vue` | `MainLayout.vue` | `mainlayout.vue` |
| Composable | `use` + `camelCase`.js | `useCampaign.js` | `campaignHelper.js` |
| Store | `use` + `Noun` + `Store.js` | `useCampaignStore.js` | `campaignStore.js` |
| Service | `camelCase` + `Service.js` | `campaignService.js` | `CampaignService.js` |
| Utils | `camelCase`.js | `formatCurrency.js` | `FormatCurrency.js` |

---

## 2. Props, Emits & Composables

### 2.1 Props

```js
// camelCase di script
const props = defineProps({
  campaignId: {
    type: Number,
    required: true,
  },
  isActive: {
    type: Boolean,
    default: false,
  },
  targetAmount: {
    type: Number,
    required: true,
  },
})

// kebab-case di template
// <CampaignCard :campaign-id="id" :is-active="true" />
```

### 2.2 Reactive Variables

```js
const isLoading   = ref(false)
const campaigns   = ref([])
const selectedTier = ref(null)
```

Aturan:
- Boolean: gunakan prefix `is` atau `has` (misal: `isLoading`, `hasError`)
- Koleksi: gunakan plural noun (misal: `campaigns`, `backings`)
- Nilai tunggal: gunakan singular noun (misal: `campaign`, `backing`)

### 2.3 Emits

```js
// kebab-case untuk semua event emit
const emit = defineEmits([
  'backing-submitted',
  'update:modelValue',
  'campaign-updated',
])

// Cara trigger
emit('backing-submitted', {
  amount: 50000,
  tier: selectedTier.value,
})
```

### 2.4 Composable

```js
// Selalu gunakan prefix "use"
export function useCampaign() {
  const store = useCampaignStore()
  const isLoading = ref(false)

  async function fetchOne(id) {
    isLoading.value = true
    try {
      await store.fetch(id)
    } finally {
      isLoading.value = false
    }
  }

  return { isLoading, fetchOne }
}
```

Aturan:
- Composable = satu tanggung jawab (single responsibility)
- Kembalikan reactive state (`ref` / `computed`)
- Jangan pernah melakukan API call langsung di komponen — pakai composable
- Semua API call harus melalui service

---

## 3. Service Layer (Axios per Modul)

Service layer bertanggung jawawab atas semua HTTP request. Composable tidak boleh tahu tentang axios secara langsung.

### 3.1 Struktur Folder

```
src/
├── services/
│   ├── api.js                  # Axios instance tunggal
│   ├── authService.js          # Endpoint otentikasi
│   ├── campaignService.js      # Endpoint kampanye
│   ├── backingService.js       # Endpoint backing
│   └── notifService.js         # Endpoint notifikasi
├── composables/
│   ├── useCampaign.js
│   └── useAuth.js
└── stores/
    └── useCampaignStore.js
```

### 3.2 Alur Tanggung Jawab

```
Component → Composable → Service → api.js (axios instance) → Laravel API
```

### 3.3 api.js — Axios Instance Tunggal

```js
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
```

### 3.4 Service per Modul

```js
// campaignService.js
import api from './api'

export const campaignService = {
  getAll: (params) => api.get('/campaigns', { params }),

  getOne: (id) => api.get(`/campaigns/${id}`),

  store: (data) => api.post('/campaigns', data),

  update: (id, data) => api.patch(`/campaigns/${id}`, data),

  back: (id, data) => api.post(`/campaigns/${id}/back`, data),
}
```

Penggunaan di composable:

```js
import { campaignService } from '@/services/campaignService'

const res = await campaignService.getOne(id)
// composable tidak perlu import axios
```

---

## 4. Library Stack Wajib

| Library | Versi | Kegunaan |
|---|---|---|
| Vue Router | v4 | Routing & navigation. Gunakan **Nested Routes** untuk layout. |
| Tailwind CSS | v3 | Utility-first styling. Jangan tulis custom CSS kecuali terpaksa. |
| Vee-Validate + Yup | v4 | Form validation. Definisikan schema Yup — jangan validasi manual di event handler. |
| Pinia | v2 | Global state management. Satu store per domain entity. |
| PrimeVue | v4 | UI component library. Gunakan preset Aura/Lara. Termasuk Button, DataTable, Dialog, Calendar, dll. |
| Vue Toastification | v2 | Notifikasi toast. Jangan pakai `alert()` atau `console.log` untuk user feedback. |
| Axios | v1 | HTTP client. Bungkus di `services/api.js` — jangan import langsung di composable. |
| PrimeIcons | bundled | 270+ icon via class `pi pi-check`. Sudah bundled dengan PrimeVue, tidak perlu install terpisah. |
| Day.js | v1 | Date formatting. Gunakan untuk format tanggal deadline, sisa hari, dll. |