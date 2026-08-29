<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAdminStore } from '@/stores/useAdminStore'
import { campaignService } from '@/services/campaignService'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import CustomSelect from '@/components/common/CustomSelect.vue'
import Pagination from '@/components/common/Pagination.vue'
import RejectDialog from '@/components/admin/RejectDialog.vue'
import { getImageUrl, onImageError } from '@/utils/imageHelper'
import { useConfirm } from '@/composables/useConfirm'

const adminStore = useAdminStore()
const { confirm: showConfirmModal } = useConfirm()

const activeTab = ref('review') // 'review' | 'all'
const allCampaigns = ref([])
const isAllLoading = ref(false)

// Filter states for List Kampanye
const searchInput = ref('')
const selectedStatus = ref('')
const selectedCategory = ref('')
const selectedSort = ref('latest')
const currentPage = ref(1)
const paginationData = ref({
  currentPage: 1,
  lastPage: 1,
  total: 0,
  perPage: 15,
})

let searchDebounceTimeout = null
const selectedRejectCampaign = ref(null)
const isRejectModalOpen = ref(false)

const statusFilterOptions = [
  { label: 'Semua Status', value: '' },
  { label: 'Menunggu Review', value: 'review' },
  { label: 'Aktif', value: 'active' },
  { label: 'Draft', value: 'draft' },
  { label: 'Berhasil', value: 'success' },
  { label: 'Gagal', value: 'failed' },
  { label: 'Ditolak', value: 'rejected' },
]

const categoryOptions = [
  { label: 'Semua Kategori', value: '' },
  { label: 'Teknologi & Inovasi', value: 'teknologi' },
  { label: 'Sosial & Kemanusiaan', value: 'sosial-kemanusiaan' },
  { label: 'Lingkungan', value: 'lingkungan' },
  { label: 'Seni & Kerajinan', value: 'seni-kerajinan' },
  { label: 'Pendidikan', value: 'pendidikan' },
  { label: 'Kesehatan', value: 'kesehatan' },
]

const sortOptions = [
  { label: 'Terbaru Ditambahkan', value: 'latest' },
  { label: 'Terpopuler (Banyak Didanai)', value: 'popular' },
  { label: 'Kampanye Terdahulu', value: 'oldest' },
]

async function loadReviewQueue() {
  await adminStore.fetchReviewCampaigns()
}

async function loadAllCampaigns(page = 1) {
  currentPage.value = page
  isAllLoading.value = true
  try {
    const params = {
      page: page,
      per_page: 15,
    }
    if (searchInput.value.trim()) params.search = searchInput.value.trim()
    if (selectedStatus.value) params.status = selectedStatus.value
    if (selectedCategory.value) params.category = selectedCategory.value
    if (selectedSort.value) params.sort = selectedSort.value

    const res = await campaignService.getAll(params)
    allCampaigns.value = res.data?.data || []
    if (res.data?.meta) {
      paginationData.value = {
        currentPage: res.data.meta.current_page || page,
        lastPage: res.data.meta.last_page || 1,
        total: res.data.meta.total || 0,
        perPage: res.data.meta.per_page || 15,
      }
    } else {
      paginationData.value = {
        currentPage: page,
        lastPage: 1,
        total: allCampaigns.value.length,
        perPage: 15,
      }
    }
  } catch {
    allCampaigns.value = []
  } finally {
    isAllLoading.value = false
  }
}

function handleSearchInput() {
  clearTimeout(searchDebounceTimeout)
  searchDebounceTimeout = setTimeout(() => {
    loadAllCampaigns(1)
  }, 400)
}

function handleFilterChange() {
  loadAllCampaigns(1)
}

function resetFilters() {
  searchInput.value = ''
  selectedStatus.value = ''
  selectedCategory.value = ''
  selectedSort.value = 'latest'
  loadAllCampaigns(1)
}

async function handleApprove(slug) {
  const isConfirmed = await showConfirmModal({
    title: 'Setujui Kampanye',
    message: 'Apakah Anda yakin ingin menyetujui proposal kampanye ini dan mengubah statusnya menjadi Aktif (Live)?',
    type: 'success',
    confirmText: 'Ya, Setujui',
    cancelText: 'Batal',
  })

  if (isConfirmed) {
    await adminStore.approveCampaign(slug)
    await loadReviewQueue()
    if (activeTab.value === 'all') {
      await loadAllCampaigns(currentPage.value)
    }
  }
}

function openRejectDialog(campaign) {
  selectedRejectCampaign.value = campaign
  isRejectModalOpen.value = true
}

async function handleRejectConfirm(note) {
  if (selectedRejectCampaign.value) {
    await adminStore.rejectCampaign(selectedRejectCampaign.value.slug, note)
    await loadReviewQueue()
    if (activeTab.value === 'all') {
      await loadAllCampaigns(currentPage.value)
    }
  }
}

async function handleForceFail(slug) {
  const isConfirmed = await showConfirmModal({
    title: 'Gagalkan Paksa Kampanye (Refund)',
    message: 'PERINGATAN: Menggagalkan kampanye secara paksa akan memicu pengembalian dana 100% ke seluruh saldo dompet donatur/backer. Lanjutkan?',
    type: 'danger',
    confirmText: 'Ya, Gagalkan & Refund',
    cancelText: 'Batal',
  })

  if (isConfirmed) {
    await adminStore.forceFailCampaign(slug)
    await loadAllCampaigns(currentPage.value)
  }
}

function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'all') {
    loadAllCampaigns(1)
  } else {
    loadReviewQueue()
  }
}

onMounted(() => {
  loadReviewQueue()
})
</script>

<template>
  <div class="space-y-8">
    <!-- Header Page -->
    <div>
      <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Pusat Manajemen</span>
      <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
        Manajemen Kampanye
      </h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">
        Kelola persetujuan proposal kampanye baru serta seluruh siklus hidup kampanye platform.
      </p>
    </div>

    <!-- Sub Menu Navigation Tabs -->
    <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
      <!-- Sub Menu 1: Approval Queue -->
      <button
        type="button"
        @click="switchTab('review')"
        :class="[
          'px-5 py-3 rounded-2xl text-xs font-bold transition-all duration-200 flex items-center gap-2.5',
          activeTab === 'review'
            ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
            : 'bg-white border border-slate-200/90 text-slate-600 hover:text-slate-900 hover:bg-slate-50',
        ]"
      >
        <i class="pi pi-check-square text-sm"></i>
        <span>Approval Queue</span>
        <span
          :class="[
            'px-2 py-0.5 rounded-full text-[10px] font-extrabold',
            activeTab === 'review' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800',
          ]"
        >
          {{ adminStore.reviewCampaigns.length }}
        </span>
      </button>

      <!-- Sub Menu 2: List Kampanye -->
      <button
        type="button"
        @click="switchTab('all')"
        :class="[
          'px-5 py-3 rounded-2xl text-xs font-bold transition-all duration-200 flex items-center gap-2.5',
          activeTab === 'all'
            ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
            : 'bg-white border border-slate-200/90 text-slate-600 hover:text-slate-900 hover:bg-slate-50',
        ]"
      >
        <i class="pi pi-th-large text-sm"></i>
        <span>List Kampanye</span>
      </button>
    </div>

    <!-- ================= SUB MENU 1: APPROVAL QUEUE ================= -->
    <div v-if="activeTab === 'review'" class="space-y-6 animate-in fade-in duration-150">
      <SkeletonLoader v-if="adminStore.isLoading" type="table" :count="4" />

      <div
        v-else-if="adminStore.reviewCampaigns.length === 0"
        class="p-12 text-center bg-white rounded-3xl border border-slate-200/80 shadow-sm text-slate-500 text-xs space-y-2"
      >
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2 shadow-xs">
          <i class="pi pi-check-circle text-2xl"></i>
        </div>
        <p class="font-bold text-slate-800 text-sm">Antrean Bersih</p>
        <p class="text-slate-400">Tidak ada kampanye yang sedang menunggu review saat ini.</p>
      </div>

      <div v-else class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider bg-slate-50/80">
                <th class="py-3.5 px-4">Kampanye & Inisiator</th>
                <th class="py-3.5 px-4">Kategori</th>
                <th class="py-3.5 px-4">Target Dana</th>
                <th class="py-3.5 px-4">Deadline</th>
                <th class="py-3.5 px-4 text-right">Aksi Moderasi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="c in adminStore.reviewCampaigns"
                :key="c.id"
                class="hover:bg-slate-50 transition"
              >
                <td class="py-4 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-slate-100 overflow-hidden flex-shrink-0 border border-slate-200">
                      <img
                        :src="getImageUrl(c.images?.[0]?.url)"
                        @error="onImageError"
                        class="w-full h-full object-cover"
                      />
                    </div>
                    <div>
                      <RouterLink
                        :to="`/admin/campaigns/${c.slug}`"
                        class="font-bold text-slate-900 hover:text-blue-600 transition line-clamp-1"
                      >
                        {{ c.title }}
                      </RouterLink>
                      <div class="text-[11px] text-slate-500 mt-0.5">
                        Oleh: <span class="text-slate-700 font-medium">{{ c.creator?.name }}</span> ({{ c.creator?.email }})
                      </div>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4 text-slate-700 font-medium">
                  {{ c.category?.name || 'Umum' }}
                </td>
                <td class="py-4 px-4 font-bold text-blue-600">
                  {{ formatCurrency(c.target_amount) }}
                </td>
                <td class="py-4 px-4 text-slate-500">
                  {{ formatDate(c.deadline) }}
                </td>
                <td class="py-4 px-4 text-right space-x-2 whitespace-nowrap">
                  <RouterLink
                    :to="`/admin/campaigns/${c.slug}`"
                    class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs transition inline-block"
                  >
                    Preview
                  </RouterLink>
                  <button
                    @click="handleApprove(c.slug)"
                    :disabled="adminStore.isActionLoading"
                    class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs"
                  >
                    Approve
                  </button>
                  <button
                    @click="openRejectDialog(c)"
                    :disabled="adminStore.isActionLoading"
                    class="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition shadow-xs"
                  >
                    Reject
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ================= SUB MENU 2: LIST KAMPANYE ================= -->
    <div v-else class="space-y-6 animate-in fade-in duration-150">
      <!-- Search & Multi-Filter Control Box -->
      <div class="p-6 bg-white rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 items-center">
          <!-- Text Search (Judul / Inisiator) -->
          <div class="sm:col-span-12 lg:col-span-4 relative">
            <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input
              type="text"
              v-model="searchInput"
              @input="handleSearchInput"
              placeholder="Cari judul atau inisiator..."
              class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
            />
          </div>

          <!-- Status Filter Select -->
          <div class="sm:col-span-4 lg:col-span-3">
            <CustomSelect
              v-model="selectedStatus"
              :options="statusFilterOptions"
              placeholder="Semua Status"
              @change="handleFilterChange"
            />
          </div>

          <!-- Category Filter Select -->
          <div class="sm:col-span-4 lg:col-span-3">
            <CustomSelect
              v-model="selectedCategory"
              :options="categoryOptions"
              placeholder="Semua Kategori"
              @change="handleFilterChange"
            />
          </div>

          <!-- Sort Select -->
          <div class="sm:col-span-4 lg:col-span-2">
            <CustomSelect
              v-model="selectedSort"
              :options="sortOptions"
              placeholder="Urutan"
              @change="handleFilterChange"
            />
          </div>
        </div>

        <!-- Quick Status Pills Filter -->
        <div class="flex items-center gap-1.5 overflow-x-auto pt-2 border-t border-slate-100 scrollbar-none">
          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1.5 flex-shrink-0">
            Filter Status:
          </span>
          <button
            v-for="opt in statusFilterOptions"
            :key="opt.value"
            type="button"
            @click="
              () => {
                selectedStatus = opt.value
                handleFilterChange()
              }
            "
            :class="[
              'px-3 py-1 rounded-xl text-[11px] font-bold whitespace-nowrap transition-all flex-shrink-0',
              selectedStatus === opt.value
                ? 'bg-blue-600 text-white shadow-xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
            ]"
          >
            {{ opt.label }}
          </button>

          <!-- Reset Filter Button -->
          <button
            v-if="searchInput || selectedStatus || selectedCategory || selectedSort !== 'latest'"
            type="button"
            @click="resetFilters"
            class="px-3 py-1 rounded-xl text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition-colors ml-auto flex items-center gap-1 flex-shrink-0"
          >
            <i class="pi pi-refresh text-[10px]"></i>
            <span>Reset Filter</span>
          </button>
        </div>
      </div>

      <SkeletonLoader v-if="isAllLoading" type="table" :count="5" />

      <div
        v-else-if="allCampaigns.length === 0"
        class="p-12 text-center bg-white rounded-3xl border border-slate-200/80 shadow-sm text-slate-500 text-xs space-y-2"
      >
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2 shadow-xs">
          <i class="pi pi-folder-open text-2xl"></i>
        </div>
        <p class="font-bold text-slate-800 text-sm">Tidak Ada Kampanye</p>
        <p class="text-slate-400">Tidak ada data kampanye yang cocok dengan parameter filter pencarian.</p>
        <button
          type="button"
          @click="resetFilters"
          class="mt-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition"
        >
          Reset Filter Pencarian
        </button>
      </div>

      <!-- All Campaigns Data Table -->
      <div v-else class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden space-y-0">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider bg-slate-50/80">
                <th class="py-3.5 px-4">Kampanye</th>
                <th class="py-3.5 px-4">Inisiator</th>
                <th class="py-3.5 px-4">Kategori</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4">Terkumpul / Target</th>
                <th class="py-3.5 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="c in allCampaigns"
                :key="c.id"
                class="hover:bg-slate-50 transition"
              >
                <!-- Campaign Cover & Title -->
                <td class="py-4 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-slate-100 overflow-hidden flex-shrink-0 border border-slate-200">
                      <img
                        :src="getImageUrl(c.images?.[0]?.url)"
                        @error="onImageError"
                        class="w-full h-full object-cover"
                      />
                    </div>
                    <div>
                      <RouterLink
                        :to="`/admin/campaigns/${c.slug}`"
                        class="font-bold text-slate-900 hover:text-blue-600 transition line-clamp-1"
                      >
                        {{ c.title }}
                      </RouterLink>
                      <div class="text-[11px] text-slate-400 mt-0.5">
                        Deadline: {{ formatDate(c.deadline) }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Creator Name & Email -->
                <td class="py-4 px-4 text-slate-700">
                  <div class="font-bold text-slate-900">{{ c.creator?.name }}</div>
                  <div class="text-[11px] text-slate-400">{{ c.creator?.email }}</div>
                </td>

                <!-- Category -->
                <td class="py-4 px-4 text-slate-700 font-medium">
                  {{ c.category?.name || 'Umum' }}
                </td>

                <!-- Status Badge -->
                <td class="py-4 px-4">
                  <StatusBadge type="campaign" :value="c.status" size="sm" />
                </td>

                <!-- Collected / Target -->
                <td class="py-4 px-4">
                  <div class="font-bold text-slate-900">
                    {{ formatCurrency(c.collected_amount) }}
                  </div>
                  <div class="text-[11px] text-slate-400">
                    Target: {{ formatCurrency(c.target_amount) }}
                  </div>
                </td>

                <!-- Actions -->
                <td class="py-4 px-4 text-right space-x-2 whitespace-nowrap">
                  <template v-if="c.status === 'review'">
                    <button
                      @click="handleApprove(c.slug)"
                      :disabled="adminStore.isActionLoading"
                      class="px-2.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs"
                    >
                      Approve
                    </button>
                    <button
                      @click="openRejectDialog(c)"
                      :disabled="adminStore.isActionLoading"
                      class="px-2.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition shadow-xs"
                    >
                      Reject
                    </button>
                  </template>

                  <button
                    v-else-if="c.status === 'active'"
                    @click="handleForceFail(c.slug)"
                    class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-bold text-xs transition border border-rose-200"
                  >
                    Force-Fail (Refund)
                  </button>

                  <RouterLink
                    :to="`/admin/campaigns/${c.slug}`"
                    class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs transition inline-block"
                  >
                    Detail
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div class="p-4 border-t border-slate-100">
          <Pagination
            v-if="paginationData.lastPage > 1"
            :current-page="paginationData.currentPage"
            :last-page="paginationData.lastPage"
            :total="paginationData.total"
            :per-page="paginationData.perPage"
            @page-change="loadAllCampaigns"
          />
        </div>
      </div>
    </div>

    <!-- Reject Confirmation Modal -->
    <RejectDialog
      v-if="selectedRejectCampaign"
      v-model:visible="isRejectModalOpen"
      :campaign-title="selectedRejectCampaign.title"
      :is-submitting="adminStore.isActionLoading"
      @confirm="handleRejectConfirm"
    />
  </div>
</template>
