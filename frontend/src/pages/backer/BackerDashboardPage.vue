<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { backerService } from '@/services/backerService'
import { backingService } from '@/services/backingService'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import UpgradeToCreatorModal from '@/components/common/UpgradeToCreatorModal.vue'
import { getImageUrl, onImageError } from '@/utils/imageHelper'

const authStore = useAuthStore()
const stats = ref(null)
const backings = ref([])
const isLoading = ref(true)
const isUpgradeModalOpen = ref(false)

async function loadData() {
  isLoading.value = true
  try {
    const [statsRes, backingsRes] = await Promise.all([
      backerService.getStatistics(),
      backingService.getMyBackings({ per_page: 20 }),
    ])
    stats.value = statsRes.data?.data || statsRes.data
    backings.value = backingsRes.data?.data || []
  } catch {
    stats.value = null
    backings.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Dashboard Backer</span>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          Portofolio Dukungan Saya
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Pantau seluruh proyek yang telah Anda dukung serta jaminan pengembalian virtual escrow.
        </p>
      </div>

      <div class="flex items-center gap-3 flex-wrap">
        <button
          v-if="authStore.isBacker"
          @click="isUpgradeModalOpen = true"
          class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition flex-shrink-0"
        >
          <i class="pi pi-bolt text-xs"></i>
          <span>Jadi Kreator (Upgrade)</span>
        </button>

        <RouterLink
          to="/campaigns"
          class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-lg shadow-blue-600/20 transition flex-shrink-0"
        >
          <i class="pi pi-search text-xs"></i>
          <span>Jelajah Kampanye</span>
        </RouterLink>
      </div>
    </div>

    <!-- Upgrade to Creator Banner if Backer -->
    <div
      v-if="authStore.isBacker"
      class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-blue-950 to-indigo-950 p-6 sm:p-8 text-white border border-blue-900/50 shadow-xl"
    >
      <div class="absolute -right-10 -bottom-10 w-52 h-52 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-2 max-w-2xl">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
            <i class="pi pi-sparkles text-xs"></i>
            <span>Peluang Inisiator</span>
          </div>
          <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight">
            Punya Ide Kreatif atau Inovasi yang Ingin Didanai?
          </h3>
          <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
            Upgrade akun Anda menjadi <strong>Kreator</strong> secara gratis. Mulai buat kampanye galang dana, tentukan paket reward eksklusif, dan jangkau dukungan dari komunitas CoFund.
          </p>
        </div>

        <button
          @click="isUpgradeModalOpen = true"
          class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-amber-500/25 transition transform hover:scale-105 flex items-center justify-center gap-2 flex-shrink-0"
        >
          <i class="pi pi-bolt"></i>
          <span>Mulai Jadi Kreator Sekarang</span>
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-if="isLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="i in 4" :key="i" class="h-28 rounded-2xl skeleton-shimmer"></div>
    </div>

    <div v-else-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Backed -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Total Dana Didukung</span>
          <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <i class="pi pi-heart-fill text-sm text-rose-500"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ formatCurrency(stats.total_backed) }}
        </div>
        <div class="text-[11px] text-slate-400">
          Dukungan sukses
        </div>
      </div>

      <!-- Total Campaigns Backed -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Kampanye Didukung</span>
          <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <i class="pi pi-th-large text-sm"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ stats.total_campaigns_backed }}
        </div>
        <div class="text-[11px] text-slate-400">
          Inisiatif kreatif
        </div>
      </div>

      <!-- Total Backings count -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Total Transaksi</span>
          <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
            <i class="pi pi-send text-sm"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ stats.total_backings }}
        </div>
        <div class="text-[11px] text-slate-400">
          Kali donasi
        </div>
      </div>

      <!-- Total Refunded -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Refund Diterima</span>
          <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
            <i class="pi pi-replay text-sm"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ formatCurrency(stats.total_refunded) }}
        </div>
        <div class="text-[11px] text-slate-400">
          Dari proyek yang tidak lolos
        </div>
      </div>
    </div>

    <!-- Backed Campaigns Table -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Riwayat Dukungan Proyek</h3>
          <p class="text-xs text-slate-500">Daftar semua kontribusi yang pernah Anda salurkan.</p>
        </div>
      </div>

      <SkeletonLoader v-if="isLoading" type="table" :count="4" />

      <div v-else-if="backings.length === 0" class="text-center py-12 text-slate-400">
        <i class="pi pi-heart text-3xl mb-2 block text-slate-300"></i>
        Anda belum pernah mendanai proyek. Jelajahi kampanye dan berikan dukungan pertama Anda!
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50">
              <th class="py-3 px-4">Kampanye</th>
              <th class="py-3 px-4">Reward Tier</th>
              <th class="py-3 px-4">Nominal</th>
              <th class="py-3 px-4">Status Backing</th>
              <th class="py-3 px-4">Tanggal</th>
              <th class="py-3 px-4 text-right">Detail</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="b in backings"
              :key="b.id"
              class="hover:bg-slate-50/80 transition"
            >
              <td class="py-4 px-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-slate-100 overflow-hidden flex-shrink-0">
                    <img
                      :src="getImageUrl(b.campaign?.images?.[0]?.url)"
                      @error="onImageError"
                      class="w-full h-full object-cover"
                    />
                  </div>
                  <div>
                    <RouterLink
                      :to="`/campaigns/${b.campaign?.slug}`"
                      class="font-bold text-slate-900 hover:text-blue-600 transition line-clamp-1"
                    >
                      {{ b.campaign?.title }}
                    </RouterLink>
                    <div class="text-[11px] text-slate-400">
                      Inisiator: {{ b.campaign?.creator?.name || 'Kreator' }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="py-4 px-4 font-semibold text-slate-700">
                {{ b.tier?.name || 'Donasi Bebas (Tanpa Tier)' }}
              </td>
              <td class="py-4 px-4 font-bold text-blue-600">
                {{ formatCurrency(b.amount) }}
              </td>
              <td class="py-4 px-4">
                <StatusBadge type="backing" :value="b.status" size="sm" />
              </td>
              <td class="py-4 px-4 text-slate-500">
                {{ formatDate(b.created_at) }}
              </td>
              <td class="py-4 px-4 text-right">
                <RouterLink
                  :to="`/campaigns/${b.campaign?.slug}`"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition"
                >
                  Buka Proyek
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Upgrade to Creator Modal -->
    <UpgradeToCreatorModal v-model:visible="isUpgradeModalOpen" />
  </div>
</template>
