<template>
  <div
    :class="['tvdrp-wrap', { 'tvdrp-wrap--open': isOpen, 'tvdrp-wrap--error': !!error }]"
    ref="wrapperRef"
  >
    <label v-if="label" class="tvdrp__label">{{ label }}</label>

    <!-- Trigger -->
    <button
      ref="triggerRef"
      type="button"
      class="tvdrp__trigger"
      :aria-expanded="isOpen"
      :aria-haspopup="'dialog'"
      :disabled="disabled"
      @click="toggle"
      @keydown="handleTriggerKey"
    >
      <svg class="tvdrp__cal-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
        <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
      </svg>
      <span :class="['tvdrp__value', { 'tvdrp__value--placeholder': !hasRange }]">
        {{ displayValue }}
      </span>
      <button
        v-if="hasRange"
        type="button"
        class="tvdrp__clear-x"
        aria-label="Clear date range"
        @click.stop="clearRange"
      >
        <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M1.5 1.5l7 7M8.5 1.5l-7 7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
      </button>
      <svg v-else class="tvdrp__chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <!-- Dropdown — teleported to body -->
    <Teleport to="body">
      <Transition name="tvdrp-drop">
        <div
          v-if="isOpen"
          ref="dropdownRef"
          class="tvdrp__dropdown"
          :style="dropdownStyle"
          role="dialog"
          aria-label="Date range picker"
        >
          <!-- Month nav -->
          <div class="tvdrp__nav">
            <button type="button" class="tvdrp__nav-btn" aria-label="Previous month" @click="shiftMonth(-1)">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9 11L5 7l4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <span class="tvdrp__month-label">{{ MONTHS[viewMonth] }} {{ viewYear }}</span>
            <button type="button" class="tvdrp__nav-btn" aria-label="Next month" @click="shiftMonth(1)">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>

          <!-- Weekday headers -->
          <div class="tvdrp__grid tvdrp__grid--head">
            <span v-for="d in DAYS" :key="d" class="tvdrp__wday">{{ d }}</span>
          </div>

          <!-- Days -->
          <div class="tvdrp__grid tvdrp__grid--days">
            <button
              v-for="cell in cells"
              :key="cell.dateStr"
              type="button"
              :class="[
                'tvdrp__day',
                {
                  'tvdrp__day--other':       !cell.inMonth,
                  'tvdrp__day--today':        cell.isToday,
                  'tvdrp__day--start':        cell.dateStr === startVal,
                  'tvdrp__day--end':          cell.dateStr === endVal,
                  'tvdrp__day--in-range':     cell.inRange,
                  'tvdrp__day--hover-range':  cell.inHoverRange,
                  'tvdrp__day--disabled':     cell.disabled,
                }
              ]"
              :disabled="cell.disabled"
              :aria-selected="cell.dateStr === startVal || cell.dateStr === endVal"
              @click="selectDate(cell.dateStr)"
              @mouseenter="hoverDate = cell.dateStr"
              @mouseleave="hoverDate = null"
            >{{ cell.day }}</button>
          </div>

          <!-- Selection hint -->
          <div class="tvdrp__hint">
            <span v-if="!startVal">Click to select start date</span>
            <span v-else-if="!endVal">Click to select end date</span>
            <span v-else>{{ formatShort(startVal) }} – {{ formatShort(endVal) }}</span>
          </div>

          <!-- Actions -->
          <div class="tvdrp__actions">
            <button type="button" class="tvdrp__action-btn tvdrp__action-btn--clear" @click="clearRange">Clear</button>
            <button type="button" class="tvdrp__action-btn" @click="applyToday">Today</button>
            <button type="button" class="tvdrp__action-btn tvdrp__action-btn--done" :disabled="!startVal" @click="close">Done</button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <p v-if="error" class="tvdrp__message tvdrp__message--error" role="alert">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'

export interface DateRange {
  start: string
  end: string
}

const props = withDefaults(defineProps<{
  modelValue?: DateRange
  label?: string
  placeholder?: string
  disabled?: boolean
  error?: string
  id?: string
}>(), { disabled: false })

const emit = defineEmits<{
  'update:modelValue': [value: DateRange]
  blur: []
}>()

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']
const DAYS   = ['Su','Mo','Tu','We','Th','Fr','Sa']

const isOpen      = ref(false)
const wrapperRef  = ref<HTMLElement | null>(null)
const triggerRef  = ref<HTMLButtonElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})
const hoverDate   = ref<string | null>(null)

const todayStr = new Date().toLocaleDateString('sv-SE')

const viewYear  = ref(new Date().getFullYear())
const viewMonth = ref(new Date().getMonth())

const startVal = computed(() => props.modelValue?.start ?? '')
const endVal   = computed(() => props.modelValue?.end ?? '')
const hasRange = computed(() => !!(startVal.value || endVal.value))

const displayValue = computed(() => {
  if (!startVal.value && !endVal.value) return props.placeholder ?? 'Select date range'
  if (startVal.value && !endVal.value) return `${formatShort(startVal.value)} →`
  if (startVal.value === endVal.value) return formatShort(startVal.value)
  return `${formatShort(startVal.value)} – ${formatShort(endVal.value)}`
})

function formatShort(iso: string): string {
  return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function positionDropdown() {
  const trigger = triggerRef.value
  if (!trigger) return
  const rect = trigger.getBoundingClientRect()
  const dropW = 280
  const dropH = 340
  const spaceBelow = window.innerHeight - rect.bottom
  const top  = spaceBelow >= dropH ? rect.bottom + 4 : rect.top - dropH - 4
  const left = Math.min(rect.left, window.innerWidth - dropW - 8)
  dropdownStyle.value = { position: 'fixed', top: `${top}px`, left: `${left}px`, width: `${dropW}px`, zIndex: '1200' }
}

async function toggle() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    if (startVal.value) {
      const d = new Date(startVal.value + 'T00:00:00')
      viewYear.value  = d.getFullYear()
      viewMonth.value = d.getMonth()
    }
    await nextTick()
    positionDropdown()
  } else {
    triggerRef.value?.blur()
    emit('blur')
  }
}

function close() {
  isOpen.value = false
  triggerRef.value?.blur()
  emit('blur')
}

function shiftMonth(delta: number) {
  let m = viewMonth.value + delta
  let y = viewYear.value
  if (m > 11) { m = 0;  y++ }
  if (m < 0)  { m = 11; y-- }
  viewMonth.value = m
  viewYear.value  = y
}

interface DayCell {
  dateStr: string
  day: number
  inMonth: boolean
  isToday: boolean
  disabled: boolean
  inRange: boolean
  inHoverRange: boolean
}

const cells = computed((): DayCell[] => {
  const firstDay = new Date(viewYear.value, viewMonth.value, 1).getDay()
  const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate()
  const prevDays    = new Date(viewYear.value, viewMonth.value, 0).getDate()
  const result: DayCell[] = []

  for (let i = firstDay - 1; i >= 0; i--) {
    const d = prevDays - i
    const m = viewMonth.value - 1
    const y = m < 0 ? viewYear.value - 1 : viewYear.value
    const mon = ((m % 12) + 12) % 12
    result.push(makeCell(`${y}-${String(mon + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`, d, false))
  }
  for (let d = 1; d <= daysInMonth; d++) {
    result.push(makeCell(`${viewYear.value}-${String(viewMonth.value + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`, d, true))
  }
  const remaining = 42 - result.length
  for (let d = 1; d <= remaining; d++) {
    const m = viewMonth.value + 1
    const y = m > 11 ? viewYear.value + 1 : viewYear.value
    const mon = m % 12
    result.push(makeCell(`${y}-${String(mon + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`, d, false))
  }
  return result
})

function makeCell(dateStr: string, day: number, inMonth: boolean): DayCell {
  const s = startVal.value
  const e = endVal.value
  const h = hoverDate.value

  const inRange = !!(s && e && dateStr > s && dateStr < e)

  let inHoverRange = false
  if (s && !e && h) {
    const lo = s < h ? s : h
    const hi = s < h ? h : s
    inHoverRange = dateStr > lo && dateStr < hi
  }

  return { dateStr, day, inMonth, isToday: dateStr === todayStr, disabled: false, inRange, inHoverRange }
}

// Selecting logic: first click = start, second click = end
const selecting = ref<'start' | 'end'>('start')

function selectDate(dateStr: string) {
  const s = startVal.value
  if (!s || selecting.value === 'start') {
    // Start a new range
    emit('update:modelValue', { start: dateStr, end: '' })
    selecting.value = 'end'
  } else {
    // Complete the range
    const [lo, hi] = dateStr >= s ? [s, dateStr] : [dateStr, s]
    emit('update:modelValue', { start: lo, end: hi })
    selecting.value = 'start'
    close()
  }
}

function clearRange() {
  emit('update:modelValue', { start: '', end: '' })
  selecting.value = 'start'
  isOpen.value = false
  triggerRef.value?.blur()
}

function applyToday() {
  emit('update:modelValue', { start: todayStr, end: todayStr })
  selecting.value = 'start'
  close()
}

function handleTriggerKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle() }
  else if (e.key === 'Escape') { close() }
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as Node
  if (
    isOpen.value &&
    wrapperRef.value  && !wrapperRef.value.contains(target) &&
    dropdownRef.value && !dropdownRef.value.contains(target)
  ) {
    isOpen.value = false
    triggerRef.value?.blur()
    emit('blur')
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.tvdrp-wrap { display: flex; flex-direction: column; gap: var(--tv-space-2); position: relative; }

.tvdrp__label {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text);
  display: flex; align-items: center; gap: var(--tv-space-1);
}

.tvdrp__trigger {
  display: flex; align-items: center; gap: var(--tv-space-2); width: 100%;
  padding: var(--tv-space-2) var(--tv-space-3) var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  min-height: 42px; cursor: pointer; text-align: left;
  transition: border-color var(--tv-transition-fast), box-shadow var(--tv-transition-fast);
}
.tvdrp__trigger:focus {
  outline: none; border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvdrp-wrap--open .tvdrp__trigger {
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvdrp-wrap--error .tvdrp__trigger { border-color: var(--tv-danger); }

.tvdrp__cal-icon { color: var(--tv-text-muted); flex-shrink: 0; }
.tvdrp__value { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tvdrp__value--placeholder { color: var(--tv-text-muted); }
.tvdrp__chevron { color: var(--tv-text-muted); flex-shrink: 0; transition: transform 200ms ease; }
.tvdrp-wrap--open .tvdrp__chevron { transform: rotate(180deg); color: var(--tv-primary); }

.tvdrp__clear-x {
  display: flex; align-items: center; justify-content: center;
  width: 16px; height: 16px; flex-shrink: 0;
  background: var(--tv-bg-soft); border: none; border-radius: var(--tv-radius-full);
  color: var(--tv-text-muted); cursor: pointer; padding: 0;
  transition: background 0.12s, color 0.12s;
}
.tvdrp__clear-x:hover { background: var(--tv-danger-soft); color: var(--tv-danger-fg); }

.tvdrp__message { font-size: var(--tv-text-xs); }
.tvdrp__message--error { color: var(--tv-danger); }

/* Transition */
.tvdrp-drop-enter-active { transition: opacity 150ms ease, transform 150ms ease; }
.tvdrp-drop-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.tvdrp-drop-enter-from { opacity: 0; transform: translateY(-6px); }
.tvdrp-drop-leave-to  { opacity: 0; transform: translateY(-4px); }
</style>

<style>
/* Dropdown — unscoped (teleported to body) */
.tvdrp__dropdown {
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-primary);
  border-radius: var(--tv-radius);
  box-shadow: var(--tv-shadow-md);
  padding: var(--tv-space-3);
  z-index: 1200;
  user-select: none;
}

.tvdrp__nav {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: var(--tv-space-2);
}
.tvdrp__nav-btn {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm); background: transparent;
  color: var(--tv-text-secondary); cursor: pointer; transition: background 0.15s;
}
.tvdrp__nav-btn:hover { background: var(--tv-bg-soft); }
.tvdrp__month-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }

.tvdrp__grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.tvdrp__grid--head { margin-bottom: 4px; }
.tvdrp__wday {
  text-align: center; font-size: 10px; font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted); text-transform: uppercase; padding: 2px 0;
}

.tvdrp__day {
  display: flex; align-items: center; justify-content: center;
  width: 100%; aspect-ratio: 1; font-size: var(--tv-text-xs);
  border: none; border-radius: var(--tv-radius-sm); background: transparent;
  color: var(--tv-text); cursor: pointer; transition: background 0.1s, color 0.1s;
  padding: 0; position: relative;
}
.tvdrp__day:hover:not(:disabled) { background: var(--tv-primary-soft); color: var(--tv-primary); }
.tvdrp__day--other { color: var(--tv-text-muted); opacity: 0.4; }
.tvdrp__day--today { font-weight: var(--tv-font-bold); color: var(--tv-primary); }
.tvdrp__day--start,
.tvdrp__day--end {
  background: var(--tv-primary) !important;
  color: var(--tv-text-inverse) !important;
  font-weight: var(--tv-font-semibold);
  border-radius: var(--tv-radius-sm);
}
.tvdrp__day--in-range {
  background: var(--tv-primary-soft);
  color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%);
  border-radius: 0;
}
.tvdrp__day--hover-range {
  background: hsl(var(--tv-primary-h), 70%, 94%);
  color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%);
  border-radius: 0;
}
.tvdrp__day--start { border-radius: var(--tv-radius-sm) 0 0 var(--tv-radius-sm); }
.tvdrp__day--end   { border-radius: 0 var(--tv-radius-sm) var(--tv-radius-sm) 0; }
.tvdrp__day--start.tvdrp__day--end { border-radius: var(--tv-radius-sm); }
.tvdrp__day--disabled { opacity: 0.3; cursor: not-allowed; }

.tvdrp__hint {
  margin-top: var(--tv-space-2);
  padding: var(--tv-space-1) 0;
  font-size: var(--tv-text-xs); color: var(--tv-text-muted); text-align: center;
  border-top: 1px solid var(--tv-border);
}

.tvdrp__actions {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: var(--tv-space-2); padding-top: var(--tv-space-2);
  border-top: 1px solid var(--tv-border); gap: var(--tv-space-2);
}
.tvdrp__action-btn {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  color: var(--tv-primary); background: transparent; border: none;
  cursor: pointer; padding: 2px var(--tv-space-2); border-radius: var(--tv-radius-sm);
  transition: background 0.15s;
}
.tvdrp__action-btn:hover:not(:disabled) { background: var(--tv-primary-soft); }
.tvdrp__action-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.tvdrp__action-btn--clear { color: var(--tv-text-muted); }
.tvdrp__action-btn--clear:hover { background: var(--tv-bg-soft); color: var(--tv-danger-fg); }
.tvdrp__action-btn--done {
  background: var(--tv-primary); color: var(--tv-text-inverse);
  padding: 4px var(--tv-space-3); font-weight: var(--tv-font-semibold);
}
.tvdrp__action-btn--done:hover:not(:disabled) { background: var(--tv-primary-hover); }
</style>
