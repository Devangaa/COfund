<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  imageSrc: {
    type: String,
    default: '',
  },
  fileName: {
    type: String,
    default: 'campaign-image.jpg',
  },
})

const emit = defineEmits(['update:visible', 'cropped', 'cancel'])

const canvasRef = ref(null)
const zoom = ref(1) // 1 to 3
const rotation = ref(0) // 0, 90, 180, 270
const position = ref({ x: 0, y: 0 })
const isDragging = ref(false)
const dragStart = ref({ x: 0, y: 0 })
const loadedImage = ref(null)
const isProcessing = ref(false)

const VIEWPORT_SIZE = 360 // Total Canvas Size
const CROP_BOX_SIZE = 260 // Active Crop Window Size
const OUTPUT_SIZE = 800 // High-res output size

const boxX = (VIEWPORT_SIZE - CROP_BOX_SIZE) / 2
const boxY = (VIEWPORT_SIZE - CROP_BOX_SIZE) / 2

watch(
  () => props.visible,
  async (val) => {
    if (val && props.imageSrc) {
      zoom.value = 1
      rotation.value = 0
      position.value = { x: 0, y: 0 }
      await nextTick()
      loadImage()
    }
  }
)

function loadImage() {
  if (!props.imageSrc) return
  const img = new Image()
  img.crossOrigin = 'anonymous'
  img.onload = () => {
    loadedImage.value = img
    position.value = { x: 0, y: 0 }
    zoom.value = 1
    drawCanvas()
  }
  img.src = props.imageSrc
}

function renderImageTransformed(ctx, targetBoxSize) {
  const img = loadedImage.value
  if (!img) return

  const aspect = img.width / img.height
  let drawWidth, drawHeight

  if (aspect >= 1) {
    drawHeight = targetBoxSize
    drawWidth = targetBoxSize * aspect
  } else {
    drawWidth = targetBoxSize
    drawHeight = targetBoxSize / aspect
  }

  ctx.drawImage(
    img,
    -drawWidth / 2 + position.value.x / zoom.value,
    -drawHeight / 2 + position.value.y / zoom.value,
    drawWidth,
    drawHeight
  )
}

function drawCanvas() {
  const canvas = canvasRef.value
  if (!canvas || !loadedImage.value) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  canvas.width = VIEWPORT_SIZE
  canvas.height = VIEWPORT_SIZE

  // 1. Clear viewport
  ctx.clearRect(0, 0, VIEWPORT_SIZE, VIEWPORT_SIZE)

  // 2. Draw base image transformed
  ctx.save()
  ctx.translate(VIEWPORT_SIZE / 2, VIEWPORT_SIZE / 2)
  ctx.rotate((rotation.value * Math.PI) / 180)
  ctx.scale(zoom.value, zoom.value)
  renderImageTransformed(ctx, CROP_BOX_SIZE)
  ctx.restore()

  // 3. Darken the outside area
  ctx.fillStyle = 'rgba(15, 23, 42, 0.72)' // Dark overlay
  ctx.fillRect(0, 0, VIEWPORT_SIZE, VIEWPORT_SIZE)

  // 4. Clip center crop window and redraw bright clear image inside it
  ctx.save()
  ctx.beginPath()
  ctx.rect(boxX, boxY, CROP_BOX_SIZE, CROP_BOX_SIZE)
  ctx.clip()

  ctx.translate(VIEWPORT_SIZE / 2, VIEWPORT_SIZE / 2)
  ctx.rotate((rotation.value * Math.PI) / 180)
  ctx.scale(zoom.value, zoom.value)
  renderImageTransformed(ctx, CROP_BOX_SIZE)
  ctx.restore()

  // 5. Draw 3x3 Grid Overlay inside crop box
  ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)'
  ctx.lineWidth = 1
  const step = CROP_BOX_SIZE / 3

  ctx.beginPath()
  // Vertical lines
  ctx.moveTo(boxX + step, boxY)
  ctx.lineTo(boxX + step, boxY + CROP_BOX_SIZE)
  ctx.moveTo(boxX + step * 2, boxY)
  ctx.lineTo(boxX + step * 2, boxY + CROP_BOX_SIZE)
  // Horizontal lines
  ctx.moveTo(boxX, boxY + step)
  ctx.lineTo(boxX + CROP_BOX_SIZE, boxY + step)
  ctx.moveTo(boxX, boxY + step * 2)
  ctx.lineTo(boxX + CROP_BOX_SIZE, boxY + step * 2)
  ctx.stroke()

  // 6. Draw Crop Box Bright Border
  ctx.strokeStyle = '#38bdf8' // Sky 400
  ctx.lineWidth = 2
  ctx.strokeRect(boxX, boxY, CROP_BOX_SIZE, CROP_BOX_SIZE)

  // Corner accents
  const cornerLen = 14
  ctx.strokeStyle = '#ffffff'
  ctx.lineWidth = 3
  ctx.beginPath()
  // Top-left
  ctx.moveTo(boxX, boxY + cornerLen)
  ctx.lineTo(boxX, boxY)
  ctx.lineTo(boxX + cornerLen, boxY)
  // Top-right
  ctx.moveTo(boxX + CROP_BOX_SIZE - cornerLen, boxY)
  ctx.lineTo(boxX + CROP_BOX_SIZE, boxY)
  ctx.lineTo(boxX + CROP_BOX_SIZE, boxY + cornerLen)
  // Bottom-left
  ctx.moveTo(boxX, boxY + CROP_BOX_SIZE - cornerLen)
  ctx.lineTo(boxX, boxY + CROP_BOX_SIZE)
  ctx.lineTo(boxX + cornerLen, boxY + CROP_BOX_SIZE)
  // Bottom-right
  ctx.moveTo(boxX + CROP_BOX_SIZE - cornerLen, boxY + CROP_BOX_SIZE)
  ctx.lineTo(boxX + CROP_BOX_SIZE, boxY + CROP_BOX_SIZE)
  ctx.lineTo(boxX + CROP_BOX_SIZE, boxY + CROP_BOX_SIZE - cornerLen)
  ctx.stroke()
}

// Mouse / Touch Dragging
function startDrag(e) {
  isDragging.value = true
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  dragStart.value = {
    x: clientX - position.value.x,
    y: clientY - position.value.y,
  }
}

function onDrag(e) {
  if (!isDragging.value) return
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY

  position.value = {
    x: clientX - dragStart.value.x,
    y: clientY - dragStart.value.y,
  }
  drawCanvas()
}

function stopDrag() {
  isDragging.value = false
}

// Wheel zoom
function onWheel(e) {
  e.preventDefault()
  const delta = e.deltaY > 0 ? -0.1 : 0.1
  zoom.value = Math.min(Math.max(Number((zoom.value + delta).toFixed(2)), 1), 3)
  drawCanvas()
}

function handleZoomChange() {
  drawCanvas()
}

function zoomIn() {
  zoom.value = Math.min(Number((zoom.value + 0.2).toFixed(2)), 3)
  drawCanvas()
}

function zoomOut() {
  zoom.value = Math.max(Number((zoom.value - 0.2).toFixed(2)), 1)
  drawCanvas()
}

function rotateClockwise() {
  rotation.value = (rotation.value + 90) % 360
  drawCanvas()
}

function resetCrop() {
  zoom.value = 1
  rotation.value = 0
  position.value = { x: 0, y: 0 }
  drawCanvas()
}

function close() {
  emit('update:visible', false)
  emit('cancel')
}

// Export high resolution cropped square image
async function applyCrop() {
  if (!loadedImage.value) return
  isProcessing.value = true

  const exportCanvas = document.createElement('canvas')
  exportCanvas.width = OUTPUT_SIZE
  exportCanvas.height = OUTPUT_SIZE
  const ctx = exportCanvas.getContext('2d')

  const scaleRatio = OUTPUT_SIZE / CROP_BOX_SIZE

  ctx.save()
  ctx.translate(OUTPUT_SIZE / 2, OUTPUT_SIZE / 2)
  ctx.rotate((rotation.value * Math.PI) / 180)
  ctx.scale(zoom.value * scaleRatio, zoom.value * scaleRatio)

  renderImageTransformed(ctx, CROP_BOX_SIZE)
  ctx.restore()

  exportCanvas.toBlob(
    (blob) => {
      isProcessing.value = false
      if (blob) {
        const cleanName = props.fileName.replace(/\.[^/.]+$/, '') + '.jpg'
        const file = new File([blob], cleanName, { type: 'image/jpeg' })
        const previewUrl = URL.createObjectURL(blob)
        emit('cropped', { file, previewUrl })
        emit('update:visible', false)
      }
    },
    'image/jpeg',
    0.92
  )
}

onMounted(() => {
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', stopDrag)
  window.addEventListener('touchmove', onDrag)
  window.addEventListener('touchend', stopDrag)
})

onBeforeUnmount(() => {
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', stopDrag)
  window.removeEventListener('touchmove', onDrag)
  window.removeEventListener('touchend', stopDrag)
})
</script>

<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm animate-fade-in"
    @click.self="close"
  >
    <div
      class="bg-white w-full max-w-md rounded-3xl border border-slate-200/90 shadow-2xl overflow-hidden flex flex-col"
    >
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <i class="pi pi-crop text-sm"></i>
          </div>
          <div>
            <h3 class="font-extrabold text-slate-900 text-sm">Sesuaikan Foto</h3>
            <p class="text-[11px] text-slate-400">Geser atau perbesar foto sesuai keinginan.</p>
          </div>
        </div>
        <button
          type="button"
          @click="close"
          class="w-7 h-7 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition"
        >
          <i class="pi pi-times text-xs"></i>
        </button>
      </div>

      <!-- Crop Viewport (With Darkened Overflow) -->
      <div class="p-6 bg-slate-950 flex flex-col items-center justify-center select-none overflow-hidden relative">
        <div
          class="relative w-[360px] h-[360px] max-w-full aspect-square rounded-2xl overflow-hidden cursor-grab active:cursor-grabbing bg-slate-900 flex items-center justify-center shadow-inner"
          @mousedown="startDrag"
          @touchstart.passive="startDrag"
          @wheel="onWheel"
        >
          <canvas ref="canvasRef" class="w-full h-full object-contain block"></canvas>
        </div>

        <p class="text-[11px] text-slate-400 mt-3 flex items-center gap-1.5">
          <i class="pi pi-arrows-alt text-[10px]"></i>
          <span>Klik & seret gambar &bull; Scroll untuk zoom</span>
        </p>
      </div>

      <!-- Controls & Sliders -->
      <div class="p-6 space-y-4 bg-white">
        <!-- Zoom Controls -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between text-xs font-bold text-slate-700">
            <span class="flex items-center gap-1.5">
              <i class="pi pi-search text-[11px] text-blue-600"></i>
              <span>Zoom Gambar</span>
            </span>
            <span class="text-blue-600 font-mono">{{ Math.round(zoom * 100) }}%</span>
          </div>

          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="zoomOut"
              :disabled="zoom <= 1"
              class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-slate-700 flex items-center justify-center transition"
              title="Perkecil"
            >
              <i class="pi pi-minus text-[10px]"></i>
            </button>

            <input
              type="range"
              v-model.number="zoom"
              min="1"
              max="3"
              step="0.05"
              @input="handleZoomChange"
              class="w-full accent-blue-600 h-2 bg-slate-100 rounded-lg cursor-pointer"
            />

            <button
              type="button"
              @click="zoomIn"
              :disabled="zoom >= 3"
              class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-slate-700 flex items-center justify-center transition"
              title="Perbesar"
            >
              <i class="pi pi-plus text-[10px]"></i>
            </button>
          </div>
        </div>

        <!-- Extra Tools (Rotate & Reset) -->
        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
          <button
            type="button"
            @click="rotateClockwise"
            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition flex items-center gap-1.5"
          >
            <i class="pi pi-refresh text-[11px]"></i>
            <span>Putar 90&deg;</span>
          </button>

          <button
            type="button"
            @click="resetCrop"
            class="px-3 py-1.5 rounded-xl hover:bg-slate-100 text-slate-500 hover:text-slate-700 font-semibold transition"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
        <button
          type="button"
          @click="close"
          class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200/60 transition"
        >
          Batal
        </button>
        <button
          type="button"
          @click="applyCrop"
          :disabled="isProcessing"
          class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/25 transition flex items-center gap-1.5"
        >
          <i v-if="isProcessing" class="pi pi-spin pi-spinner text-xs"></i>
          <i v-else class="pi pi-check text-xs"></i>
          <span>{{ isProcessing ? 'Memproses...' : 'Terapkan' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.97);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
.animate-fade-in {
  animation: fadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
