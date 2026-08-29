<script setup>
import { ref, computed, watch } from 'vue'
import dayjs from 'dayjs'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  min: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Pilih tanggal batas waktu',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const isOpen = ref(false)
const currentView = ref('days') // 'days' | 'months' | 'years'

// Internal navigation state
const defaultMinDate = computed(() => {
  if (props.min && dayjs(props.min).isValid()) {
    return dayjs(props.min)
  }
  return dayjs().add(8, 'day')
})

const viewYear = ref(defaultMinDate.value.year())
const viewMonth = ref(defaultMinDate.value.month()) // 0 - 11
const yearRangeStart = ref(Math.floor(defaultMinDate.value.year() / 12) * 12)

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

const shortMonthNames = [
  'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
  'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
]

const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

// Synchronize with modelValue when changed
watch(
  () => props.modelValue,
  (val) => {
    if (val && dayjs(val).isValid()) {
      const d = dayjs(val)
      viewYear.value = d.year()
      viewMonth.value = d.month()
      yearRangeStart.value = Math.floor(d.year() / 12) * 12
    }
  },
  { immediate: true }
)

const displayValue = computed(() => {
  if (!props.modelValue || !dayjs(props.modelValue).isValid()) {
    return ''
  }
  return dayjs(props.modelValue).format('DD MMMM YYYY')
})

// Generate days grid for the current viewMonth & viewYear
const calendarDays = computed(() => {
  const firstDayOfMonth = dayjs().year(viewYear.value).month(viewMonth.value).date(1)
  const daysInMonth = firstDayOfMonth.daysInMonth()
  const startingDayIndex = firstDayOfMonth.day() // 0 (Sun) to 6 (Sat)

  const days = []

  // 1. Previous month padding days
  const prevMonth = firstDayOfMonth.subtract(1, 'month')
  const prevMonthDays = prevMonth.daysInMonth()
  for (let i = startingDayIndex - 1; i >= 0; i--) {
    const d = prevMonth.date(prevMonthDays - i)
    days.push({
      dateStr: d.format('YYYY-MM-DD'),
      dayNumber: d.date(),
      isCurrentMonth: false,
      isPrev: true,
      isDisabled: isDateDisabled(d),
      isSelected: isDateSelected(d),
      isToday: isDateToday(d),
    })
  }

  // 2. Current month days
  for (let i = 1; i <= daysInMonth; i++) {
    const d = firstDayOfMonth.date(i)
    days.push({
      dateStr: d.format('YYYY-MM-DD'),
      dayNumber: i,
      isCurrentMonth: true,
      isDisabled: isDateDisabled(d),
      isSelected: isDateSelected(d),
      isToday: isDateToday(d),
    })
  }

  // 3. Next month padding days to complete grid
  const remaining = (7 - (days.length % 7)) % 7
  const nextMonth = firstDayOfMonth.add(1, 'month')
  for (let i = 1; i <= remaining; i++) {
    const d = nextMonth.date(i)
    days.push({
      dateStr: d.format('YYYY-MM-DD'),
      dayNumber: i,
      isCurrentMonth: false,
      isNext: true,
      isDisabled: isDateDisabled(d),
      isSelected: isDateSelected(d),
      isToday: isDateToday(d),
    })
  }

  return days
})

// 12-Year window list
const yearsList = computed(() => {
  const list = []
  for (let i = 0; i < 12; i++) {
    list.push(yearRangeStart.value + i)
  }
  return list
})

function isDateDisabled(d) {
  if (!props.min) return false
  return d.isBefore(dayjs(props.min), 'day')
}

function isDateSelected(d) {
  if (!props.modelValue) return false
  return d.isSame(dayjs(props.modelValue), 'day')
}

function isDateToday(d) {
  return d.isSame(dayjs(), 'day')
}

// Open / Close actions
function toggleDropdown() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    currentView.value = 'days'
    // Focus calendar view to either selected date or minimum allowed date (e.g. H+7)
    if (props.modelValue && dayjs(props.modelValue).isValid()) {
      const d = dayjs(props.modelValue)
      viewYear.value = d.year()
      viewMonth.value = d.month()
      yearRangeStart.value = Math.floor(d.year() / 12) * 12
    } else {
      const initDate = defaultMinDate.value
      viewYear.value = initDate.year()
      viewMonth.value = initDate.month()
      yearRangeStart.value = Math.floor(initDate.year() / 12) * 12
    }
  }
}

function closeDropdown() {
  isOpen.value = false
  currentView.value = 'days'
}

// Navigation arrows
function handlePrev() {
  if (currentView.value === 'days') {
    if (viewMonth.value === 0) {
      viewMonth.value = 11
      viewYear.value--
    } else {
      viewMonth.value--
    }
  } else if (currentView.value === 'months') {
    viewYear.value--
  } else if (currentView.value === 'years') {
    yearRangeStart.value -= 12
  }
}

function handleNext() {
  if (currentView.value === 'days') {
    if (viewMonth.value === 11) {
      viewMonth.value = 0
      viewYear.value++
    } else {
      viewMonth.value++
    }
  } else if (currentView.value === 'months') {
    viewYear.value++
  } else if (currentView.value === 'years') {
    yearRangeStart.value += 12
  }
}

// View switching
function openMonthsView() {
  currentView.value = 'months'
}

function openYearsView() {
  yearRangeStart.value = Math.floor(viewYear.value / 12) * 12
  currentView.value = 'years'
}

function selectMonth(mIndex) {
  viewMonth.value = mIndex
  currentView.value = 'days' // Switches back to days view
}

function selectYear(y) {
  viewYear.value = y
  currentView.value = 'months' // Switches to months view
}

function selectDay(dayItem) {
  if (dayItem.isDisabled) return
  emit('update:modelValue', dayItem.dateStr)
  emit('change', dayItem.dateStr)
  closeDropdown()
}

function selectMinDate() {
  const minDateStr = defaultMinDate.value.format('YYYY-MM-DD')
  emit('update:modelValue', minDateStr)
  emit('change', minDateStr)
  closeDropdown()
}

function clearDate(event) {
  event.stopPropagation()
  emit('update:modelValue', '')
  emit('change', '')
}
</script>

<template>
  <div class="relative w-full" v-click-outside="closeDropdown">
    <!-- Input Trigger Field -->
    <div
      @click="toggleDropdown"
      :class="[
        'w-full px-4 py-3 bg-slate-50 border rounded-xl text-xs sm:text-sm font-medium transition cursor-pointer flex items-center justify-between gap-2 select-none',
        isOpen
          ? 'border-blue-500 ring-2 ring-blue-500/20 bg-white'
          : 'border-slate-200 hover:border-slate-300 hover:bg-white',
        disabled ? 'opacity-60 cursor-not-allowed pointer-events-none' : '',
      ]"
    >
      <div class="flex items-center gap-2.5 truncate">
        <i class="pi pi-calendar text-blue-600 text-sm"></i>
        <span v-if="displayValue" class="font-bold text-slate-900 truncate">
          {{ displayValue }}
        </span>
        <span v-else class="text-slate-400 font-normal truncate">
          {{ placeholder }}
        </span>
      </div>

      <div class="flex items-center gap-1.5 flex-shrink-0">
        <button
          v-if="modelValue"
          type="button"
          @click.stop="clearDate"
          class="w-5 h-5 rounded-full hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition"
          title="Hapus tanggal"
        >
          <i class="pi pi-times text-[10px]"></i>
        </button>
        <i
          :class="[
            'pi pi-chevron-down text-slate-400 text-[10px] transition-transform duration-200',
            isOpen ? 'rotate-180 text-blue-600' : '',
          ]"
        ></i>
      </div>
    </div>

    <!-- Calendar Popover Panel -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="transform scale-95 opacity-0 -translate-y-2"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      leave-to-class="transform scale-95 opacity-0 -translate-y-2"
    >
      <div
        v-if="isOpen"
        @click.stop
        class="absolute left-0 top-full mt-2 w-80 sm:w-88 p-4 bg-white rounded-3xl border border-slate-200/90 shadow-2xl z-50 overflow-hidden"
      >
        <!-- Popover Top Header Controls -->
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
          <button
            type="button"
            @click.stop="handlePrev"
            class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition"
          >
            <i class="pi pi-chevron-left text-xs"></i>
          </button>

          <!-- Header Titles with Drill-down click handlers -->
          <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800">
            <!-- VIEW: DAYS (Shows Month & Year buttons) -->
            <template v-if="currentView === 'days'">
              <button
                type="button"
                @click.stop="openMonthsView"
                class="px-2.5 py-1 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition"
              >
                {{ monthNames[viewMonth] }}
              </button>
              <button
                type="button"
                @click.stop="openYearsView"
                class="px-2.5 py-1 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition text-slate-500 hover:text-blue-600 font-semibold"
              >
                {{ viewYear }}
              </button>
            </template>

            <!-- VIEW: MONTHS (Shows Year button) -->
            <template v-else-if="currentView === 'months'">
              <button
                type="button"
                @click.stop="openYearsView"
                class="px-3 py-1 rounded-lg hover:bg-blue-50 text-blue-600 hover:text-blue-700 font-extrabold text-sm transition"
              >
                {{ viewYear }}
              </button>
            </template>

            <!-- VIEW: YEARS (Shows Range label) -->
            <template v-else-if="currentView === 'years'">
              <span class="px-3 py-1 text-blue-600 font-extrabold text-sm">
                {{ yearRangeStart }} - {{ yearRangeStart + 11 }}
              </span>
            </template>
          </div>

          <button
            type="button"
            @click.stop="handleNext"
            class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition"
          >
            <i class="pi pi-chevron-right text-xs"></i>
          </button>
        </div>

        <!-- 1. VIEW: DAYS GRID -->
        <div v-if="currentView === 'days'" class="space-y-2">
          <!-- Day Name Headers -->
          <div class="grid grid-cols-7 text-center">
            <div
              v-for="dName in dayNames"
              :key="dName"
              class="text-[11px] font-bold text-slate-400 py-1 uppercase tracking-wider"
            >
              {{ dName }}
            </div>
          </div>

          <!-- Days Grid Matrix -->
          <div class="grid grid-cols-7 gap-1">
            <button
              v-for="(day, idx) in calendarDays"
              :key="idx"
              type="button"
              :disabled="day.isDisabled"
              @click.stop="selectDay(day)"
              :class="[
                'h-9 rounded-xl text-xs font-semibold flex items-center justify-center transition relative',
                day.isSelected
                  ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/30 ring-2 ring-blue-500/20 z-10'
                  : day.isDisabled
                  ? 'text-slate-300 cursor-not-allowed bg-transparent'
                  : day.isCurrentMonth
                  ? 'text-slate-800 hover:bg-blue-50 hover:text-blue-600'
                  : 'text-slate-300 hover:bg-slate-50',
                day.isToday && !day.isSelected ? 'border border-blue-400 font-bold text-blue-600' : '',
              ]"
            >
              <span>{{ day.dayNumber }}</span>
            </button>
          </div>
        </div>

        <!-- 2. VIEW: MONTHS GRID -->
        <div v-else-if="currentView === 'months'" class="grid grid-cols-3 gap-2 py-2">
          <button
            v-for="(mName, mIdx) in shortMonthNames"
            :key="mIdx"
            type="button"
            @click.stop="selectMonth(mIdx)"
            :class="[
              'py-3 rounded-2xl text-xs font-bold transition',
              viewMonth === mIdx
                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/25'
                : 'bg-slate-50 text-slate-700 hover:bg-blue-50 hover:text-blue-600',
            ]"
          >
            {{ mName }}
          </button>
        </div>

        <!-- 3. VIEW: YEARS GRID -->
        <div v-else-if="currentView === 'years'" class="grid grid-cols-3 gap-2 py-2">
          <button
            v-for="y in yearsList"
            :key="y"
            type="button"
            @click.stop="selectYear(y)"
            :class="[
              'py-3 rounded-2xl text-xs font-bold transition',
              viewYear === y
                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/25'
                : 'bg-slate-50 text-slate-700 hover:bg-blue-50 hover:text-blue-600',
            ]"
          >
            {{ y }}
          </button>
        </div>

        <!-- Bottom Quick Actions -->
        <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between text-xs">
          <button
            type="button"
            @click.stop="selectMinDate"
            class="text-blue-600 hover:underline font-bold"
          >
            Pilih Min ({{ defaultMinDate.format('D MMM') }})
          </button>
          <button
            type="button"
            @click.stop="closeDropdown"
            class="px-3 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition"
          >
            Tutup
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
