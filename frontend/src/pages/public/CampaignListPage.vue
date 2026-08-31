<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCampaignStore } from '@/stores/useCampaignStore'
import CampaignCard from '@/components/campaign/CampaignCard.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Pagination from '@/components/common/Pagination.vue'
import CustomSelect from '@/components/common/CustomSelect.vue'

const route = useRoute()
const router = useRouter()
const campaignStore = useCampaignStore()

const searchInput = ref(route.query.search || '')
const selectedCategory = ref(route.query.category || '')
const selectedSort = ref(route.query.sort || 'latest')
const currentPage = ref(Number(route.query.page) || 1)

let searchDebounceTimeout = null

const categories = [
  { label: 'Semua Kategori', value: '' },
  { label: 'Teknologi & Inovasi', value: 'teknologi' },
  { label: 'Sosial & Kemanusiaan', value: 'sosial-kemanusiaan' },
  { label: 'Lingkungan', value: 'lingkungan' },
  { label: 'Seni & Kerajinan', value: 'seni-kerajinan' },
  { label: 'Pendidikan', value: 'pendidikan' },
  { label: 'Kesehatan', value: 'kesehatan' },
]

const sortOptions = [
  { label: 'Terbaru', value: 'latest' },
  { label: 'Terpopuler (Paling Banyak Didanai)', value: 'popular' },
  { label: 'Kampanye Terdahulu', value: 'oldest' },
]

async function loadData(page = 1) {
  currentPage.value = page
  const params = {
    page: page,
    per_page: 9,
  }
  if (searchInput.value.trim()) params.search = searchInput.value.trim()
  if (selectedCategory.value) params.category = selectedCategory.value
  if (selectedSort.value) params.sort = selectedSort.value

  // Sync to query string
  router.replace({
    query: {
      ...(params.search ? { search: params.search } : {}),
      ...(params.category ? { category: params.category } : {}),
      ...(params.sort !== 'latest' ? { sort: params.sort } : {}),
      ...(page > 1 ? { page } : {}),
    },
  })

  await campaignStore.fetchCampaigns(params)
}

function handleSearchInput() {
  clearTimeout(searchDebounceTimeout)
  searchDebounceTimeout = setTimeout(() => {
    loadData(1)
  }, 400)
}

function handleCategoryChange(catValue) {
  selectedCategory.value = catValue
  loadData(1)
}

function handleSortChange() {
  loadData(1)
}

function resetFilters() {
  searchInput.value = ''
  selectedCategory.value = ''
  selectedSort.value = 'latest'
  loadData(1)
}

onMounted(() => {
  if (route.query.category) selectedCategory.value = route.query.category
  if (route.query.search) searchInput.value = route.query.search
  if (route.query.sort) selectedSort.value = route.query.sort
  loadData(currentPage.value)
})

watch(
  () => route.query.category,
  (newCat) => {
    if (newCat !== undefined && newCat !== selectedCategory.value) {
      selectedCategory.value = newCat || ''
      loadData(1)
    }
  }
)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    <!-- Header Page -->
    <div class="space-y-2">
      <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Eksplorasi Proyek</span>
      <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
        Temukan & Dukung Kampanye
      </h1>
      <p class="text-sm text-slate-500 max-w-2xl leading-relaxed">
        Jelajahi berbagai proyek inovatif dari kreator berbakat di seluruh Indonesia yang sedang menggalang dana pendanaan.
      </p>
    </div>

    <!-- Search & Filter Controls -->
    <div class="p-6 bg-white rounded-3xl border border-slate-200/80 shadow-sm space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
        <!-- Search Input -->
        <div class="md:col-span-8 relative">
          <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
          <input
            type="text"
            v-model="searchInput"
            @input="handleSearchInput"
            placeholder="Cari kampanye berdasarkan judul, deskripsi, atau nama inisiator..."
            class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
          />
        </div>

        <!-- Sort Select -->
        <div class="md:col-span-4">
          <CustomSelect
            v-model="selectedSort"
            :options="sortOptions"
            placeholder="Pilih urutan..."
            @change="handleSortChange"
          />
        </div>
      </div>

      <!-- Category Filter Pills -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-1 scrollbar-none">
        <button
          v-for="cat in categories"
          :key="cat.value"
          @click="handleCategoryChange(cat.value)"
          :class="[
            'px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex-shrink-0',
            selectedCategory === cat.value
              ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80',
          ]"
        >
          {{ cat.label }}
        </button>
      </div>
    </div>

    <!-- Campaigns Grid / Loading State -->
    <div>
      <SkeletonLoader v-if="campaignStore.isLoading" type="card" :count="6" />

      <div
        v-else-if="campaignStore.campaigns.length > 0"
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
      >
        <CampaignCard
          v-for="campaign in campaignStore.campaigns"
          :key="campaign.id"
          :campaign="campaign"
        />
      </div>

      <!-- Empty State -->
      <EmptyState
        v-else
        title="Tidak Ada Kampanye Ditemukan"
        description="Cobalah gunakan kata kunci lain atau ubah filter kategori pencarian Anda."
        action-text="Reset Semua Filter"
        @action="resetFilters"
      />
    </div>

    <!-- Pagination -->
    <Pagination
      v-if="!campaignStore.isLoading && campaignStore.campaigns.length > 0"
      :current-page="campaignStore.pagination.currentPage"
      :last-page="campaignStore.pagination.lastPage"
      :total="campaignStore.pagination.total"
      :per-page="campaignStore.pagination.perPage"
      @page-change="loadData"
    />
  </div>
</template>
