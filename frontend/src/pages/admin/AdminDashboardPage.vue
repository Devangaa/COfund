<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAdminStore } from '@/stores/useAdminStore'
import { formatCurrency } from '@/utils/formatCurrency'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const adminStore = useAdminStore()
const stats = ref(null)

onMounted(async () => {
  stats.value = await adminStore.fetchStatistics()
})
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Ikhtisar Platform</span>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          Admin Dashboard Analytics
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Statistik performa agregat platform, volume pendanaan, dan pendapatan fee platform.
        </p>
      </div>

      <RouterLink
        to="/admin/campaigns"
        class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-500/20 transition flex-shrink-0"
      >
        <i class="pi pi-check-square text-xs"></i>
        <span>Tinjau Approval Queue</span>
      </RouterLink>
    </div>

    <!-- Skeleton when loading -->
    <SkeletonLoader v-if="adminStore.isLoading" type="card" :count="4" />

    <div v-else-if="stats" class="space-y-8">
      <!-- 6 Metrics Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Total Platform Funds -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
          <div class="flex items-center justify-between text-slate-500">
            <span class="text-xs font-bold uppercase tracking-wider">Total Dana Terkumpul</span>
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
              <i class="pi pi-wallet text-sm"></i>
            </div>
          </div>
          <div class="text-xl sm:text-2xl font-black text-slate-900">
            {{ formatCurrency(stats.total_collected) }}
          </div>
          <div class="text-[11px] text-slate-500">
            Dari target {{ formatCurrency(stats.total_target, true) }}
          </div>
        </div>

        <!-- Platform Fee Revenue -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
          <div class="flex items-center justify-between text-slate-500">
            <span class="text-xs font-bold uppercase tracking-wider">Pendapatan Fee (5%)</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <i class="pi pi-percentage text-sm"></i>
            </div>
          </div>
          <div class="text-xl sm:text-2xl font-black text-emerald-600">
            {{ formatCurrency(stats.total_fee || stats.total_fees || 0) }}
          </div>
          <div class="text-[11px] text-slate-500">
            Platform revenue disbursement
          </div>
        </div>

        <!-- Total Users -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
          <div class="flex items-center justify-between text-slate-500">
            <span class="text-xs font-bold uppercase tracking-wider">Total Pengguna Terdaftar</span>
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
              <i class="pi pi-users text-sm"></i>
            </div>
          </div>
          <div class="text-xl sm:text-2xl font-black text-slate-900">
            {{ stats.total_users }}
          </div>
          <div class="text-[11px] text-slate-500">
            Backer & Creator
          </div>
        </div>

        <!-- Total Campaigns -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
          <div class="flex items-center justify-between text-slate-500">
            <span class="text-xs font-bold uppercase tracking-wider">Total Kampanye</span>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
              <i class="pi pi-th-large text-sm"></i>
            </div>
          </div>
          <div class="text-xl sm:text-2xl font-black text-slate-900">
            {{ stats.total_campaigns }}
          </div>
          <div class="text-[11px] text-slate-500">
            Seluruh status
          </div>
        </div>

        <!-- Total Backings Count -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
          <div class="flex items-center justify-between text-slate-500">
            <span class="text-xs font-bold uppercase tracking-wider">Jumlah Backing Donasi</span>
            <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
              <i class="pi pi-heart text-sm"></i>
            </div>
          </div>
          <div class="text-xl sm:text-2xl font-black text-slate-900">
            {{ stats.total_backings }}
          </div>
          <div class="text-[11px] text-slate-500">
            Transaksi pendanaan
          </div>
        </div>

        <!-- Completion Rate -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
          <div class="flex items-center justify-between text-slate-500">
            <span class="text-xs font-bold uppercase tracking-wider">Tingkat Keberhasilan</span>
            <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
              <i class="pi pi-check-circle text-sm"></i>
            </div>
          </div>
          <div class="text-xl sm:text-2xl font-black text-slate-900">
            {{ Number(stats.completion_rate || 0).toFixed(1) }}%
          </div>
          <div class="text-[11px] text-slate-500">
            Target tercapai vs gagal
          </div>
        </div>
      </div>

      <!-- Campaign Status Distribution Cards -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-slate-900">Distribusi Status Kampanye</h3>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div class="p-4 rounded-2xl bg-amber-50/50 border border-amber-200/60">
            <div class="text-xs text-amber-800 font-semibold">Menunggu Review</div>
            <div class="text-2xl font-black text-amber-600 mt-1">
              {{ stats.status_distribution?.review || 0 }}
            </div>
          </div>
          <div class="p-4 rounded-2xl bg-blue-50/50 border border-blue-200/60">
            <div class="text-xs text-blue-800 font-semibold">Aktif</div>
            <div class="text-2xl font-black text-blue-600 mt-1">
              {{ stats.status_distribution?.active || 0 }}
            </div>
          </div>
          <div class="p-4 rounded-2xl bg-emerald-50/50 border border-emerald-200/60">
            <div class="text-xs text-emerald-800 font-semibold">Berhasil</div>
            <div class="text-2xl font-black text-emerald-600 mt-1">
              {{ stats.status_distribution?.success || 0 }}
            </div>
          </div>
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
            <div class="text-xs text-slate-600 font-semibold">Draft</div>
            <div class="text-2xl font-black text-slate-800 mt-1">
              {{ stats.status_distribution?.draft || 0 }}
            </div>
          </div>
          <div class="p-4 rounded-2xl bg-rose-50/50 border border-rose-200/60">
            <div class="text-xs text-rose-800 font-semibold">Gagal</div>
            <div class="text-2xl font-black text-rose-600 mt-1">
              {{ stats.status_distribution?.failed || 0 }}
            </div>
          </div>
        </div>
      </div>

      <!-- Top Campaigns Table -->
      <div v-if="stats.top_campaigns && stats.top_campaigns.length > 0" class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-slate-900">Kampanye Teratas Platform</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider bg-slate-50/80">
                <th class="py-3 px-4">Judul Kampanye</th>
                <th class="py-3 px-4">Terkumpul</th>
                <th class="py-3 px-4">Target</th>
                <th class="py-3 px-4">Jumlah Backer</th>
                <th class="py-3 px-4">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="tc in stats.top_campaigns" :key="tc.id" class="hover:bg-slate-50 transition">
                <td class="py-3.5 px-4 font-bold text-slate-900">
                  <RouterLink :to="`/admin/campaigns/${tc.slug}`" class="hover:text-blue-600 transition">
                    {{ tc.title }}
                  </RouterLink>
                </td>
                <td class="py-3.5 px-4 font-bold text-emerald-600">
                  {{ formatCurrency(tc.collected_amount) }}
                </td>
                <td class="py-3.5 px-4 text-slate-500">
                  {{ formatCurrency(tc.target_amount) }}
                </td>
                <td class="py-3.5 px-4 text-slate-700 font-semibold">
                  {{ tc.backings_count }}
                </td>
                <td class="py-3.5 px-4">
                  <StatusBadge type="campaign" :value="tc.status" size="sm" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
