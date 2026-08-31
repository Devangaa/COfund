<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number, Boolean, null],
    default: '',
  },
  options: {
    type: Array,
    required: true,
    // Each item can be string or { label: string, value: any, icon?: string, badge?: string }
  },
  placeholder: {
    type: String,
    default: 'Pilih opsi...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  theme: {
    type: String,
    default: 'light', // 'light' | 'dark'
  },
  size: {
    type: String,
    default: 'md', // 'sm' | 'md'
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const isOpen = ref(false)

const normalizedOptions = computed(() => {
  return props.options.map((opt) => {
    if (typeof opt === 'object' && opt !== null) {
      return {
        label: opt.label !== undefined ? opt.label : opt.name || String(opt.value),
        value: opt.value !== undefined ? opt.value : opt.id,
        icon: opt.icon || null,
        badge: opt.badge || null,
      }
    }
    return {
      label: String(opt),
      value: opt,
      icon: null,
      badge: null,
    }
  })
})

const selectedOption = computed(() => {
  return normalizedOptions.value.find((opt) => String(opt.value) === String(props.modelValue))
})

function toggleDropdown() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
}

function closeDropdown() {
  isOpen.value = false
}

function selectOption(opt) {
  emit('update:modelValue', opt.value)
  emit('change', opt.value)
  closeDropdown()
}
</script>

<template>
  <div class="relative w-full text-left" v-click-outside="closeDropdown">
    <!-- Trigger Button -->
    <button
      type="button"
      @click="toggleDropdown"
      :disabled="disabled"
      :class="[
        'w-full flex items-center justify-between transition-all duration-200 focus:outline-none select-none',
        size === 'sm' ? 'px-3 py-2 text-xs rounded-xl' : 'px-4 py-2.5 sm:py-3 text-xs sm:text-sm rounded-2xl',
        theme === 'dark'
          ? 'bg-slate-800/90 border border-slate-700/80 text-white hover:bg-slate-800 focus:ring-2 focus:ring-blue-500'
          : 'bg-slate-50 border border-slate-200 text-slate-900 hover:bg-white hover:border-slate-300 focus:ring-2 focus:ring-blue-500 focus:bg-white shadow-sm',
        isOpen ? (theme === 'dark' ? 'ring-2 ring-blue-500 border-blue-500' : 'ring-2 ring-blue-500 border-blue-500 bg-white') : '',
        disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer',
      ]"
    >
      <div class="flex items-center gap-2 truncate pr-2">
        <i v-if="selectedOption?.icon" :class="[selectedOption.icon, 'text-xs text-blue-500']"></i>
        <span v-if="selectedOption" class="font-semibold truncate">
          {{ selectedOption.label }}
        </span>
        <span v-else class="text-slate-400 font-normal truncate">
          {{ placeholder }}
        </span>
      </div>

      <div class="flex items-center gap-1.5 flex-shrink-0 text-slate-400">
        <span
          v-if="selectedOption?.badge"
          :class="[
            'text-[10px] px-1.5 py-0.5 rounded-md font-bold',
            theme === 'dark' ? 'bg-slate-700 text-slate-300' : 'bg-slate-200 text-slate-700',
          ]"
        >
          {{ selectedOption.badge }}
        </span>
        <i
          class="pi pi-chevron-down text-[11px] transition-transform duration-200"
          :class="isOpen ? 'rotate-180 text-blue-500' : ''"
        ></i>
      </div>
    </button>

    <!-- Dropdown Options Menu -->
    <div
      v-if="isOpen"
      :class="[
        'absolute left-0 right-0 mt-1.5 z-50 rounded-2xl shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150 max-h-60 overflow-y-auto scrollbar-thin',
        theme === 'dark'
          ? 'bg-slate-900 border border-slate-800 text-white'
          : 'bg-white border border-slate-200 text-slate-900 shadow-slate-200/50',
      ]"
    >
      <div class="p-1.5 space-y-0.5">
        <button
          v-for="opt in normalizedOptions"
          :key="String(opt.value)"
          type="button"
          @click="selectOption(opt)"
          :class="[
            'w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition text-left',
            String(opt.value) === String(modelValue)
              ? (theme === 'dark' ? 'bg-blue-600/20 text-sky-400 font-bold' : 'bg-blue-50 text-blue-600 font-bold')
              : (theme === 'dark' ? 'hover:bg-slate-800 text-slate-300 hover:text-white' : 'hover:bg-slate-50 text-slate-700 hover:text-slate-900'),
          ]"
        >
          <div class="flex items-center gap-2.5 truncate">
            <i
              v-if="opt.icon"
              :class="[
                opt.icon,
                String(opt.value) === String(modelValue) ? 'text-blue-500' : 'text-slate-400',
              ]"
            ></i>
            <span class="truncate">{{ opt.label }}</span>
          </div>

          <div class="flex items-center gap-2 flex-shrink-0">
            <span
              v-if="opt.badge"
              :class="[
                'text-[10px] px-1.5 py-0.5 rounded-md font-semibold',
                String(opt.value) === String(modelValue)
                  ? 'bg-blue-600 text-white'
                  : (theme === 'dark' ? 'bg-slate-800 text-slate-400' : 'bg-slate-100 text-slate-600'),
              ]"
            >
              {{ opt.badge }}
            </span>
            <i
              v-if="String(opt.value) === String(modelValue)"
              class="pi pi-check text-xs text-blue-500 font-bold"
            ></i>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
