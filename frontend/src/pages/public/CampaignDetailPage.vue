<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useCampaignStore } from '@/stores/useCampaignStore'
import { useAuthStore } from '@/stores/useAuthStore'
import { useBacking } from '@/composables/useBacking'
import { backingService } from '@/services/backingService'
import { campaignUpdateService } from '@/services/campaignUpdateService'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate, getDaysRemaining } from '@/utils/formatDate'
import { getImageUrl, onImageError } from '@/utils/imageHelper'
import ProgressBar from '@/components/common/ProgressBar.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'
import RewardTierCard from '@/components/campaign/RewardTierCard.vue'
import BackingDialog from '@/components/campaign/BackingDialog.vue'
import CampaignUpdatesList from '@/components/campaign/CampaignUpdatesList.vue'
import CreateUpdateDialog from '@/components/campaign/CreateUpdateDialog.vue'
import EditUpdateDialog from '@/components/campaign/EditUpdateDialog.vue'
import { useConfirm } from '@/composables/useConfirm'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { confirm: showConfirmModal } = useConfirm()
const campaignStore = useCampaignStore()
const authStore = useAuthStore()
const { isBackingDialogOpen, isSubmitting, selectedTier, openBackingDialog, closeBackingDialog, submitBacking } = useBacking()

const activeTab = ref('about') // 'about' | 'tiers' | 'updates' | 'backers'
const selectedImageIndex = ref(0)
const backers = ref([])
const updates = ref([])
const isBackersLoading = ref(false)
const isUpdatesLoading = ref(false)
const isCreateUpdateModalOpen = ref(false)
const isEditUpdateModalOpen = ref(false)
const selectedEditUpdate = ref(null)
const isSubmittingReview = ref(false)

const campaign = computed(() => campaignStore.currentCampaign)

const isOwner = computed(() => {
  if (!authStore.user || !campaign.value) return false
  const currentUserId = authStore.user.id
  const creatorId = campaign.value.creator?.id || campaign.value.user_id
  return Boolean(currentUserId && creatorId && currentUserId === creatorId)
})

const canViewBackers = computed(() => {
  return isOwner.value || authStore.isAdmin
})

const daysInfo = computed(() => {
  return getDaysRemaining(campaign.value?.deadline)
})

const progress = computed(() => {
  return Number(campaign.value?.progress_percentage) || 0
})

const images = computed(() => {
  if (campaign.value?.images && campaign.value.images.length > 0) {
    return campaign.value.images.map((img) => ({
      ...img,
      url: getImageUrl(img.url),
    }))
  }
  return [{ url: getImageUrl(null) }]
})

const youtubeEmbedUrl = computed(() => {
  if (!campaign.value?.video_url) return null
  const url = campaign.value.video_url
  if (url.includes('youtube.com/watch?v=')) {
    const videoId = url.split('v=')[1]?.split('&')[0]
    return `https://www.youtube-nocookie.com/embed/${videoId}`
  }
  if (url.includes('youtu.be/')) {
    const videoId = url.split('youtu.be/')[1]?.split('?')[0]
    return `https://www.youtube-nocookie.com/embed/${videoId}`
  }
  return null
})

async function fetchBackers() {
  if (!campaign.value?.slug) return
  if (!canViewBackers.value) {
    backers.value = []
    return
  }
  isBackersLoading.value = true
  try {
    const res = await backingService.getByCampaign(campaign.value.slug, { per_page: 50 })
    backers.value = res.data?.data || []
  } catch {
    backers.value = []
  } finally {
    isBackersLoading.value = false
  }
}

async function fetchUpdates() {
  if (!campaign.value?.slug) return
  isUpdatesLoading.value = true
  try {
    const res = await campaignUpdateService.getAll(campaign.value.slug)
    updates.value = res.data?.data || []
  } catch {
    updates.value = []
  } finally {
    isUpdatesLoading.value = false
  }
}

function handleOpenBacking(tier = null) {
  if (authStore.isAdmin) {
    toast.info('Akun Administrator tidak dapat melakukan donasi/dukungan pada kampanye.')
    return
  }
  if (isOwner.value) {
    toast.info('Inisiator tidak dapat memberikan donasi/dukungan pada kampanye miliknya sendiri.')
    return
  }
  openBackingDialog(tier)
}

function handleSelectTier(tier) {
  handleOpenBacking(tier)
}

function handleBackingConfirm(payload) {
  submitBacking(campaign.value.slug, payload, async () => {
    // Refresh detail and backers
    await campaignStore.fetchCampaignBySlug(campaign.value.slug)
    await fetchBackers()
  })
}

async function handleSubmitReviewFromDetail() {
  const isConfirmed = await showConfirmModal({
    title: 'Kirim untuk Peninjauan',
    message: 'Ajukan proposal kampanye ini ke Administrator untuk diverifikasi dan disetujui? Status akan berubah menjadi Menunggu Review.',
    type: 'info',
    confirmText: 'Ya, Ajukan Review',
    cancelText: 'Batal',
  })

  if (!isConfirmed) {
    return
  }
  isSubmittingReview.value = true
  try {
    await campaignStore.submitForReview(campaign.value.slug)
    await campaignStore.fetchCampaignBySlug(campaign.value.slug)
  } finally {
    isSubmittingReview.value = false
  }
}

function onUpdateCreated() {
  fetchUpdates()
}

function handleOpenEditUpdate(update) {
  selectedEditUpdate.value = update
  isEditUpdateModalOpen.value = true
}

async function handleDeleteUpdate(update) {
  const isConfirmed = await showConfirmModal({
    title: 'Hapus Kabar Proyek',
    message: `Apakah Anda yakin ingin menghapus kabar proyek "${update.title}"?`,
    type: 'danger',
    confirmText: 'Ya, Hapus',
    cancelText: 'Batal',
  })

  if (!isConfirmed) {
    return
  }

  try {
    const res = await campaignUpdateService.delete(campaign.value.slug, update.id)
    toast.success(res.data?.message || 'Kabar proyek berhasil dihapus.')
    await fetchUpdates()
  } catch (error) {
    const msg = error.response?.data?.message || 'Gagal menghapus kabar proyek.'
    toast.error(msg)
  }
}

function onUpdateEdited() {
  fetchUpdates()
}

onMounted(async () => {
  const slug = route.params.slug
  const res = await campaignStore.fetchCampaignBySlug(slug)
  if (!res) {
    router.push('/404')
    return
  }
  fetchUpdates()
  fetchBackers()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Skeleton loader when fetching -->
    <SkeletonLoader v-if="campaignStore.isLoading" type="detail" />

    <div v-else-if="campaign" class="space-y-8">
      <!-- Creator Owner Notification Banner (Draft / Review Management) -->
      <div
        v-if="isOwner && campaign.status === 'draft'"
        class="p-5 rounded-3xl bg-amber-50 border border-amber-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4"
      >
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0 mt-0.5">
            <i class="pi pi-file-edit text-lg"></i>
          </div>
          <div>
            <h4 class="font-bold text-amber-900 text-sm">Mode Pratinjau Draft Inisiator</h4>
            <p class="text-xs text-amber-800 leading-relaxed mt-0.5">
              Kampanye ini masih dalam status <strong>Draft</strong> dan belum tampil ke publik. Anda dapat mengedit data pokok, menambah/mengedit reward tier, dan foto, atau langsung mengajukannya ke admin untuk direview.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <RouterLink
            :to="`/creator/campaigns/${campaign.slug}/edit`"
            class="px-4 py-2.5 rounded-xl bg-white hover:bg-amber-100/60 text-amber-900 border border-amber-300 font-bold text-xs shadow-sm transition flex items-center gap-1.5"
          >
            <i class="pi pi-pencil text-xs"></i>
            <span>Edit Data Draft</span>
          </RouterLink>
          <button
            type="button"
            @click="handleSubmitReviewFromDetail"
            :disabled="isSubmittingReview"
            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5"
          >
            <i v-if="isSubmittingReview" class="pi pi-spin pi-spinner text-xs"></i>
            <i v-else class="pi pi-send text-xs"></i>
            <span>{{ isSubmittingReview ? 'Mengajukan...' : 'Ajukan Review ke Admin' }}</span>
          </button>
        </div>
      </div>

      <div
        v-else-if="isOwner && campaign.status === 'review'"
        class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-900 text-xs flex items-center gap-3"
      >
        <i class="pi pi-clock text-blue-600 text-lg"></i>
        <span>Kampanye ini sedang dalam antrean peninjauan oleh Admin. Setelah disetujui, kampanye akan otomatis tayang dan siap menerima donasi backer.</span>
      </div>

      <!-- Breadcrumbs (Hidden for Admin) -->
      <div v-if="!authStore.isAdmin" class="flex items-center gap-2 text-xs text-slate-500">
        <RouterLink to="/campaigns" class="hover:text-blue-600 font-medium">Kampanye</RouterLink>
        <i class="pi pi-chevron-right text-[10px]"></i>
        <span class="font-bold text-slate-800">{{ campaign.category?.name || 'Umum' }}</span>
        <i class="pi pi-chevron-right text-[10px]"></i>
        <span class="truncate max-w-xs text-slate-400">{{ campaign.title }}</span>
      </div>

      <!-- Campaign Title Header -->
      <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
          <span class="px-3 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100">
            {{ campaign.category?.name || 'Inovasi' }}
          </span>
          <StatusBadge type="campaign" :value="campaign.status" size="sm" />
        </div>
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
          {{ campaign.title }}
        </h1>
      </div>

      <!-- Main Visual & Summary Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Left: Image Gallery & Video (Col 7) -->
        <div class="lg:col-span-7 space-y-4">
          <!-- Primary image (1:1 Persegi Kotak) -->
          <div class="relative aspect-square w-full rounded-3xl overflow-hidden bg-slate-900 border border-slate-200/80 shadow-md">
            <img
              :src="images[selectedImageIndex]?.url || images[0]?.url"
              :alt="campaign.title"
              @error="onImageError"
              class="w-full h-full object-cover"
            />
          </div>

          <!-- Thumbnails slider if multiple (1:1 Persegi Kotak) -->
          <div v-if="images.length > 1" class="flex items-center gap-3 overflow-x-auto pb-2">
            <button
              v-for="(img, idx) in images"
              :key="img.id || idx"
              @click="selectedImageIndex = idx"
              :class="[
                'w-20 h-20 aspect-square rounded-2xl overflow-hidden border-2 transition flex-shrink-0',
                selectedImageIndex === idx ? 'border-blue-600 ring-2 ring-blue-500/20' : 'border-transparent opacity-70 hover:opacity-100',
              ]"
            >
              <img :src="img.url" @error="onImageError" class="w-full h-full object-cover" />
            </button>
          </div>

          <!-- Video Embed if available -->
          <div v-if="youtubeEmbedUrl" class="mt-6 space-y-2">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
              <i class="pi pi-video text-rose-500"></i>
              Video Presentasi Proyek
            </h4>
            <div class="aspect-video w-full rounded-2xl overflow-hidden border border-slate-200 bg-slate-900 shadow-sm">
              <iframe
                :src="youtubeEmbedUrl"
                class="w-full h-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
              ></iframe>
            </div>
          </div>
        </div>

        <!-- Right: Funding Status Card (Col 5) -->
        <div class="lg:col-span-5 space-y-6">
          <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-soft space-y-6">
            <!-- Funding Progress -->
            <div class="space-y-3">
              <div class="flex items-baseline justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Terkumpul</span>
                <span class="text-xs font-extrabold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">
                  {{ progress.toFixed(1) }}%
                </span>
              </div>
              <div class="text-3xl sm:text-4xl font-black text-slate-900">
                {{ formatCurrency(campaign.collected_amount) }}
              </div>
              <div class="text-xs text-slate-500">
                Target dana: <strong class="text-slate-800">{{ formatCurrency(campaign.target_amount) }}</strong>
              </div>

              <!-- Animated Progress Bar -->
              <ProgressBar :percentage="progress" height="h-3" />
            </div>

            <!-- Stats Mini Grid -->
            <div class="grid grid-cols-2 gap-4 py-4 border-y border-slate-100">
              <div>
                <div class="text-2xl font-black text-slate-900">
                  {{ campaign.backings_count || backers.length || 0 }}
                </div>
                <div class="text-xs text-slate-500 font-medium mt-0.5">Orang Telah Mendukung</div>
              </div>
              <div>
                <div class="text-2xl font-black text-slate-900">
                  {{ daysInfo.days }}
                </div>
                <div class="text-xs text-slate-500 font-medium mt-0.5">{{ daysInfo.text }}</div>
              </div>
            </div>

            <!-- Creator info card -->
            <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white font-bold text-sm flex items-center justify-center shadow-sm">
                {{ campaign.creator?.name?.charAt(0)?.toUpperCase() || 'C' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-slate-900 truncate">{{ campaign.creator?.name }}</div>
                <div class="text-[11px] text-slate-500 flex items-center gap-1">
                  <i class="pi pi-check-circle text-emerald-500 text-[10px]"></i>
                  <span>Inisiator Terverifikasi</span>
                </div>
              </div>
            </div>

            <!-- CTA Backing Button (Only if Active, Not Admin, and Not Owner) -->
            <div class="space-y-3 pt-2">
              <!-- 1. Administrator Info -->
              <div
                v-if="authStore.isAdmin"
                class="w-full py-3.5 px-4 rounded-2xl bg-slate-100 text-slate-500 font-bold text-xs text-center border border-slate-200"
              >
                <i class="pi pi-shield mr-1.5 text-blue-600"></i>
                Akun Administrator tidak dapat mendukung kampanye.
              </div>

              <!-- 2. Campaign Owner / Creator Box -->
              <div
                v-else-if="isOwner"
                class="w-full p-4 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200/80 text-center space-y-2.5 shadow-xs"
              >
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-blue-600 text-white font-extrabold text-xs shadow-xs">
                  <i class="pi pi-user-check text-xs"></i>
                  <span>Kampanye Milik Anda</span>
                </div>
                <p class="text-xs text-slate-600 font-medium leading-relaxed">
                  Sebagai inisiator/pemilik proyek ini, Anda tidak dapat memberikan dukungan dana ke kampanye Anda sendiri.
                </p>
                <div class="pt-1 flex gap-2">
                  <RouterLink
                    v-if="campaign.status === 'draft'"
                    :to="`/creator/campaigns/${campaign.slug}/edit`"
                    class="flex-1 py-2.5 px-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition shadow-sm flex items-center justify-center gap-1.5"
                  >
                    <i class="pi pi-pencil text-[11px]"></i>
                    <span>Edit Draft</span>
                  </RouterLink>
                  <RouterLink
                    to="/creator/dashboard"
                    class="flex-1 py-2.5 px-3 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold transition shadow-sm flex items-center justify-center gap-1.5"
                  >
                    <i class="pi pi-chart-bar text-[11px]"></i>
                    <span>Dashboard</span>
                  </RouterLink>
                </div>
              </div>

              <!-- 3. Active Backing CTA for Public / Backers -->
              <button
                v-else-if="campaign.status === 'active'"
                @click="handleOpenBacking()"
                class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm sm:text-base shadow-glow-amber transition-all duration-300 transform hover:-translate-y-0.5 active:scale-[0.98] flex items-center justify-center gap-2.5 tracking-tight"
              >
                <i class="pi pi-bolt text-slate-950 text-base"></i>
                <span>Dukung Kampanye Sekarang</span>
                <i class="pi pi-arrow-right text-xs"></i>
              </button>

              <!-- 4. Draft Status -->
              <div
                v-else-if="campaign.status === 'draft'"
                class="w-full p-4 rounded-2xl bg-slate-100 text-slate-600 font-medium text-xs text-center border border-slate-200 space-y-2"
              >
                <div class="font-bold text-slate-800">Kampanye masih berstatus Draft</div>
                <p class="text-[11px] text-slate-500">
                  Ajukan review ke admin untuk membuka pendanaan publik.
                </p>
              </div>

              <!-- 5. Inactive / Completed / Failed Status -->
              <div
                v-else
                class="w-full py-3.5 px-4 rounded-2xl bg-slate-100 text-slate-500 font-bold text-xs text-center border border-slate-200"
              >
                Kampanye saat ini tidak menerima pendanaan baru (Status: {{ campaign.status }}).
              </div>

              <!-- Escrow Guarantee note -->
              <p class="text-[11px] text-slate-400 text-center flex items-center justify-center gap-1.5 pt-1">
                <i class="pi pi-lock text-emerald-500"></i>
                <span>Dilindungi Virtual Escrow dengan jaminan refund otomatis.</span>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="border-b border-slate-200 pt-4">
        <div class="flex items-center gap-8 overflow-x-auto">
          <button
            @click="activeTab = 'about'"
            :class="[
              'pb-4 text-sm font-bold border-b-2 transition whitespace-nowrap',
              activeTab === 'about'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-900',
            ]"
          >
            Tentang Kampanye
          </button>
          <button
            @click="activeTab = 'tiers'"
            :class="[
              'pb-4 text-sm font-bold border-b-2 transition whitespace-nowrap flex items-center gap-2',
              activeTab === 'tiers'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-900',
            ]"
          >
            <span>Pilihan Reward Tier</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-700">
              {{ campaign.tiers?.length || 0 }}
            </span>
          </button>
          <button
            @click="activeTab = 'updates'"
            :class="[
              'pb-4 text-sm font-bold border-b-2 transition whitespace-nowrap flex items-center gap-2',
              activeTab === 'updates'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-900',
            ]"
          >
            <span>Kabar Proyek (Blog Update)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600">
              {{ updates.length }}
            </span>
          </button>
          <button
            v-if="canViewBackers"
            @click="activeTab = 'backers'"
            :class="[
              'pb-4 text-sm font-bold border-b-2 transition whitespace-nowrap flex items-center gap-2',
              activeTab === 'backers'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-900',
            ]"
          >
            <span>Donatur (Backers)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600">
              {{ backers.length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Tab Content Area -->
      <div class="py-4">
        <!-- Tab 1: About -->
        <div v-if="activeTab === 'about'" class="max-w-4xl space-y-6">
          <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Rencana & Deskripsi Proyek</h3>
            <div
              v-if="campaign.description_html"
              class="prose prose-slate max-w-none text-sm text-slate-700 leading-relaxed"
              v-html="campaign.description_html"
            ></div>
            <p v-else class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">
              {{ campaign.description }}
            </p>
          </div>
        </div>

        <!-- Tab 2: Reward Tiers -->
        <div v-else-if="activeTab === 'tiers'" class="space-y-6">
          <div class="max-w-xl">
            <h3 class="text-lg font-bold text-slate-900">Pilih Paket Dukungan (Reward Tiers)</h3>
            <p class="text-xs text-slate-500 mt-1">
              Pilih salah satu reward eksklusif berikut untuk mendukung inisiator mewujudkan proyek ini.
            </p>
          </div>

          <div
            v-if="campaign.tiers && campaign.tiers.length > 0"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
          >
            <RewardTierCard
              v-for="tier in campaign.tiers"
              :key="tier.id"
              :tier="tier"
              :is-campaign-active="campaign.status === 'active' && !authStore.isAdmin && !isOwner"
              @select="handleSelectTier"
            />
          </div>
          <div v-else class="text-center py-12 text-slate-400">
            Tidak ada reward tier khusus yang terdaftar. Anda tetap dapat berdonasi secara bebas.
          </div>
        </div>

        <!-- Tab 3: Updates (Blog Proyek) -->
        <div v-else-if="activeTab === 'updates'" class="max-w-3xl space-y-6">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-bold text-slate-900">Kabar Proyek & Blog Milestone</h3>
              <p class="text-xs text-slate-500 mt-0.5">Catatan kemajuan dan milestone berkala yang diposting oleh inisiator untuk para donatur.</p>
            </div>
            <!-- Creator button to post update if active -->
            <button
              v-if="isOwner && campaign.status === 'active'"
              @click="isCreateUpdateModalOpen = true"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm"
            >
              <i class="pi pi-plus text-[10px]"></i>
              <span>Tulis Kabar Proyek (Blog)</span>
            </button>
          </div>

          <div v-if="campaign.status !== 'active' && isOwner" class="p-3.5 rounded-xl bg-slate-100 text-slate-600 text-xs border border-slate-200">
            <i class="pi pi-info-circle mr-1 text-slate-500"></i>
            Anda dapat mempublikasikan blog kabar proyek setelah kampanye disetujui admin dan berstatus <strong>Aktif</strong>.
          </div>

          <CampaignUpdatesList
            :updates="updates"
            :is-owner="isOwner"
            @edit="handleOpenEditUpdate"
            @delete="handleDeleteUpdate"
          />
        </div>

        <!-- Tab 4: Backers (Only visible to campaign owner or admin) -->
        <div v-else-if="activeTab === 'backers'" class="max-w-3xl space-y-4">
          <div v-if="!canViewBackers" class="p-10 text-center bg-white rounded-3xl border border-slate-200/80 shadow-sm text-slate-500 text-xs space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-2">
              <i class="pi pi-lock text-xl"></i>
            </div>
            <p class="font-bold text-slate-800 text-sm">Privasi Data Donatur</p>
            <p class="text-slate-400 max-w-sm mx-auto">
              Daftar rincian donatur kampanye ini hanya dapat diakses oleh inisiator pemilik proyek dan admin untuk melindungi privasi.
            </p>
          </div>

          <template v-else>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-bold text-slate-900">Daftar Sahabat Pendukung (Inisiator Only)</h3>
                <p class="text-xs text-slate-500 mt-0.5">Daftar backer yang telah berkontribusi mendanai proyek Anda.</p>
              </div>
              <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-xl">
                {{ backers.length }} Kontribusi
              </span>
            </div>

            <div v-if="backers.length === 0" class="text-center py-10 text-slate-400 text-sm bg-white rounded-3xl border border-slate-200/80 p-6">
              Belum ada donatur yang mendukung kampanye ini.
            </div>

            <div v-else class="bg-white rounded-3xl border border-slate-200/80 shadow-sm divide-y divide-slate-100 overflow-hidden">
              <div
                v-for="b in backers"
                :key="b.id"
                class="p-4 sm:p-5 flex items-center justify-between"
              >
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center">
                    {{ b.backer?.name?.charAt(0)?.toUpperCase() || 'B' }}
                  </div>
                  <div>
                    <div class="text-xs font-bold text-slate-900">{{ b.backer?.name || 'Backer Anonim' }}</div>
                    <div class="text-[11px] text-slate-400">{{ formatDate(b.created_at) }}</div>
                  </div>
                </div>

                <div class="text-right">
                  <div class="text-xs font-extrabold text-blue-600">
                    {{ formatCurrency(b.amount) }}
                  </div>
                  <div v-if="b.tier" class="text-[10px] text-slate-500 font-medium">
                    Tier: {{ b.tier.name }}
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Backing Dialog Modal -->
    <BackingDialog
      v-if="campaign"
      v-model:visible="isBackingDialogOpen"
      :campaign="campaign"
      :initial-tier="selectedTier"
      :is-submitting="isSubmitting"
      @confirm="handleBackingConfirm"
    />

    <!-- Creator Post Update Modal -->
    <CreateUpdateDialog
      v-if="campaign"
      v-model:visible="isCreateUpdateModalOpen"
      :campaign-slug="campaign.slug"
      @created="onUpdateCreated"
    />

    <!-- Creator Edit Update Modal -->
    <EditUpdateDialog
      v-if="campaign && selectedEditUpdate"
      v-model:visible="isEditUpdateModalOpen"
      :campaign-slug="campaign.slug"
      :update-data="selectedEditUpdate"
      @updated="onUpdateEdited"
    />
  </div>
</template>
