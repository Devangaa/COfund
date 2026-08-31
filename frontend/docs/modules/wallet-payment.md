# Modul Frontend: Dompet Digital & Pembayaran (Wallet & Payment Frontend Module)

## 1. Judul & Deskripsi Modul

Modul Frontend Dompet Digital mengelola antarmuka saldo virtual pengguna, meliputi kartu saldo FinTech Navy, aksi pengisian saldo instan (*Deposit Modal* dengan tombol preset nominal), penarikan saldo (*Withdraw Modal*), dan tabel mutasi buku besar transaksi (*Transaction History Table*) yang dilengkapi filter tipe dan pagination.

**Base Route:** `/wallet` dan `/transactions`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **View Pages** | `frontend/src/pages/backer/WalletPage.vue` | Tampilan kartu saldo FinTech & ringkasan mutasi |
| | `frontend/src/pages/backer/TransactionsPage.vue` | Tampilan laporan riwayat transaksi lengkap |
| **Domain Modals** | `frontend/src/components/wallet/DepositModal.vue` | Dialog modal deposit instan dengan tombol preset |
| | `frontend/src/components/wallet/WithdrawModal.vue` | Dialog modal penarikan saldo |
| | `frontend/src/components/wallet/TransactionTable.vue` | Tabel riwayat mutasi debit/kredit |
| **Pinia Store** | `frontend/src/stores/useWalletStore.js` | State transaksi, processing state, aksi deposit/withdraw |
| **Composable** | `frontend/src/composables/useWallet.js` | Abstraksi logika modal & pemuatan transaksi |
| **Service Layer** | `frontend/src/services/walletService.js` | Wrapper API endpoint `/api/v1/wallet/*` |

### Diagram Alur Deposit & Saldo Reaktif

```
User Klik "Isi Saldo (Deposit)" di /wallet
        │
        ▼
[ Buka DepositModal.vue ]
        │
        ├─► Pilih Preset Nominal (misal: Rp 50.000 / Rp 100.000)
        ├─► Validasi: Min Rp 10.000, Max Rp 100.000.000
        │
        ▼
[ Klik Submit Top-up ]
        │
        ▼
[ POST /api/v1/wallet/deposit ]
        │
        ├─► Sukses (HTTP 201)
        │     ├─► authStore.fetchMe() ──► Saldo Terupdate Otomatis di Navbar & Kartu
        │     ├─► walletStore.fetchTransactions() ──► Tabel Mutasi Ter-refresh
        │     ├─► Tutup DepositModal & Tampilkan Toast Sukses
        │     └─► Pengguna Siap Mendanai Kampanye
        │
        └─► Gagal
              └─► Tampilkan Toast Error
```

---

## 3. Struktur File Terkait

```
frontend/src/
├── components/
│   └── wallet/
│       ├── DepositModal.vue
│       ├── TransactionTable.vue
│       └── WithdrawModal.vue
├── composables/
│   └── useWallet.js
├── pages/
│   └── backer/
│       ├── TransactionsPage.vue
│       └── WalletPage.vue
├── services/
│   ├── transactionService.js
│   └── walletService.js
└── stores/
    └── useWalletStore.js
```

---

## 4. Rincian Rute & Halaman (Route Pages)

### 4.1 Halaman Dompet Saldo (`/wallet`)
- **Meta:** `{ requiresAuth: true }`
- **Fitur Utama:**
  - Kartu Saldo FinTech Navy berglow lembut menampilkan `authStore.balance`.
  - Tombol aksi: **Isi Saldo (Deposit)** dan **Tarik Saldo (Withdraw)**.
  - Kartu ringkasan Virtual Escrow Protocol.
  - Tabel Mutasi Terkini dengan filter tipe transaksi.

---

## 5. Skema Sumber Daya (State Interface)

```typescript
interface WalletState {
  transactions: Array<Transaction>;
  isLoading: boolean;
  isProcessing: boolean;
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
import DepositModal from '@/components/wallet/DepositModal.vue'

describe('DepositModal.vue', () => {
  it('validates minimum deposit amount correctly', async () => {
    const wrapper = mount(DepositModal, {
      props: { visible: true, isProcessing: false }
    })
    const input = wrapper.find('input[type="number"]')
    await input.setValue(5000)
    await wrapper.find('form').trigger('submit.prevent')
    expect(wrapper.text()).toContain('Nominal deposit minimal adalah Rp 10.000')
  })
})
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario UI | Input | Hasil UI yang Diharapkan |
|---|---|---|---|
| 1 | Klik tombol preset Rp 100.000 | Klik tombol | Nilai input berubah menjadi 100000 |
| 2 | Submit deposit Rp 100.000 | Klik Top-up | Modal tertutup, saldo bertambah, toast sukses |
| 3 | Input withdraw melebihi saldo | Input nominal lebih besar | Tombol disabled / validasi saldo tidak cukup |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Saldo tidak bertambah setelah top-up | Cache store belum refresh | `useWalletStore` secara otomatis memanggil `authStore.fetchMe()` pasca deposit sukses. |

---

## 9. Matriks RBAC

| Rute Halaman | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `/wallet` | Redirect Login | ✓ | ✓ | ✓ |
| `/transactions` | Redirect Login | ✓ | ✓ | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected Result |
|---|---|---|---|---|
| `FE-WAL-001` | Deposit preset nominal | Positive | Klik preset Rp 50.000 | Input terisi 50000 |
| `FE-WAL-002` | Validasi input di bawah 10.000 | Negative | Input 5000 | Pesan error validasi muncul |
