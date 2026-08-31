<script setup>
import { ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useCampaignStore } from '@/stores/useCampaignStore'
import { formatCurrency } from '@/utils/formatCurrency'
import { useToast } from '@/composables/useToast'
import CustomDatePicker from '@/components/common/CustomDatePicker.vue'
import ImageCropperModal from '@/components/common/ImageCropperModal.vue'
import CustomSelect from '@/components/common/CustomSelect.vue'
import dayjs from 'dayjs'

const router = useRouter()
const toast = useToast()
const campaignStore = useCampaignStore()

const currentStep = ref(1) // 1: Info Dasar, 2: Reward Tiers, 3: Upload Gambar, 4: Ringkasan & Publish

// Step 1: Info Dasar (Tanpa auto-fill)
const form = ref({
  category_id: 1,
  title: '',
  slug: '',
  description: '',
  target_amount: null,
  deadline: '',
  video_url: '',
})

// Step 2: Tiers (Min 1 tier required, tanpa auto-fill teks bawaan)
const tiers = ref([
  {
    name: '',
    min_amount: null,
    quota: null,
    reward_description: '',
  },
])

// Step 3: Images State with Original Raw Preservation
// Each item: { rawFile: File, rawSrc: string, croppedFile: File, previewUrl: string, name: string }
const uploadedImages = ref([])
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

function addTier() {
  tiers.value.push({
    name: '',
    min_amount: null,
    quota: null,
    reward_description: '',
  })
}

function removeTier(index) {
  if (tiers.value.length <= 1) {
    toast.warning('Kampanye wajib memiliki minimal 1 tier reward.')
    return
  }
  tiers.value.splice(index, 1)
}

function readFileAsDataURL(file) {
  return new Promise((resolve) => {
    const reader = new FileReader()
    reader.onload = (e) => resolve(e.target.result)
    reader.readAsDataURL(file)
  })
}

async function handleImageChange(event) {
  const files = Array.from(event.target.files || [])
  if (uploadedImages.value.length + files.length > 5) {
    toast.error('Maksimal 5 gambar per kampanye.')
    return
  }

  // Filter valid images and preserve raw uncropped data
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
  processNextInQueue()

  event.target.value = ''
}

function processNextInQueue() {
  if (cropQueue.value.length === 0) return
  const item = cropQueue.value.shift()
  currentProcessingRawItem.value = item
  cropperImageSrc.value = item.rawSrc
  cropperFileName.value = item.name
  isCropperOpen.value = true
}

function handleCropFinished({ file, previewUrl }) {
  if (recropTargetIndex.value !== null) {
    // Re-cropping an existing image: update croppedFile & previewUrl while keeping the original uncropped rawSrc intact
    const target = uploadedImages.value[recropTargetIndex.value]
    if (target) {
      target.croppedFile = file
      target.previewUrl = previewUrl
    }
    recropTargetIndex.value = null
  } else if (currentProcessingRawItem.value) {
    // Adding newly cropped image item
    uploadedImages.value.push({
      rawFile: currentProcessingRawItem.value.rawFile,
      rawSrc: currentProcessingRawItem.value.rawSrc,
      name: currentProcessingRawItem.value.name,
      croppedFile: file,
      previewUrl: previewUrl,
    })
    currentProcessingRawItem.value = null
  }

  // Check if there are more images in the queue
  if (cropQueue.value.length > 0) {
    setTimeout(() => {
      processNextInQueue()
    }, 150)
  }
}

function openRecrop(index) {
  const item = uploadedImages.value[index]
  if (!item) return
  recropTargetIndex.value = index
  // Open cropper with the ORIGINAL raw image before crop
  cropperImageSrc.value = item.rawSrc
  cropperFileName.value = item.name || `image-${index + 1}.jpg`
  isCropperOpen.value = true
}

function removeImage(index) {
  uploadedImages.value.splice(index, 1)
}

// Navigation Step Validations
function nextStep() {
  if (currentStep.value === 1) {
    if (!form.value.title.trim()) {
      toast.warning('Judul kampanye wajib diisi.')
      return
    }
    if (form.value.title.length > 100) {
      toast.warning('Judul maksimal 100 karakter.')
      return
    }
    if (!form.value.description.trim()) {
      toast.warning('Deskripsi proyek wajib diisi.')
      return
    }
    if (!form.value.target_amount || Number(form.value.target_amount) < 100000) {
      toast.warning('Target dana minimal Rp 100.000.')
      return
    }
    if (!form.value.deadline) {
      toast.warning('Batas waktu (deadline) wajib dipilih.')
      return
    }
    if (dayjs(form.value.deadline).diff(dayjs(), 'day') < 6) {
      toast.warning('Deadline minimal 7 hari dari hari ini.')
      return
    }
    currentStep.value = 2
  } else if (currentStep.value === 2) {
    if (tiers.value.length === 0) {
      toast.warning('Tambahkan minimal 1 tier reward.')
      return
    }
    for (let i = 0; i < tiers.value.length; i++) {
      const t = tiers.value[i]
      if (!t.name.trim()) {
        toast.warning(`Nama Tier #${i + 1} wajib diisi.`)
        return
      }
      if (t.min_amount === null || t.min_amount === '' || Number(t.min_amount) < 0) {
        toast.warning(`Nominal minimum Tier #${i + 1} tidak valid.`)
        return
      }
    }
    currentStep.value = 3
  } else if (currentStep.value === 3) {
    if (uploadedImages.value.length === 0) {
      toast.warning('Unggah minimal 1 foto sampul kampanye.')
      return
    }
    currentStep.value = 4
  }
}

function prevStep() {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

async function handlePublish() {
  const formData = new FormData()
  formData.append('category_id', form.value.category_id)
  formData.append('title', form.value.title)
  if (form.value.slug.trim()) {
    formData.append('slug', form.value.slug.trim())
  }
  formData.append('description', form.value.description)
  formData.append('target_amount', form.value.target_amount)
  formData.append('deadline', form.value.deadline)
  if (form.value.video_url.trim()) {
    formData.append('video_url', form.value.video_url.trim())
  }

  // Append cropped image files
  uploadedImages.value.forEach((item) => {
    formData.append('images[]', item.croppedFile)
  })

  // Append tiers
  tiers.value.forEach((tier, index) => {
    formData.append(`tiers[${index}][name]`, tier.name)
    formData.append(`tiers[${index}][min_amount]`, tier.min_amount)
    formData.append(`tiers[${index}][quota]`, tier.quota || 0)
    if (tier.reward_description?.trim()) {
      formData.append(`tiers[${index}][reward_description]`, tier.reward_description)
    }
  })

  const res = await campaignStore.createCampaign(formData)
  if (res.success) {
    router.push('/creator/dashboard')
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <RouterLink to="/creator/dashboard" class="text-xs font-semibold text-blue-600 hover:underline inline-flex items-center gap-1 mb-2">
          <i class="pi pi-arrow-left text-[10px]"></i>
          <span>Kembali ke Dashboard</span>
        </RouterLink>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          Buat Kampanye Baru
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Lengkapi formulir di bawah ini untuk memulai penggalangan dana proyek Anda.
        </p>
      </div>
    </div>

    <!-- Stepper Navigation -->
    <div class="grid grid-cols-4 gap-2 sm:gap-4 p-2 bg-white rounded-2xl border border-slate-200 shadow-sm text-center">
      <div
        :class="[
          'py-2 px-1 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5',
          currentStep === 1
            ? 'bg-blue-600 text-white shadow-sm'
            : currentStep > 1
            ? 'text-emerald-600 bg-emerald-50'
            : 'text-slate-400',
        ]"
      >
        <i :class="currentStep > 1 ? 'pi pi-check' : 'pi pi-info-circle'" class="text-xs"></i>
        <span class="hidden sm:inline">1. Info Dasar</span>
      </div>
      <div
        :class="[
          'py-2 px-1 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5',
          currentStep === 2
            ? 'bg-blue-600 text-white shadow-sm'
            : currentStep > 2
            ? 'text-emerald-600 bg-emerald-50'
            : 'text-slate-400',
        ]"
      >
        <i :class="currentStep > 2 ? 'pi pi-check' : 'pi pi-list'" class="text-xs"></i>
        <span class="hidden sm:inline">2. Reward Tiers</span>
      </div>
      <div
        :class="[
          'py-2 px-1 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5',
          currentStep === 3
            ? 'bg-blue-600 text-white shadow-sm'
            : currentStep > 3
            ? 'text-emerald-600 bg-emerald-50'
            : 'text-slate-400',
        ]"
      >
        <i :class="currentStep > 3 ? 'pi pi-check' : 'pi pi-images'" class="text-xs"></i>
        <span class="hidden sm:inline">3. Foto Kampanye</span>
      </div>
      <div
        :class="[
          'py-2 px-1 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5',
          currentStep === 4
            ? 'bg-blue-600 text-white shadow-sm'
            : 'text-slate-400',
        ]"
      >
        <i class="pi pi-send text-xs"></i>
        <span class="hidden sm:inline">4. Konfirmasi</span>
      </div>
    </div>

    <!-- Step 1: Info Dasar Form -->
    <div v-if="currentStep === 1" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
      <div class="border-b border-slate-100 pb-4">
        <h3 class="text-lg font-bold text-slate-900">Langkah 1: Informasi Proyek</h3>
        <p class="text-xs text-slate-500 mt-0.5">Tuliskan identitas utama proyek yang akan dilihat oleh calon donatur.</p>
      </div>

      <div class="space-y-4">
        <!-- Title -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Judul Kampanye <span class="text-rose-500">*</span>
          </label>
          <input
            type="text"
            v-model="form.title"
            maxlength="100"
            placeholder="Masukkan judul kampanye"
            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
          />
          <div class="text-[11px] text-slate-400 text-right mt-1">{{ form.title.length }}/100 Karakter</div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Category -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Kategori <span class="text-rose-500">*</span>
            </label>
            <CustomSelect
              v-model="form.category_id"
              :options="categories"
              placeholder="Pilih Kategori Kampanye"
            />
          </div>

          <!-- Slug Custom -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Kustom URL Slug (Opsional)
            </label>
            <input
              type="text"
              v-model="form.slug"
              placeholder="Masukkan kustom slug URL"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Target Amount -->
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
            <p class="text-[11px] text-slate-400 mt-1">Minimal target: Rp 100.000</p>
          </div>

          <!-- Deadline with CustomDatePicker -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Batas Waktu Kampanye (Deadline) <span class="text-rose-500">*</span>
            </label>
            <CustomDatePicker
              v-model="form.deadline"
              :min="minDeadline"
              placeholder="Pilih tanggal batas waktu"
            />
            <p class="text-[11px] text-slate-400 mt-1">Minimal 7 hari dari tanggal pembuatan</p>
          </div>
        </div>

        <!-- Video URL -->
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

        <!-- Description Markdown -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Deskripsi Lengkap Proyek (Format Markdown didukung) <span class="text-rose-500">*</span>
          </label>
          <textarea
            v-model="form.description"
            rows="8"
            placeholder="Masukkan deskripsi lengkap proyek kampanye, latar belakang, solusi, dan rencana penggunaan dana"
            class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition leading-relaxed"
          ></textarea>
        </div>
      </div>
    </div>

    <!-- Step 2: Reward Tiers Builder -->
    <div v-else-if="currentStep === 2" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Langkah 2: Paket Reward (Tiers)</h3>
          <p class="text-xs text-slate-500 mt-0.5">Tentukan paket imbalan reward yang menarik bagi calon backer.</p>
        </div>
        <button
          type="button"
          @click="addTier"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm"
        >
          <i class="pi pi-plus text-[10px]"></i>
          <span>Tambah Tier</span>
        </button>
      </div>

      <div class="space-y-4">
        <div
          v-for="(tier, idx) in tiers"
          :key="idx"
          class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-4 relative"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg">
              Tier #{{ idx + 1 }}
            </span>
            <button
              v-if="tiers.length > 1"
              type="button"
              @click="removeTier(idx)"
              class="text-xs text-rose-500 hover:text-rose-700 font-semibold inline-flex items-center gap-1"
            >
              <i class="pi pi-trash"></i>
              <span>Hapus Tier</span>
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Nama Paket Tier</label>
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
            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deskripsi Reward & Fasilitas</label>
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

    <!-- Step 3: Images Upload with Cropping -->
    <div v-else-if="currentStep === 3" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
      <div class="border-b border-slate-100 pb-4">
        <h3 class="text-lg font-bold text-slate-900">Langkah 3: Unggah Foto Kampanye</h3>
        <p class="text-xs text-slate-500 mt-0.5">Unggah 1 hingga 5 foto visual menarik untuk mewakili kampanye Anda.</p>
      </div>

      <div class="space-y-4">
        <!-- Upload Dropzone -->
        <div class="border-2 border-dashed border-slate-200 rounded-3xl p-8 text-center hover:border-blue-500 transition bg-slate-50/50">
          <input
            type="file"
            id="campaign-images"
            multiple
            accept="image/jpeg,image/png,image/jpg"
            @change="handleImageChange"
            class="hidden"
          />
          <label for="campaign-images" class="cursor-pointer space-y-3 block">
            <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto shadow-sm">
              <i class="pi pi-cloud-upload text-2xl"></i>
            </div>
            <div class="space-y-1">
              <div class="text-sm font-bold text-slate-800">
                Pilih atau seret gambar ke sini
              </div>
              <p class="text-xs text-slate-400">
                Maksimal 5 foto, format JPG/PNG &bull; Dilengkapi crop & zoom gambar
              </p>
            </div>
          </label>
        </div>

        <!-- Preview Grid -->
        <div v-if="uploadedImages.length > 0" class="space-y-2">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
            Foto yang Diunggah ({{ uploadedImages.length }}/5):
          </label>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
            <div
              v-for="(item, idx) in uploadedImages"
              :key="idx"
              class="relative aspect-square rounded-2xl overflow-hidden border border-slate-200 group bg-slate-900 shadow-sm"
            >
              <img :src="item.previewUrl" class="w-full h-full object-cover" />
              <span v-if="idx === 0" class="absolute top-2 left-2 px-2 py-0.5 bg-blue-600 text-white text-[9px] font-bold rounded-md uppercase shadow-sm">
                Foto Utama
              </span>

              <!-- Hover overlay with Edit & Delete actions -->
              <div class="absolute inset-0 bg-slate-950/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 p-2">
                <button
                  type="button"
                  @click="openRecrop(idx)"
                  class="px-2.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold flex items-center gap-1.5 transition shadow-lg"
                  title="Edit & Sesuaikan Foto"
                >
                  <i class="pi pi-pencil text-xs"></i>
                  <span>Edit</span>
                </button>
                <button
                  type="button"
                  @click="removeImage(idx)"
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

    <!-- Step 4: Summary & Publish -->
    <div v-else-if="currentStep === 4" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
      <div class="border-b border-slate-100 pb-4">
        <h3 class="text-lg font-bold text-slate-900">Langkah 4: Konfirmasi Kampanye</h3>
        <p class="text-xs text-slate-500 mt-0.5">Periksa kembali ringkasan draf kampanye sebelum disimpan.</p>
      </div>

      <div class="space-y-4 text-xs">
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
          <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Judul & Target</div>
          <div class="text-base font-extrabold text-slate-900">{{ form.title }}</div>
          <div class="text-slate-600">
            Target: <strong class="text-blue-600">{{ formatCurrency(form.target_amount) }}</strong> &bull; Deadline: <strong>{{ form.deadline }}</strong>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
          <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Jumlah Reward Tiers</div>
          <div class="font-bold text-slate-800">{{ tiers.length }} Paket Terdaftar</div>
          <ul class="list-disc list-inside text-slate-600 space-y-1">
            <li v-for="t in tiers" :key="t.name">
              {{ t.name }} - {{ formatCurrency(t.min_amount) }} (Kuota: {{ t.quota || 'Tanpa Batas' }})
            </li>
          </ul>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
          <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Foto Visual</div>
          <div class="font-bold text-slate-800">{{ uploadedImages.length }} Foto Terlampir</div>
        </div>
      </div>
    </div>

    <!-- Stepper Footer Buttons -->
    <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
      <button
        v-if="currentStep > 1"
        type="button"
        @click="prevStep"
        class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition flex items-center gap-1.5"
      >
        <i class="pi pi-arrow-left text-[10px]"></i>
        <span>Sebelumnya</span>
      </button>
      <div v-else></div>

      <div class="flex items-center gap-3">
        <button
          v-if="currentStep < 4"
          type="button"
          @click="nextStep"
          class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 transition flex items-center gap-1.5"
        >
          <span>Lanjutkan</span>
          <i class="pi pi-arrow-right text-[10px]"></i>
        </button>

        <button
          v-else
          type="button"
          @click="handlePublish"
          :disabled="campaignStore.isSubmitting"
          class="px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-lg shadow-emerald-600/25 transition flex items-center gap-2"
        >
          <i v-if="campaignStore.isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
          <i v-else class="pi pi-check text-xs"></i>
          <span>{{ campaignStore.isSubmitting ? 'Menyimpan...' : 'Simpan Sebagai Draft' }}</span>
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
