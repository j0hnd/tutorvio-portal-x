<template>
  <div
    :class="['tvdp-wrap', { 'tvdp-wrap--error': !!error, 'tvdp-wrap--disabled': disabled, 'tvdp-wrap--open': isOpen }]"
    ref="wrapperRef"
  >
    <label v-if="label" :id="`${uid}-label`" class="tvdp__label">
      {{ label }}
      <span v-if="required" class="tvdp__required" aria-hidden="true">*</span>
    </label>

    <!-- Trigger -->
    <button
      ref="triggerRef"
      :id="uid"
      type="button"
      class="tvdp__trigger"
      :aria-expanded="isOpen"
      :aria-haspopup="'dialog'"
      :aria-labelledby="label ? `${uid}-label ${uid}` : undefined"
      :aria-describedby="error ? `${uid}-error` : hint ? `${uid}-hint` : undefined"
      :aria-invalid="!!error"
      :disabled="disabled"
      @click="toggle"
      @keydown="handleTriggerKey"
    >
      <svg class="tvdp__cal-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
        <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
      </svg>
      <span :class="['tvdp__value', { 'tvdp__value--placeholder': !modelValue }]">
        {{ displayValue }}
      </span>
      <svg class="tvdp__chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <!-- Calendar dropdown — teleported to body to escape overflow clipping -->
    <Teleport to="body">
    <Transition name="tvdp-drop">
      <div v-if="isOpen" ref="dropdownRef" class="tvdp__dropdown" :style="dropdownStyle" role="dialog" :aria-label="label ?? 'Date picker'">

        <!-- Month nav -->
        <div class="tvdp__nav">
          <button type="button" class="tvdp__nav-btn" aria-label="Previous month" @click="shiftMonth(-1)">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9 11L5 7l4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <span class="tvdp__month-label">{{ MONTHS[viewMonth] }} {{ viewYear }}</span>
          <button type="button" class="tvdp__nav-btn" aria-label="Next month" @click="shiftMonth(1)">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>

        <!-- Weekday headers -->
        <div class="tvdp__grid tvdp__grid--head">
          <span v-for="d in DAYS" :key="d" class="tvdp__wday">{{ d }}</span>
        </div>

        <!-- Days -->
        <div class="tvdp__grid tvdp__grid--days">
          <button
            v-for="cell in cells"
            :key="cell.dateStr"
            type="button"
            :class="[
              'tvdp__day',
              {
                'tvdp__day--other':    !cell.inMonth,
                'tvdp__day--today':     cell.isToday,
                'tvdp__day--selected':  cell.dateStr === modelValue,
                'tvdp__day--disabled':  cell.disabled,
                'tvdp__day--focused':   cell.dateStr === focusedDate,
              }
            ]"
            :disabled="cell.disabled"
            :tabindex="cell.dateStr === (focusedDate ?? modelValue ?? cells.find(c => c.inMonth && !c.disabled)?.dateStr) ? 0 : -1"
            :aria-selected="cell.dateStr === modelValue"
            :aria-label="cell.label"
            @click="selectDate(cell.dateStr)"
            @keydown="handleGridKey($event, cell.dateStr)"
            @focus="focusedDate = cell.dateStr"
          >
            {{ cell.day }}
          </button>
        </div>

        <!-- Clear / Today actions -->
        <div class="tvdp__actions">
          <button type="button" class="tvdp__action-btn" @click="selectDate(todayStr)">Today</button>
          <button v-if="modelValue" type="button" class="tvdp__action-btn tvdp__action-btn--clear" @click="clearDate">Clear</button>
        </div>
      </div>
    </Transition>
    </Teleport>

    <p v-if="error" :id="`${uid}-error`" class="tvdp__message tvdp__message--error" role="alert">{{ error }}</p>
    <p v-else-if="hint" :id="`${uid}-hint`" class="tvdp__message tvdp__message--hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: string   // YYYY-MM-DD or ''
  label?: string
  placeholder?: string
  min?: string          // YYYY-MM-DD
  max?: string          // YYYY-MM-DD
  disabled?: boolean
  required?: boolean
  error?: string
  hint?: string
  id?: string
}>(), { disabled: false, required: false })

const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: []
}>()

const uid = computed(() => props.id ?? `tvdp-${Math.random().toString(36).slice(2, 9)}`)

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']
const DAYS   = ['Su','Mo','Tu','We','Th','Fr','Sa']

const isOpen        = ref(false)
const focusedDate   = ref<string | null>(null)
const wrapperRef    = ref<HTMLElement | null>(null)
const triggerRef    = ref<HTMLElement | null>(null)
const dropdownRef   = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

const todayStr = new Date().toLocaleDateString('sv-SE')

// View state: which month/year we are browsing
const viewYear  = ref(new Date().getFullYear())
const viewMonth = ref(new Date().getMonth())

// When value changes externally, sync view to that date
watch(() => props.modelValue, (val) => {
  if (val) {
    const d = new Date(val + 'T00:00:00')
    viewYear.value  = d.getFullYear()
    viewMonth.value = d.getMonth()
  }
}, { immediate: true })

const displayValue = computed(() => {
  if (!props.modelValue) return props.placeholder ?? 'Select date'
  const d = new Date(props.modelValue + 'T00:00:00')
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
})

function positionDropdown() {
  const trigger = triggerRef.value
  if (!trigger) return
  const rect = trigger.getBoundingClientRect()
  const dropW = 280
  const dropH = 330
  const spaceBelow = window.innerHeight - rect.bottom
  const top  = spaceBelow >= dropH ? rect.bottom + 4 : rect.top - dropH - 4
  const left = Math.min(rect.left, window.innerWidth - dropW - 8)
  dropdownStyle.value = {
    position: 'fixed',
    top:    `${top}px`,
    left:   `${left}px`,
    width:  `${dropW}px`,
    zIndex: '1200',
  }
}

async function toggle() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    focusedDate.value = props.modelValue ?? null
    if (props.modelValue) {
      const d = new Date(props.modelValue + 'T00:00:00')
      viewYear.value  = d.getFullYear()
      viewMonth.value = d.getMonth()
    }
    await nextTick()
    positionDropdown()
  } else {
    emit('blur')
  }
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
  label: string
}

const cells = computed((): DayCell[] => {
  const firstDay = new Date(viewYear.value, viewMonth.value, 1).getDay()
  const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate()
  const prevDays = new Date(viewYear.value, viewMonth.value, 0).getDate()

  const result: DayCell[] = []

  // Prev month tail
  for (let i = firstDay - 1; i >= 0; i--) {
    const d = prevDays - i
    const m = viewMonth.value - 1
    const y = m < 0 ? viewYear.value - 1 : viewYear.value
    const mon = ((m % 12) + 12) % 12
    const dateStr = `${y}-${String(mon + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    result.push(makeCell(dateStr, d, false))
  }

  // Current month
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${viewYear.value}-${String(viewMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    result.push(makeCell(dateStr, d, true))
  }

  // Next month head
  const remaining = 42 - result.length
  for (let d = 1; d <= remaining; d++) {
    const m = viewMonth.value + 1
    const y = m > 11 ? viewYear.value + 1 : viewYear.value
    const mon = m % 12
    const dateStr = `${y}-${String(mon + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    result.push(makeCell(dateStr, d, false))
  }

  return result
})

function makeCell(dateStr: string, day: number, inMonth: boolean): DayCell {
  const disabled =
    (!!props.min && dateStr < props.min) ||
    (!!props.max && dateStr > props.max)
  const d = new Date(dateStr + 'T00:00:00')
  const label = d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
  return { dateStr, day, inMonth, isToday: dateStr === todayStr, disabled, label }
}

function selectDate(dateStr: string) {
  const cell = cells.value.find(c => c.dateStr === dateStr)
  if (cell?.disabled) return
  emit('update:modelValue', dateStr)
  isOpen.value = false
  emit('blur')
}

function clearDate() {
  emit('update:modelValue', '')
  isOpen.value = false
}

function handleTriggerKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle() }
  else if (e.key === 'Escape') { isOpen.value = false; emit('blur') }
}

function handleGridKey(e: KeyboardEvent, dateStr: string) {
  const idx = cells.value.findIndex(c => c.dateStr === dateStr)
  let next = idx

  if (e.key === 'ArrowRight') { e.preventDefault(); next = idx + 1 }
  else if (e.key === 'ArrowLeft') { e.preventDefault(); next = idx - 1 }
  else if (e.key === 'ArrowDown') { e.preventDefault(); next = idx + 7 }
  else if (e.key === 'ArrowUp') { e.preventDefault(); next = idx - 7 }
  else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectDate(dateStr); return }
  else if (e.key === 'Escape') { isOpen.value = false; return }
  else return

  // Navigate across month boundaries
  if (next < 0 || next >= cells.value.length) {
    const newDate = new Date(dateStr + 'T00:00:00')
    const delta = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : e.key === 'ArrowDown' ? 7 : -7
    newDate.setDate(newDate.getDate() + delta)
    viewYear.value  = newDate.getFullYear()
    viewMonth.value = newDate.getMonth()
    const newStr = newDate.toLocaleDateString('sv-SE')
    focusedDate.value = newStr
    return
  }

  const target = cells.value[next]
  if (!target.inMonth) {
    viewYear.value  = new Date(target.dateStr + 'T00:00:00').getFullYear()
    viewMonth.value = new Date(target.dateStr + 'T00:00:00').getMonth()
  }
  focusedDate.value = target.dateStr
  // Focus the button
  const btn = wrapperRef.value?.querySelector<HTMLButtonElement>(`[aria-label="${target.label}"]`)
  btn?.focus()
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as Node
  if (
    isOpen.value &&
    wrapperRef.value  && !wrapperRef.value.contains(target) &&
    dropdownRef.value && !dropdownRef.value.contains(target)
  ) {
    isOpen.value = false; emit('blur')
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.tvdp-wrap { display: flex; flex-direction: column; gap: var(--tv-space-2); position: relative; }

.tvdp__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  display: flex; align-items: center; gap: var(--tv-space-1);
}
.tvdp__required { color: var(--tv-danger); }

/* ── Trigger ── (mirrors tv-select__trigger exactly) */
.tvdp__trigger {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  width: 100%;
  padding: var(--tv-space-2) var(--tv-space-3) var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius);
  min-height: 42px;
  cursor: pointer;
  text-align: left;
  transition: border-color var(--tv-transition-fast), box-shadow var(--tv-transition-fast);
}
.tvdp__trigger:focus {
  outline: none;
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvdp-wrap--open .tvdp__trigger {
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvdp-wrap--error .tvdp__trigger { border-color: var(--tv-danger); }
.tvdp-wrap--disabled .tvdp__trigger { background: var(--tv-bg-soft); opacity: 0.6; cursor: not-allowed; }

.tvdp__cal-icon { color: var(--tv-text-muted); flex-shrink: 0; }
.tvdp__value { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tvdp__value--placeholder { color: var(--tv-text-muted); }
.tvdp__chevron {
  color: var(--tv-text-muted); flex-shrink: 0;
  transition: transform 200ms ease, color 150ms ease;
}
.tvdp-wrap--open .tvdp__chevron { transform: rotate(180deg); color: var(--tv-primary); }

/* Messages */
.tvdp__message { font-size: var(--tv-text-xs); line-height: var(--tv-leading-snug); }
.tvdp__message--error { color: var(--tv-danger); }
.tvdp__message--hint  { color: var(--tv-text-muted); }
</style>

<style>
/* ── Dropdown (unscoped — teleported to body) ── */
.tvdp__dropdown {
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-primary);
  border-radius: var(--tv-radius);
  box-shadow: var(--tv-shadow-md);
  padding: var(--tv-space-3);
  width: 280px;
  z-index: var(--tv-z-modal-dropdown);
}

/* Month nav */
.tvdp__nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--tv-space-2);
}
.tvdp__nav-btn {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: transparent;
  color: var(--tv-text-secondary);
  cursor: pointer;
  transition: background 0.15s;
}
.tvdp__nav-btn:hover { background: var(--tv-bg-soft); }
.tvdp__month-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }

/* Grid */
.tvdp__grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}
.tvdp__grid--head { margin-bottom: 4px; }
.tvdp__wday {
  text-align: center;
  font-size: 10px;
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  padding: 2px 0;
}

/* Day button */
.tvdp__day {
  display: flex; align-items: center; justify-content: center;
  width: 100%; aspect-ratio: 1;
  font-size: var(--tv-text-xs);
  border: none;
  border-radius: var(--tv-radius-sm);
  background: transparent;
  color: var(--tv-text);
  cursor: pointer;
  transition: background 0.1s, color 0.1s;
  padding: 0;
}
.tvdp__day:hover:not(:disabled) { background: var(--tv-primary-soft); color: var(--tv-primary); }
.tvdp__day:focus { outline: 2px solid var(--tv-primary); outline-offset: -1px; }
.tvdp__day--other { color: var(--tv-text-muted); opacity: 0.4; }
.tvdp__day--today { font-weight: var(--tv-font-bold); color: var(--tv-primary); }
.tvdp__day--selected {
  background: var(--tv-primary) !important;
  color: var(--tv-text-inverse) !important;
  font-weight: var(--tv-font-semibold);
}
.tvdp__day--disabled { opacity: 0.3; cursor: not-allowed; }

/* Actions row */
.tvdp__actions {
  display: flex;
  justify-content: space-between;
  margin-top: var(--tv-space-2);
  padding-top: var(--tv-space-2);
  border-top: 1px solid var(--tv-border);
}
.tvdp__action-btn {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  color: var(--tv-primary);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 2px var(--tv-space-1);
  border-radius: var(--tv-radius-sm);
  transition: background 0.15s;
}
.tvdp__action-btn:hover { background: var(--tv-primary-soft); }
.tvdp__action-btn--clear { color: var(--tv-text-muted); }
.tvdp__action-btn--clear:hover { background: var(--tv-bg-soft); color: var(--tv-danger-fg); }

/* Transition */
.tvdp-drop-enter-active { transition: opacity 150ms ease, transform 150ms ease; }
.tvdp-drop-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.tvdp-drop-enter-from { opacity: 0; transform: translateY(-6px); }
.tvdp-drop-leave-to  { opacity: 0; transform: translateY(-4px); }
</style>
