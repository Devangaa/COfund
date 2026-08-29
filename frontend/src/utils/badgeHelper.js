/**
 * Helper to get badge styling and label based on campaign status, backing status, transaction type, or role.
 */

export const CAMPAIGN_STATUS = {
  draft: {
    label: 'Draft',
    bg: 'bg-slate-100',
    text: 'text-slate-700',
    border: 'border-slate-200',
    icon: 'pi pi-file-edit',
  },
  review: {
    label: 'Menunggu Review',
    bg: 'bg-amber-50',
    text: 'text-amber-700',
    border: 'border-amber-200',
    icon: 'pi pi-clock',
  },
  active: {
    label: 'Aktif',
    bg: 'bg-blue-50',
    text: 'text-blue-700',
    border: 'border-blue-200',
    icon: 'pi pi-bolt',
  },
  success: {
    label: 'Berhasil',
    bg: 'bg-emerald-50',
    text: 'text-emerald-700',
    border: 'border-emerald-200',
    icon: 'pi pi-check-circle',
  },
  failed: {
    label: 'Gagal',
    bg: 'bg-rose-50',
    text: 'text-rose-700',
    border: 'border-rose-200',
    icon: 'pi pi-times-circle',
  },
  rejected: {
    label: 'Ditolak',
    bg: 'bg-rose-50',
    text: 'text-rose-700',
    border: 'border-rose-200',
    icon: 'pi pi-ban',
  },
}

export const BACKING_STATUS = {
  pending: {
    label: 'Menunggu Pembayaran',
    bg: 'bg-amber-50',
    text: 'text-amber-700',
    border: 'border-amber-200',
    icon: 'pi pi-clock',
  },
  completed: {
    label: 'Berhasil',
    bg: 'bg-emerald-50',
    text: 'text-emerald-700',
    border: 'border-emerald-200',
    icon: 'pi pi-check-circle',
  },
  refunded: {
    label: 'Dana Dikembalikan',
    bg: 'bg-purple-50',
    text: 'text-purple-700',
    border: 'border-purple-200',
    icon: 'pi pi-replay',
  },
}

export const TRANSACTION_TYPE = {
  deposit: {
    label: 'Deposit Saldo',
    bg: 'bg-emerald-50',
    text: 'text-emerald-700',
    border: 'border-emerald-200',
    icon: 'pi pi-arrow-down-left',
    prefix: '+',
  },
  withdrawal: {
    label: 'Penarikan Saldo',
    bg: 'bg-rose-50',
    text: 'text-rose-700',
    border: 'border-rose-200',
    icon: 'pi pi-arrow-up-right',
    prefix: '-',
  },
  payment: {
    label: 'Dukungan Kampanye',
    bg: 'bg-blue-50',
    text: 'text-blue-700',
    border: 'border-blue-200',
    icon: 'pi pi-send',
    prefix: '-',
  },
  refund: {
    label: 'Pengembalian Dana',
    bg: 'bg-teal-50',
    text: 'text-teal-700',
    border: 'border-teal-200',
    icon: 'pi pi-replay',
    prefix: '+',
  },
  disbursement: {
    label: 'Pencairan Dana',
    bg: 'bg-indigo-50',
    text: 'text-indigo-700',
    border: 'border-indigo-200',
    icon: 'pi pi-wallet',
    prefix: '+',
  },
  platform_fee: {
    label: 'Biaya Layanan Platform (5%)',
    bg: 'bg-slate-100',
    text: 'text-slate-700',
    border: 'border-slate-200',
    icon: 'pi pi-percentage',
    prefix: '-',
  },
}

export const ROLE_BADGE = {
  admin: {
    label: 'Admin',
    bg: 'bg-red-50',
    text: 'text-red-700',
    border: 'border-red-200',
  },
  creator: {
    label: 'Kreator',
    bg: 'bg-blue-50',
    text: 'text-blue-700',
    border: 'border-blue-200',
  },
  backer: {
    label: 'Donatur',
    bg: 'bg-slate-100',
    text: 'text-slate-700',
    border: 'border-slate-200',
  },
}
