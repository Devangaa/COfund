<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useCampaignStore } from '@/stores/useCampaignStore'
import { tierService } from '@/services/tierService'
import { campaignImageService } from '@/services/campaignImageService'
import { formatCurrency } from '@/utils/formatCurrency'
import { getImageUrl, onImageError } from '@/utils/imageHelper'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import CustomDatePicker from '@/components/common/CustomDatePicker.vue'
import ImageCropperModal from '@/components/common/ImageCropperModal.vue'
import CustomSelect from '@/components/common/CustomSelect.vue'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { confirm: showConfirmModal } = useConfirm()
const campaignStore = useCampaignStore()

const isLoading = ref(true)
const isSaving = ref(false)
const isSubmittingReview = ref(false)
const activeTab = ref('info') // 'info' | 'tiers' | 'images'

// Core Campaign Form
const form = ref({
  category_id: 1,
  title: '',
  description: '',
  target_amount: 1000000,
  deadline: '',
  video_url: '',
})

// Tiers State
// Existing tiers will have an `id`. Newly added tiers will have `isNew: true`.
// Tiers marked for deletion will have `isMarkedForDeletion: true`.
const tiers = ref([])

// Images State
// Existing images from backend
const existingImages = ref([]) // { id, url, isMarkedForDeletion: false }
// Newly selected local image files with original raw data preserved
// Each item: { rawFile: File, rawSrc: string, croppedFile: File, previewUrl: string, name: string }
const newUploadedImages = ref([])

// 1:1 Cropper State
const isCropperOpen = ref(false)
const cropperImageSrc = ref('')
const cropperFileName = ref('')
const cropQueue = ref([])
const currentProcessingRawItem = ref(null)
const recropTargetIndex = ref(null)

const categories = [
  { id: 1, name: 'Teknologi' },
  { id: 2, name: 'Seni & Kerajinan' },
  { id: 3, name: 'Lingkungan' },
  { id: 4, name: 'Sosial & Kemanusiaan' },
  { id: 5, name: 'Pendidikan' },
  { id: 6, name: 'Kesehatan' },
]

const minDeadline = computed(() => {
  return dayjs().add(8, 'day').format('YYYY-MM-DD')
})

const activeTiersCount = computed(() => {
  return tiers.value.filter((t) => !t.isMarkedForDeletion).length
})

const activeImagesCount = computed(() => {
  const existingActive = existingImages.value.filter((img) => !img.isMarkedForDeletion).length
  return existingActive + newUploadedImages.value.length
})

async function loadCampaign() {
  isLoading.value = true
  const slug = route.params.slug
  const campaign = await campaignStore.fetchCampaignBySlug(slug)

  if (!campaign) {
    toast.error('Kampanye tidak ditemukan.')
    router.push('/creator/dashboard')
    return
  }

  if (campaign.status !== 'draft') {
    toast.warning('Hanya kampanye berstatus Draft yang dapat diedit datanya.')
    router.push('/creator/dashboard')
    return
  }

  // Populate core form
  form.value = {
    category_id: campaign.category?.id || 1,
    title: campaign.title,
    description: campaign.description,
    target_amount: campaign.target_amount,
    deadline: campaign.deadline,
    video_url: campaign.video_url || '',
  }

  // Populate tiers
  tiers.value = (campaign.tiers || []).map((t) => ({
    id: t.id,
    name: t.name,
    min_amount: t.min_amount,
    quota: t.quota,
    reward_description: t.reward_description || '',
    isNew: false,
    isMarkedForDeletion: false,
  }))

  // If no tier found, initialize one without auto-fill values
  if (tiers.value.length === 0) {
    tiers.value.push({
      name: '',
      min_amount: null,
      quota: null,
      reward_description: '',
      isNew: true,
      isMarkedForDeletion: false,
    })
  }

  // Populate existing images
  existingImages.value = (campaign.images || []).map((img) => ({
    id: img.id,
    url: getImageUrl(img.url),
    isMarkedForDeletion: false,
  }))

  isLoading.value = false
}

// --- Tier Actions ---
function addNewTier() {
  tiers.value.push({
    name: '',
    min_amount: null,
    quota: null,
    reward_description: '',
    isNew: true,
    isMarkedForDeletion: false,
  })
}

function markTierForDeletion(index) {
  const tier = tiers.value[index]
  if (activeTiersCount.value <= 1 && !tier.isMarkedForDeletion) {
    toast.warning('Kampanye harus memiliki minimal 1 tier yang aktif.')
    return
  }
  if (tier.isNew) {
    tiers.value.splice(index, 1)
  } else {
    tier.isMarkedForDeletion = true
  }
}

function unmarkTierForDeletion(index) {
  tiers.value[index].isMarkedForDeletion = false
}

function readFileAsDataURL(file) {
  return new Promise((resolve) => {
    const reader = new FileReader()
    reader.onload = (e) => resolve(e.target.result)
    reader.readAsDataURL(file)
  })
}

// --- Image Actions ---
async function handleImageSelect(event) {
  const files = Array.from(event.target.files || [])
  if (activeImagesCount.value + files.length > 5) {
    toast.error('Maksimal 5 gambar per kampanye.')
    return
  }

  const validItems = []
  for (const file of files) {
    if (!file.type.startsWith('image/')) {
      toast.error(`File ${file.name} bukan format gambar yang valid.`)
      continue
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error(`Ukuran file ${file.name} melebihi 5MB.`)
      continue
    }
    const rawSrc = await readFileAsDataURL(file)
    validItems.push({
      rawFile: file,
      rawSrc: rawSrc,
      name: file.name,
    })
  }

  if (validItems.length === 0) return

  recropTargetIndex.value = null
  cropQueue.value = validItems
  processNextCropInQueue()

  event.target.value = ''
}

function processNextCropInQueue() {
  if (cropQueue.value.length === 0) return
  const item = cropQueue.value.shift()
  currentProcessingRawItem.value = item
  cropperImageSrc.value = item.rawSrc
  cropperFileName.value = item.name
  isCropperOpen.value = true
}

function handleCropFinished({ file, previewUrl }) {
  if (recropTargetIndex.value !== null) {
    const target = newUploadedImages.value[recropTargetIndex.value]
    if (target) {
      target.croppedFile = file
      target.previewUrl = previewUrl
    }
    recropTargetIndex.value = null
  } else if (currentProcessingRawItem.value) {
    newUploadedImages.value.push({
      rawFile: currentProcessingRawItem.value.rawFile,
      rawSrc: currentProcessingRawItem.value.rawSrc,
      name: currentProcessingRawItem.value.name,
      croppedFile: file,
      previewUrl: previewUrl,
    })
    currentProcessingRawItem.value = null
  }

  if (cropQueue.value.length > 0) {
    setTimeout(() => {
      processNextCropInQueue()
    }, 150)
  }
}

function openRecropNewImage(index) {
  const item = newUploadedImages.value[index]
  if (!item) return
  recropTargetIndex.value = index
  // Open cropper with the ORIGINAL uncropped raw image!
  cropperImageSrc.value = item.rawSrc
  cropperFileName.value = item.name || `new-image-${index + 1}.jpg`
  isCropperOpen.value = true
}

function removeNewImage(index) {
  newUploadedImages.value.splice(index, 1)
}

function markExistingImageForDeletion(index) {
  const img = existingImages.value[index]
  if (activeImagesCount.value <= 1 && !img.isMarkedForDeletion) {
    toast.warning('Kampanye harus memiliki minimal 1 gambar yang aktif.')
    return
  }
  img.isMarkedForDeletion = true
}

function unmarkExistingImageForDeletion(index) {
  existingImages.value[index].isMarkedForDeletion = false
}

// --- Master Save Function ---
async function saveChangesInternal() {
  const slug = route.params.slug

  // Validations
  if (!form.value.title.trim()) {
    toast.warning('Judul kampanye wajib diisi.')
    activeTab.value = 'info'
    return false
  }
  if (!form.value.description.trim()) {
    toast.warning('Deskripsi kampanye wajib diisi.')
    activeTab.value = 'info'
    return false
  }
  if (activeTiersCount.value < 1) {
    toast.warning('Kampanye harus memiliki minimal 1 tier reward aktif.')
    activeTab.value = 'tiers'
    return false
  }
  if (activeImagesCount.value < 1) {
    toast.warning('Kampanye harus memiliki minimal 1 gambar aktif.')
    activeTab.value = 'images'
    return false
  }

  // 1. Update Core Campaign Data
  await campaignStore.updateCampaign(slug, {
    category_id: form.value.category_id,
    title: form.value.title,
    description: form.value.description,
    target_amount: Number(form.value.target_amount),
    deadline: form.value.deadline,
    video_url: form.value.video_url || '',
  })

  // 2. Process New Image Uploads FIRST (to satisfy backend min 1 image constraint)
  if (newUploadedImages.value.length > 0) {
    for (const item of newUploadedImages.value) {
      await campaignImageService.store(slug, item.croppedFile)
    }
  }

  // 3. Process New Tiers & Updates FIRST (to satisfy backend min 1 tier constraint)
  for (const tier of tiers.value) {
    if (tier.isMarkedForDeletion) continue

    if (tier.isNew) {
      // Create new tier
      await tierService.store(slug, {
        name: tier.name,
        min_amount: Number(tier.min_amount),
        quota: Number(tier.quota) || 0,
        reward_description: tier.reward_description || '',
      })
    } else {
      // Update existing tier
      await tierService.update(slug, tier.id, {
        name: tier.name,
        min_amount: Number(tier.min_amount),
        quota: Number(tier.quota) || 0,
        reward_description: tier.reward_description || '',
      })
    }
  }

  // 4. Process Image Deletions AFTER new images are uploaded
  const imagesToDelete = existingImages.value
    .filter((img) => img.isMarkedForDeletion)
    .map((img) => img.id)

  if (imagesToDelete.length > 0) {
    await campaignImageService.destroyMany(slug, imagesToDelete)
  }

  // 5. Process Tier Deletions AFTER new tiers are created
  const existingTiersToDelete = tiers.value
    .filter((t) => !t.isNew && t.isMarkedForDeletion)
    .map((t) => t.id)

  if (existingTiersToDelete.length > 0) {
    await tierService.destroyMany(slug, existingTiersToDelete)
  }

  return true
}

async function handleSaveOnly() {
  isSaving.value = true
  try {
    const success = await saveChangesInternal()
    if (success) {
      toast.success('Draft kampanye berhasil diperbarui!')
      router.push('/creator/dashboard')
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Terjadi kesalahan saat menyimpan perubahan.'
    toast.error(msg)
  } finally {
    isSaving.value = false
  }
}

async function handleSaveAndSubmitReview() {
  const isConfirmed = await showConfirmModal({
    title: 'Simpan & Ajukan Peninjauan',
    message: 'Simpan seluruh perubahan dan ajukan kampanye ini ke Administrator untuk diverifikasi? Status akan berubah menjadi Menunggu Review.',
    type: 'info',
    confirmText: 'Ya, Simpan & Ajukan',
    cancelText: 'Batal',
  })

  if (!isConfirmed) {
    return
  }

  isSubmittingReview.value = true
  try {
    const success = await saveChangesInternal()
    if (success) {
      const slug = route.params.slug
      await campaignStore.submitForReview(slug)
      router.push('/creator/dashboard')
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Gagal mengajukan review kampanye.'
    toast.error(msg)
  } finally {
    isSubmittingReview.value = false
  }
}

onMounted(() => {
  loadCampaign()
})
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div>
      <RouterLink to="/creator/dashboard" class="text-xs font-semibold text-blue-600 hover:underline inline-flex items-center gap-1 mb-2">
        <i class="pi pi-arrow-left text-[10px]"></i>
        <span>Kembali ke Dashboard</span>
      </RouterLink>
      <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
        Edit Data Kampanye
      </h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">
        Perbarui informasi kampanye, paket reward tier, dan foto visual proyek Anda.
      </p>
    </div>

    <!-- Alert Box: Explanation of Draft vs Review vs Updates -->
    <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-900 text-xs flex items-start gap-3">
      <i class="pi pi-info-circle text-blue-600 text-base mt-0.5 flex-shrink-0"></i>
      <div class="space-y-1">
        <p class="font-bold">Alur Pengajuan & Pengeditan:</p>
        <p class="text-blue-800 leading-relaxed">
          1. <strong>Edit Data:</strong> Ubah informasi dasar, tambah/edit/hapus paket reward tier, dan foto proyek selama status masih <strong>Draft</strong>.<br />
          2. <strong>Ajukan Review:</strong> Klik tombol <em>"Simpan & Ajukan Review"</em> agar admin dapat menyetujui kampanye Anda.<br />
          3. <strong>Kabar Terbaru (Blog Update):</strong> Setelah kampanye disetujui dan berstatus <strong>Aktif</strong>, Anda dapat menulis kabar terbaru (blog milestone) untuk para backer.
        </p>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-3 p-1.5 bg-slate-200/70 rounded-2xl">
      <button
        type="button"
        @click="activeTab = 'info'"
        :class="[
          'flex-1 py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2',
          activeTab === 'info'
            ? 'bg-white text-blue-600 shadow-sm'
            : 'text-slate-600 hover:text-slate-900',
        ]"
      >
        <i class="pi pi-info-circle"></i>
        <span>1. Data Kampanye</span>
      </button>

      <button
        type="button"
        @click="activeTab = 'tiers'"
        :class="[
          'flex-1 py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2',
          activeTab === 'tiers'
            ? 'bg-white text-blue-600 shadow-sm'
            : 'text-slate-600 hover:text-slate-900',
        ]"
      >
        <i class="pi pi-list"></i>
        <span>2. Reward Tiers</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-700">
          {{ activeTiersCount }}
        </span>
      </button>

      <button
        type="button"
        @click="activeTab = 'images'"
        :class="[
          'flex-1 py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2',
          activeTab === 'images'
            ? 'bg-white text-blue-600 shadow-sm'
            : 'text-slate-600 hover:text-slate-900',
        ]"
      >
        <i class="pi pi-images"></i>
        <span>3. Foto Kampanye</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-700">
          {{ activeImagesCount }}
        </span>
      </button>
    </div>

    <div v-if="isLoading" class="p-12 bg-white rounded-3xl border border-slate-200 skeleton-shimmer h-96"></div>

    <!-- TAB 1: Core Campaign Data Form -->
    <div v-else-if="activeTab === 'info'" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
      <div class="border-b border-slate-100 pb-4">
        <h3 class="text-base font-bold text-slate-900">Informasi Pokok Proyek</h3>
        <p class="text-xs text-slate-500 mt-0.5">Ubah judul, kategori, target dana, atau deadline kampanye.</p>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Judul Kampanye <span class="text-rose-500">*</span>
          </label>
          <input
            type="text"
            v-model="form.title"
            maxlength="100"
            placeholder="Masukkan judul kampanye"
            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Kategori
            </label>
            <CustomSelect
              v-model="form.category_id"
              :options="categories"
              placeholder="Pilih Kategori Kampanye"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Target Pendanaan (Rp) <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 font-bold text-xs">
                Rp
              </span>
              <input
                type="number"
                v-model="form.target_amount"
                min="100000"
                step="50000"
                placeholder="Masukkan target nominal"
                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
              />
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Batas Waktu (Deadline) <span class="text-rose-500">*</span>
            </label>
            <CustomDatePicker
              v-model="form.deadline"
              :min="minDeadline"
              placeholder="Pilih tanggal batas waktu"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Link Video YouTube / Vimeo (Opsional)
            </label>
            <input
              type="url"
              v-model="form.video_url"
              placeholder="Masukkan link video YouTube / Vimeo"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Deskripsi Proyek (Markdown) <span class="text-rose-500">*</span>
          </label>
          <textarea
            v-model="form.description"
            rows="8"
            placeholder="Masukkan rincian deskripsi lengkap proyek kampanye..."
            class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition leading-relaxed"
          ></textarea>
        </div>
      </div>
    </div>

    <!-- TAB 2: Manage Reward Tiers Builder -->
    <div v-else-if="activeTab === 'tiers'" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-base font-bold text-slate-900">Kelola Paket Reward (Tiers)</h3>
          <p class="text-xs text-slate-500 mt-0.5">
            Edit paket reward yang ada, tambahkan tier baru, atau tandai tier yang ingin dihapus.
          </p>
        </div>
        <button
          type="button"
          @click="addNewTier"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm"
        >
          <i class="pi pi-plus text-[10px]"></i>
          <span>Tambah Tier Baru</span>
        </button>
      </div>

      <div class="space-y-4">
        <div
          v-for="(tier, idx) in tiers"
          :key="tier.id || idx"
          :class="[
            'p-5 rounded-2xl border transition-all duration-200 space-y-4 relative overflow-hidden',
            tier.isMarkedForDeletion
              ? 'bg-rose-50/70 border-rose-300 opacity-75'
              : tier.isNew
              ? 'bg-blue-50/40 border-blue-200'
              : 'bg-slate-50 border-slate-200',
          ]"
        >
          <!-- Deletion Overlay / Tag if Marked for Deletion -->
          <div
            v-if="tier.isMarkedForDeletion"
            class="p-3 bg-rose-100 border border-rose-300 rounded-xl flex items-center justify-between text-xs text-rose-800 font-semibold"
          >
            <div class="flex items-center gap-2">
              <i class="pi pi-trash text-rose-600"></i>
              <span>Tier ini ditandai untuk dihapus dan akan dihapus saat Anda menekan tombol "Simpan".</span>
            </div>
            <button
              type="button"
              @click="unmarkTierForDeletion(idx)"
              class="px-3 py-1 bg-white hover:bg-rose-50 text-rose-700 rounded-lg font-bold border border-rose-300 transition shadow-sm"
            >
              Urungkan
            </button>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold uppercase tracking-wider text-blue-700 bg-blue-100 px-2.5 py-1 rounded-lg">
                {{ tier.isNew ? `Tier Baru #${idx + 1}` : `Tier #${idx + 1}` }}
              </span>
              <span v-if="tier.isNew" class="text-[10px] bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-md uppercase">
                Baru
              </span>
            </div>

            <!-- Delete / Mark Button -->
            <button
              v-if="!tier.isMarkedForDeletion"
              type="button"
              @click="markTierForDeletion(idx)"
              class="text-xs text-rose-600 hover:text-rose-800 font-bold inline-flex items-center gap-1"
            >
              <i class="pi pi-trash text-xs"></i>
              <span>Hapus Tier</span>
            </button>
          </div>

          <div :class="tier.isMarkedForDeletion ? 'pointer-events-none opacity-50' : ''" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Nama Tier</label>
                <input
                  type="text"
                  v-model="tier.name"
                  placeholder="Masukkan nama paket tier"
                  class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Min. Nominal (Rp)</label>
                <input
                  type="number"
                  v-model="tier.min_amount"
                  min="0"
                  step="10000"
                  placeholder="Masukkan nominal minimum donasi"
                  class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Kuota (0 = Tak Terbatas)</label>
                <input
                  type="number"
                  v-model="tier.quota"
                  min="0"
                  placeholder="Masukkan kuota donatur (0 jika tanpa batas)"
                  class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
              </div>
            </div>

            <div>
              <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deskripsi Reward</label>
              <textarea
                v-model="tier.reward_description"
                rows="2"
                placeholder="Masukkan rincian reward yang didapatkan backer"
                class="w-full p-3 bg-white border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
              ></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: Manage Images -->
    <div v-else-if="activeTab === 'images'" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
      <div class="border-b border-slate-100 pb-4">
        <h3 class="text-base font-bold text-slate-900">Kelola Foto Kampanye</h3>
        <p class="text-xs text-slate-500 mt-0.5">
          Foto yang ditandai hapus akan terhapus saat klik simpan. Anda juga dapat mengunggah foto baru.
        </p>
      </div>

      <!-- Existing Images -->
      <div v-if="existingImages.length > 0" class="space-y-3">
        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
          Foto Yang Sudah Ada ({{ existingImages.length }}):
        </label>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          <div
            v-for="(img, idx) in existingImages"
            :key="img.id"
            class="relative aspect-square rounded-2xl overflow-hidden border border-slate-200 group bg-slate-900 shadow-sm"
          >
            <img
              :src="img.url"
              @error="onImageError"
              class="w-full h-full object-cover transition duration-300"
              :class="img.isMarkedForDeletion ? 'grayscale opacity-30' : ''"
            />

            <!-- Deletion Overlay on Image -->
            <div
              v-if="img.isMarkedForDeletion"
              class="absolute inset-0 bg-rose-950/80 backdrop-blur-[2px] flex flex-col items-center justify-center p-2 text-center text-white"
            >
              <i class="pi pi-trash text-lg text-rose-400 mb-1"></i>
              <span class="text-[11px] font-bold text-rose-300">Akan Dihapus</span>
              <button
                type="button"
                @click="unmarkExistingImageForDeletion(idx)"
                class="mt-2 px-2.5 py-1 bg-white/20 hover:bg-white text-white hover:text-rose-900 text-[10px] font-bold rounded-lg transition"
              >
                Urungkan
              </button>
            </div>

            <!-- Delete action button -->
            <button
              v-else
              type="button"
              @click="markExistingImageForDeletion(idx)"
              class="absolute top-2 right-2 w-7 h-7 rounded-xl bg-rose-600 text-white text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-md"
              title="Tandai untuk Dihapus"
            >
              <i class="pi pi-trash text-[11px]"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Upload New Images Box -->
      <div class="space-y-4 pt-4 border-t border-slate-100">
        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
          Tambah Foto Baru:
        </label>

        <div class="border-2 border-dashed border-slate-200 rounded-3xl p-6 text-center hover:border-blue-500 transition bg-slate-50/50">
          <input
            type="file"
            id="edit-campaign-images"
            multiple
            accept="image/jpeg,image/png,image/jpg"
            @change="handleImageSelect"
            class="hidden"
          />
          <label for="edit-campaign-images" class="cursor-pointer space-y-2 block">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto shadow-sm">
              <i class="pi pi-cloud-upload text-xl"></i>
            </div>
            <div class="text-xs sm:text-sm font-bold text-slate-800">
              Pilih foto baru dari perangkat Anda
            </div>
            <p class="text-[11px] text-slate-400">
              Format JPG, PNG &bull; Dilengkapi crop & zoom gambar
            </p>
          </label>
        </div>

        <!-- Previews of newly selected images -->
        <div v-if="newUploadedImages.length > 0" class="space-y-2">
          <span class="text-xs font-bold text-emerald-700">Foto Baru Siap Diunggah ({{ newUploadedImages.length }}):</span>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <div
              v-for="(item, idx) in newUploadedImages"
              :key="idx"
              class="relative aspect-square rounded-2xl overflow-hidden border border-emerald-300 group bg-slate-900 shadow-sm"
            >
              <img :src="item.previewUrl" class="w-full h-full object-cover" />
              <span class="absolute top-2 left-2 px-2 py-0.5 bg-emerald-600 text-white text-[9px] font-bold rounded-md uppercase">
                Baru
              </span>

              <!-- Hover overlay with Edit & Delete actions -->
              <div class="absolute inset-0 bg-slate-950/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 p-2">
                <button
                  type="button"
                  @click="openRecropNewImage(idx)"
                  class="px-2.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold flex items-center gap-1.5 transition shadow-lg"
                  title="Edit & Sesuaikan Foto"
                >
                  <i class="pi pi-pencil text-xs"></i>
                  <span>Edit</span>
                </button>
                <button
                  type="button"
                  @click="removeNewImage(idx)"
                  class="w-8 h-8 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs flex items-center justify-center transition shadow-lg"
                  title="Hapus Foto"
                >
                  <i class="pi pi-trash text-xs"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Action Bar -->
    <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
      <RouterLink
        to="/creator/dashboard"
        class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition"
      >
        Batal
      </RouterLink>

      <div class="flex items-center gap-2 w-full sm:w-auto">
        <button
          type="button"
          @click="handleSaveOnly"
          :disabled="isSaving || isSubmittingReview"
          class="flex-1 sm:flex-initial px-6 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition flex items-center justify-center gap-2"
        >
          <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
          <i v-else class="pi pi-save text-xs"></i>
          <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Draft' }}</span>
        </button>

        <button
          type="button"
          @click="handleSaveAndSubmitReview"
          :disabled="isSaving || isSubmittingReview"
          class="flex-1 sm:flex-initial px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2"
        >
          <i v-if="isSubmittingReview" class="pi pi-spin pi-spinner text-xs"></i>
          <i v-else class="pi pi-send text-xs"></i>
          <span>{{ isSubmittingReview ? 'Mengajukan...' : 'Simpan & Ajukan Review' }}</span>
        </button>
      </div>
    </div>

    <!-- 1:1 Image Cropper Modal -->
    <ImageCropperModal
      v-model:visible="isCropperOpen"
      :image-src="cropperImageSrc"
      :file-name="cropperFileName"
      @cropped="handleCropFinished"
    />
  </div>
</template>
