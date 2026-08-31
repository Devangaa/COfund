<script setup>
import { useToastStore } from '@/stores/useToastStore'

const toastStore = useToastStore()

function getIcon(type) {
  switch (type) {
    case 'success':
      return 'pi pi-check-circle'
    case 'error':
      return 'pi pi-times-circle'
    case 'warning':
      return 'pi pi-exclamation-triangle'
    case 'info':
    default:
      return 'pi pi-info-circle'
  }
}

function getStyleClasses(type) {
  switch (type) {
    case 'success':
      return {
        card: 'bg-white border-emerald-500/30 shadow-emerald-950/10',
        iconBg: 'bg-emerald-100 text-emerald-600',
        title: 'text-emerald-950',
        progress: 'bg-emerald-500',
      }
    case 'error':
      return {
        card: 'bg-white border-rose-500/30 shadow-rose-950/10',
        iconBg: 'bg-rose-100 text-rose-600',
        title: 'text-rose-950',
        progress: 'bg-rose-500',
      }
    case 'warning':
      return {
        card: 'bg-white border-amber-500/30 shadow-amber-950/10',
        iconBg: 'bg-amber-100 text-amber-700',
        title: 'text-amber-950',
        progress: 'bg-amber-500',
      }
    case 'info':
    default:
      return {
        card: 'bg-white border-blue-500/30 shadow-blue-950/10',
        iconBg: 'bg-blue-100 text-blue-600',
        title: 'text-blue-950',
        progress: 'bg-blue-500',
      }
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed z-[999999] pointer-events-none flex flex-col gap-2.5 sm:top-5 sm:right-5 sm:w-[380px] max-w-full top-4 inset-x-4"
    >
      <TransitionGroup name="toast-anim">
        <div
          v-for="toast in toastStore.toasts"
          :key="toast.id"
          :class="[
            'pointer-events-auto w-full rounded-2xl p-4 border shadow-2xl backdrop-blur-md relative overflow-hidden transition-all duration-300',
            getStyleClasses(toast.type).card,
          ]"
        >
          <div class="flex items-start gap-3">
            <!-- Icon -->
            <div
              :class="[
                'w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base shadow-xs',
                getStyleClasses(toast.type).iconBg,
              ]"
            >
              <i :class="getIcon(toast.type)"></i>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 pr-1">
              <h4
                :class="[
                  'text-xs font-extrabold tracking-tight leading-tight',
                  getStyleClasses(toast.type).title,
                ]"
              >
                {{ toast.title }}
              </h4>
              <p class="text-xs font-medium text-slate-600 mt-0.5 leading-relaxed break-words">
                {{ toast.message }}
              </p>
            </div>

            <!-- Close Button -->
            <button
              type="button"
              @click="toastStore.removeToast(toast.id)"
              class="w-6 h-6 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition flex-shrink-0"
              aria-label="Tutup notifikasi"
            >
              <i class="pi pi-times text-[10px]"></i>
            </button>
          </div>

          <!-- Subtle Progress Bar Countdown -->
          <div
            v-if="toast.timeout > 0"
            :class="['absolute bottom-0 left-0 right-0 h-1 opacity-70', getStyleClasses(toast.type).progress]"
            :style="{
              animation: `toast-progress ${toast.timeout}ms linear forwards`,
            }"
          ></div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes toast-progress {
  from {
    width: 100%;
  }
  to {
    width: 0%;
  }
}

/* Toast Transitions */
.toast-anim-enter-active,
.toast-anim-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-anim-enter-from {
  opacity: 0;
  transform: translateY(-16px) scale(0.95);
}

.toast-anim-leave-to {
  opacity: 0;
  transform: translateX(30px) scale(0.95);
}

@media (max-width: 640px) {
  .toast-anim-leave-to {
    opacity: 0;
    transform: translateY(-16px) scale(0.95);
  }
}
</style>
