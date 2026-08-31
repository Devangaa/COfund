<script setup>
import { useConfirmStore } from '@/stores/useConfirmStore'

const confirmStore = useConfirmStore()

function getIcon(type) {
  switch (type) {
    case 'danger':
      return 'pi pi-trash'
    case 'success':
      return 'pi pi-check-circle'
    case 'info':
      return 'pi pi-info-circle'
    case 'warning':
    default:
      return 'pi pi-exclamation-triangle'
  }
}

function getTypeStyles(type) {
  switch (type) {
    case 'danger':
      return {
        iconBg: 'bg-rose-50 text-rose-600 border border-rose-200',
        confirmBtn: 'bg-rose-600 hover:bg-rose-700 text-white shadow-rose-600/20',
      }
    case 'success':
      return {
        iconBg: 'bg-emerald-50 text-emerald-600 border border-emerald-200',
        confirmBtn: 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/20',
      }
    case 'info':
      return {
        iconBg: 'bg-blue-50 text-blue-600 border border-blue-200',
        confirmBtn: 'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-600/20',
      }
    case 'warning':
    default:
      return {
        iconBg: 'bg-amber-50 text-amber-600 border border-amber-200',
        confirmBtn: 'bg-amber-600 hover:bg-amber-700 text-white shadow-amber-600/20',
      }
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-modal">
      <div
        v-if="confirmStore.isOpen"
        class="fixed inset-0 z-[9999999] flex items-center justify-center p-4"
      >
        <!-- Backdrop Blur Overlay -->
        <div
          class="confirm-backdrop absolute inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity"
          @click="confirmStore.handleCancel"
        ></div>

        <!-- Dialog Card -->
        <div
          class="confirm-card relative z-10 w-full max-w-md bg-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-200/80 overflow-hidden space-y-6"
        >
          <div class="flex items-start gap-4">
            <!-- Icon -->
            <div
              :class="[
                'w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 text-xl shadow-xs',
                getTypeStyles(confirmStore.type).iconBg,
              ]"
            >
              <i :class="getIcon(confirmStore.type)"></i>
            </div>

            <!-- Title & Message -->
            <div class="flex-1 min-w-0">
              <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">
                {{ confirmStore.title }}
              </h3>
              <p class="text-xs sm:text-sm text-slate-600 mt-1.5 leading-relaxed">
                {{ confirmStore.message }}
              </p>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              @click="confirmStore.handleCancel"
              class="px-5 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition"
            >
              {{ confirmStore.cancelText }}
            </button>
            <button
              type="button"
              @click="confirmStore.handleConfirm"
              :class="[
                'px-5 py-2.5 rounded-2xl font-bold text-xs shadow-md transition flex items-center gap-2',
                getTypeStyles(confirmStore.type).confirmBtn,
              ]"
            >
              <span>{{ confirmStore.confirmText }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.confirm-modal-enter-active,
.confirm-modal-leave-active {
  transition: opacity 0.2s ease;
}

.confirm-modal-enter-active .confirm-card,
.confirm-modal-leave-active .confirm-card {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.confirm-modal-enter-from,
.confirm-modal-leave-to {
  opacity: 0;
}

.confirm-modal-enter-from .confirm-card,
.confirm-modal-leave-to .confirm-card {
  transform: scale(0.92) translateY(8px);
  opacity: 0;
}
</style>
