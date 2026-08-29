<script setup>
import { ref } from 'vue'

defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  campaignTitle: {
    type: String,
    default: '',
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'confirm'])

const rejectionNote = ref('')
const error = ref('')

function handleClose() {
  rejectionNote.value = ''
  error.value = ''
  emit('update:visible', false)
}

function handleConfirm() {
  if (!rejectionNote.value.trim()) {
    error.value = 'Alasan penolakan wajib diisi agar creator dapat memperbaikinya'
    return
  }
  emit('confirm', rejectionNote.value)
  handleClose()
}
</script>

<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200"
  >
    <div
      class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative overflow-hidden animate-in zoom-in-95 duration-200"
    >
      <button
        @click="handleClose"
        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 flex items-center justify-center transition"
      >
        <i class="pi pi-times text-xs"></i>
      </button>

      <div class="mb-5">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
          <i class="pi pi-exclamation-triangle text-xl"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900">
          Tolak Kampanye
        </h3>
        <p class="text-xs text-slate-500 mt-1">
          Kampanye <strong>{{ campaignTitle }}</strong> akan dikembalikan ke status <em>Draft</em>.
        </p>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
            Catatan / Alasan Penolakan
          </label>
          <textarea
            v-model="rejectionNote"
            rows="4"
            placeholder="Jelaskan bagian apa yang belum memenuhi syarat kelayakan (misal: dokumen kurang lengkap, target tidak realistis)..."
            class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition"
          ></textarea>
          <p v-if="error" class="text-xs text-rose-500 font-medium mt-1">
            {{ error }}
          </p>
        </div>

        <div class="flex gap-3 pt-2">
          <button
            type="button"
            @click="handleClose"
            :disabled="isSubmitting"
            class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
          >
            Batal
          </button>
          <button
            type="button"
            @click="handleConfirm"
            :disabled="isSubmitting"
            class="flex-2 py-3 px-6 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 transition shadow-md shadow-amber-600/20 flex items-center justify-center gap-2"
          >
            <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
            <span>{{ isSubmitting ? 'Memproses...' : 'Kirim Penolakan' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
