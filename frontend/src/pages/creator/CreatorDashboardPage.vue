<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useCampaignStore } from '@/stores/useCampaignStore'
import { creatorService } from '@/services/creatorService'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import ProgressBar from '@/components/common/ProgressBar.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import CreateUpdateDialog from '@/components/campaign/CreateUpdateDialog.vue'
import { getImageUrl, onImageError } from '@/utils/imageHelper'
import { useConfirm } from '@/composables/useConfirm'

const campaignStore = useCampaignStore()
const { confirm: showConfirmModal } = useConfirm()

const stats = ref(null)
const isStatsLoading = ref(true)
const selectedStatusFilter = ref('') // '' (Semua) | 'draft' | 'review' | 'active' | 'success' | 'failed'
const selectedUpdateSlug = ref('')
const isUpdateModalOpen = ref(false)

const statusFilterTabs = computed(() => {
  const dist = stats.value?.status_distribution || {}
  const total = stats.value?.total_campaigns || 0
  return [
    { label: 'Semua', value: '', count: total, icon: 'pi pi-th-large' },
    { label: 'Draft', value: 'draft', count: dist.draft || 0, icon: 'pi pi-file-edit' },
    { label: 'Menunggu Review', value: 'review', count: dist.review || 0, icon: 'pi pi-clock' },
    { label: 'Aktif', value: 'active', count: dist.active || 0, icon: 'pi pi-bolt' },
    { label: 'Berhasil', value: 'success', count: dist.success || 0, icon: 'pi pi-check-circle' },
    { label: 'Gagal', value: 'failed', count: dist.failed || 0, icon: 'pi pi-times-circle' },
  ]
})

async function loadStats() {
  isStatsLoading.value = true
  try {
    const res = await creatorService.getStatistics()
    stats.value = res.data?.data || res.data
  } catch {
    stats.value = null
  } finally {
    isStatsLoading.value = false
  }
}

async function loadCampaigns(status = selectedStatusFilter.value) {
  selectedStatusFilter.value = status
  const params = {
    scope: 'mine',
    per_page: 50,
  }
  if (status) {
    params.status = status
  }
  await campaignStore.fetchCreatorCampaigns(params)
}

async function handleSubmitReview(slug) {
  const isConfirmed = await showConfirmModal({
    title: 'Kirim untuk Peninjauan',
    message: 'Kirim proposal kampanye ini ke Administrator untuk diverifikasi dan disetujui? Status akan berubah menjadi Menunggu Review.',
    type: 'info',
    confirmText: 'Ya, Ajukan Review',
    cancelText: 'Batal',
  })

  if (isConfirmed) {
    await campaignStore.submitForReview(slug)
    await loadCampaigns()
    await loadStats()
  }
}

async function handleDeleteCampaign(slug) {
  const isConfirmed = await showConfirmModal({
    title: 'Hapus Draft Kampanye',
    message: 'Apakah Anda yakin ingin menghapus draft kampanye ini secara permanen? Tindakan ini tidak dapat dibatalkan.',
    type: 'danger',
    confirmText: 'Ya, Hapus Permanen',
    cancelText: 'Batal',
  })

  if (isConfirmed) {
    await campaignStore.deleteCampaign(slug)
    await loadStats()
  }
}

function openPostUpdate(slug) {
  selectedUpdateSlug.value = slug
  isUpdateModalOpen.value = true
}

onMounted(() => {
  loadStats()
  loadCampaigns('')
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Creator Studio</span>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          Dashboard Kreator
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Pantau performa pendanaan, kelola status kampanye (draft, review, active, success, failed), dan kelola reward tier.
        </p>
      </div>

      <RouterLink
        to="/creator/campaigns/create"
        class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-lg shadow-blue-600/20 transition flex-shrink-0"
      >
        <i class="pi pi-plus text-xs"></i>
        <span>Buat Kampanye Baru</span>
      </RouterLink>
    </div>

    <!-- Stats Metric Cards -->
    <div v-if="isStatsLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="i in 4" :key="i" class="h-28 rounded-2xl skeleton-shimmer"></div>
    </div>

    <div v-else-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Collected -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Total Terkumpul</span>
          <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <i class="pi pi-wallet text-sm"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ formatCurrency(stats.total_collected) }}
        </div>
        <div class="text-[11px] text-slate-400">
          Dari target {{ formatCurrency(stats.total_target, true) }}
        </div>
      </div>

      <!-- Total Backers -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Total Backer</span>
          <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <i class="pi pi-users text-sm"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ stats.total_backings }}
        </div>
        <div class="text-[11px] text-slate-400">
          Orang telah berdonasi
        </div>
      </div>

      <!-- Completion Rate -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Tingkat Capaian</span>
          <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
            <i class="pi pi-percentage text-sm"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ Number(stats.completion_rate || 0).toFixed(1) }}%
        </div>
        <div class="text-[11px] text-slate-400">
          Rata-rata target tercapai
        </div>
      </div>

      <!-- Total Campaigns -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-xs font-semibold">Total Kampanye</span>
          <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
            <i class="pi pi-th-large text-sm"></i>
          </div>
        </div>
        <div class="text-xl sm:text-2xl font-black text-slate-900">
          {{ stats.total_campaigns }}
        </div>
        <div class="text-[11px] text-slate-400">
          Aktif: {{ stats.status_distribution?.active || 0 }} &bull; Draft: {{ stats.status_distribution?.draft || 0 }}
        </div>
      </div>
    </div>

    <!-- Campaigns List Card with Horizontal Status Category Tabs -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Daftar Kampanye Saya</h3>
          <p class="text-xs text-slate-500">Pilih kategori status di bawah untuk memfilter daftar kampanye.</p>
        </div>
      </div>

      <!-- Horizontal Status Filter Menu (Left to Right) -->
      <div class="flex items-center gap-2 overflow-x-auto pb-2 pt-1 border-b border-slate-100 scrollbar-none">
        <button
          v-for="tab in statusFilterTabs"
          :key="tab.value"
          @click="loadCampaigns(tab.value)"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 flex items-center gap-2 flex-shrink-0',
            selectedStatusFilter === tab.value
              ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80',
          ]"
        >
          <i :class="[tab.icon, 'text-xs']"></i>
          <span>{{ tab.label }}</span>
          <span
            :class="[
              'px-2 py-0.5 rounded-full text-[10px] font-bold',
              selectedStatusFilter === tab.value
                ? 'bg-white/20 text-white'
                : 'bg-slate-200 text-slate-700',
            ]"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <SkeletonLoader v-if="campaignStore.isLoading" type="table" :count="4" />

      <div v-else-if="campaignStore.creatorCampaigns.length === 0" class="text-center py-12 text-slate-400">
        <i class="pi pi-folder-open text-3xl mb-2 block"></i>
        <p class="text-sm font-semibold text-slate-600">Tidak ada kampanye pada kategori ini.</p>
        <p class="text-xs text-slate-400 mt-1">
          {{ selectedStatusFilter === 'draft' ? 'Anda belum memiliki draf kampanye yang tersimpan.' : 'Belum ada data kampanye untuk status yang dipilih.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50">
              <th class="py-3 px-4">Kampanye</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Terkumpul / Target</th>
              <th class="py-3 px-4">Deadline</th>
              <th class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="c in campaignStore.creatorCampaigns"
              :key="c.id"
              class="hover:bg-slate-50/80 transition"
            >
              <td class="py-4 px-4">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 rounded-xl bg-slate-100 overflow-hidden flex-shrink-0">
                    <img
                      :src="getImageUrl(c.images?.[0]?.url)"
                      @error="onImageError"
                      class="w-full h-full object-cover"
                    />
                  </div>
                  <div>
                    <RouterLink
                      :to="`/campaigns/${c.slug}`"
                      class="font-bold text-slate-900 hover:text-blue-600 transition line-clamp-1"
                    >
                      {{ c.title }}
                    </RouterLink>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                      {{ c.category?.name || 'Umum' }} &bull; {{ c.tiers?.length || 0 }} Tiers
                    </div>
                  </div>
                </div>
              </td>
              <td class="py-4 px-4">
                <StatusBadge type="campaign" :value="c.status" size="sm" />
                <div v-if="c.rejection_note" class="text-[10px] text-rose-600 mt-1 max-w-xs line-clamp-2">
                  Catatan Admin: {{ c.rejection_note }}
                </div>
              </td>
              <td class="py-4 px-4">
                <div class="font-bold text-slate-900">
                  {{ formatCurrency(c.collected_amount) }}
                </div>
                <div class="text-[11px] text-slate-400">
                  dari {{ formatCurrency(c.target_amount, true) }} ({{ (Number(c.progress_percentage) || 0).toFixed(0) }}%)
                </div>
                <div class="w-24 mt-1">
                  <ProgressBar :percentage="Number(c.progress_percentage) || 0" height="h-1.5" :animated="false" />
                </div>
              </td>
              <td class="py-4 px-4 text-slate-600">
                {{ formatDate(c.deadline) }}
              </td>
              <td class="py-4 px-4 text-right space-x-1.5 whitespace-nowrap">
                <!-- Draft Actions: Edit Data/Tier/Images, Submit Review, Delete -->
                <template v-if="c.status === 'draft'">
                  <RouterLink
                    :to="`/creator/campaigns/${c.slug}/edit`"
                    class="px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 inline-flex items-center gap-1 text-xs font-bold transition"
                    title="Edit Data Kampanye, Tiers, & Foto"
                  >
                    <i class="pi pi-file-edit text-xs"></i>
                    <span>Edit Draft</span>
                  </RouterLink>
                  <button
                    @click="handleSubmitReview(c.slug)"
                    class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-xs transition"
                    title="Submit untuk Ditinjau Admin"
                  >
                    Submit Review
                  </button>
                  <button
                    @click="handleDeleteCampaign(c.slug)"
                    class="p-2 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 inline-flex items-center text-xs font-semibold transition"
                    title="Hapus Draft"
                  >
                    <i class="pi pi-trash"></i>
                  </button>
                </template>

                <!-- Active Actions -->
                <template v-else-if="c.status === 'active'">
                  <button
                    @click="openPostUpdate(c.slug)"
                    class="px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-bold text-xs transition"
                  >
                    + Update
                  </button>
                  <RouterLink
                    :to="`/campaigns/${c.slug}`"
                    class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold text-xs transition"
                  >
                    Lihat Publik
                  </RouterLink>
                </template>

                <!-- Other statuses -->
                <template v-else>
                  <RouterLink
                    :to="`/campaigns/${c.slug}`"
                    class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold text-xs transition"
                  >
                    Detail
                  </RouterLink>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Post Update Modal -->
    <CreateUpdateDialog
      v-if="selectedUpdateSlug"
      v-model:visible="isUpdateModalOpen"
      :campaign-slug="selectedUpdateSlug"
      @created="loadCampaigns(selectedStatusFilter)"
    />
  </div>
</template>
