<script setup>
import { ref, watch } from 'vue'
import { campaignUpdateService } from '@/services/campaignUpdateService'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  campaignSlug: {
    type: String,
    required: true,
  },
  updateData: {
    type: Object,
    default: () => null,
  },
})

const emit = defineEmits(['update:visible', 'updated'])
const toast = useToast()

const title = ref('')
const content = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')

watch(
  () => props.updateData,
  (val) => {
    if (val) {
      title.value = val.title || ''
      content.value = val.content || ''
    } else {
      title.value = ''
      content.value = ''
    }
    errorMessage.value = ''
  },
  { immediate: true }
)

function handleClose() {
  errorMessage.value = ''
  emit('update:visible', false)
}

async function handleSubmit() {
  if (!title.value.trim()) {
    errorMessage.value = 'Judul update wajib diisi'
    return
  }
  if (!content.value.trim()) {
    errorMessage.value = 'Konten update wajib diisi'
    return
  }
  if (!props.updateData?.id) {
    errorMessage.value = 'ID update tidak valid'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const response = await campaignUpdateService.update(props.campaignSlug, props.updateData.id, {
      title: title.value,
      content: content.value,
    })
    toast.success(response.data?.message || 'Update kabar proyek berhasil diperbarui!')
    emit('updated', response.data?.data)
    handleClose()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Gagal memperbarui update kabar proyek.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200"
  >
    <div
      class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative overflow-hidden animate-in zoom-in-95 duration-200"
    >
      <button
        @click="handleClose"
        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 flex items-center justify-center transition"
      >
        <i class="pi pi-times text-xs"></i>
      </button>

      <div class="mb-6">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-50 text-amber-800 mb-2">
          <i class="pi pi-pencil text-[10px]"></i>
          Edit Kabar Proyek
        </span>
        <h3 class="text-xl font-bold text-slate-900">
          Ubah Kabar Proyek
        </h3>
        <p class="text-xs text-slate-500 mt-1">
          Perbarui teks informasi perkembangan milestone kampanye Anda.
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Judul Update
          </label>
          <input
            type="text"
            v-model="title"
            placeholder="Judul update..."
            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Konten / Pesan Update
          </label>
          <textarea
            v-model="content"
            rows="6"
            placeholder="Tuliskan isi pembaruan kabar proyek..."
            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
          ></textarea>
        </div>

        <p v-if="errorMessage" class="text-xs text-rose-500 font-medium">
          {{ errorMessage }}
        </p>

        <div class="flex gap-3 pt-2">
          <button
            type="button"
            @click="handleClose"
            class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="flex-2 py-3 px-6 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-md shadow-blue-600/20 flex items-center justify-center gap-2"
          >
            <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
            <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
