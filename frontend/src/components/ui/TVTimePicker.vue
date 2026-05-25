<template>
  <div
    :class="['tvtp-wrap', { 'tvtp-wrap--error': !!error, 'tvtp-wrap--disabled': disabled, 'tvtp-wrap--open': isOpen }]"
    ref="wrapperRef"
  >
    <label v-if="label" :id="`${uid}-label`" class="tvtp__label">
      {{ label }}
      <span v-if="required" class="tvtp__required" aria-hidden="true">*</span>
    </label>

    <!-- Trigger -->
    <button
      :id="uid"
      type="button"
      class="tvtp__trigger"
      :aria-expanded="isOpen"
      :aria-haspopup="'listbox'"
      :aria-labelledby="label ? `${uid}-label ${uid}` : undefined"
      :aria-describedby="error ? `${uid}-error` : hint ? `${uid}-hint` : undefined"
      :aria-invalid="!!error"
      :disabled="disabled"
      @click="toggle"
      @keydown="handleTriggerKey"
    >
      <svg class="tvtp__clock-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
        <path d="M7 4.5V7l2 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
      </svg>
      <span :class="['tvtp__value', { 'tvtp__value--placeholder': !modelValue }]">
        {{ displayValue }}
      </span>
      <svg class="tvtp__chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <!-- Dropdown -->
    <Transition name="dropdown">
      <div v-if="isOpen" class="tvtp__dropdown" role="listbox" :aria-label="label ?? 'Time picker'">
        <div class="tvtp__cols">

          <!-- Hours -->
          <div class="tvtp__col" ref="hourColRef">
            <button
              v-for="h in hours"
              :key="h"
              type="button"
              :class="['tvtp__slot', { 'tvtp__slot--selected': h === selectedHour }]"
              :aria-selected="h === selectedHour"
              @click="pickHour(h)"
            >
              {{ String(h).padStart(2, '0') }}
            </button>
          </div>

          <span class="tvtp__sep">:</span>

          <!-- Minutes -->
          <div class="tvtp__col" ref="minColRef">
            <button
              v-for="m in minutes"
              :key="m"
              type="button"
              :class="['tvtp__slot', { 'tvtp__slot--selected': m === selectedMin }]"
              :aria-selected="m === selectedMin"
              @click="pickMin(m)"
            >
              {{ String(m).padStart(2, '0') }}
            </button>
          </div>

          <!-- AM/PM -->
          <div class="tvtp__col tvtp__col--ampm">
            <button
              type="button"
              :class="['tvtp__slot', { 'tvtp__slot--selected': period === 'AM' }]"
              @click="setPeriod('AM')"
            >AM</button>
            <button
              type="button"
              :class="['tvtp__slot', { 'tvtp__slot--selected': period === 'PM' }]"
              @click="setPeriod('PM')"
            >PM</button>
          </div>

        </div>

        <!-- Done -->
        <div class="tvtp__footer">
          <button type="button" class="tvtp__done" @click="isOpen = false">Done</button>
        </div>
      </div>
    </Transition>

    <p v-if="error" :id="`${uid}-error`" class="tvtp__message tvtp__message--error" role="alert">{{ error }}</p>
    <p v-else-if="hint" :id="`${uid}-hint`" class="tvtp__message tvtp__message--hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: string   // HH:MM (24h)
  label?: string
  placeholder?: string
  step?: number         // minutes between slots, default 30
  minHour?: number      // 0–23
  maxHour?: number      // 0–23
  disabled?: boolean
  required?: boolean
  error?: string
  hint?: string
  id?: string
}>(), { step: 30, minHour: 0, maxHour: 23, disabled: false, required: false })

const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: []
}>()

const uid = computed(() => props.id ?? `tvtp-${Math.random().toString(36).slice(2, 9)}`)

const isOpen      = ref(false)
const wrapperRef  = ref<HTMLElement | null>(null)
const hourColRef  = ref<HTMLElement | null>(null)
const minColRef   = ref<HTMLElement | null>(null)

// Parse current value
const selectedHour = ref(8)
const selectedMin  = ref(0)
const period       = ref<'AM' | 'PM'>('AM')

function parseValue(val: string | undefined) {
  if (!val) return
  const [hStr, mStr] = val.split(':')
  const h = parseInt(hStr, 10)
  const m = parseInt(mStr, 10)
  selectedHour.value = h === 0 ? 12 : h > 12 ? h - 12 : h
  selectedMin.value  = m
  period.value       = h < 12 ? 'AM' : 'PM'
}

watch(() => props.modelValue, parseValue, { immediate: true })

// Hours: 1–12
const hours = computed(() => Array.from({ length: 12 }, (_, i) => i + 1))

// Minutes: 0, step, step*2 … up to 59
const minutes = computed(() => {
  const slots: number[] = []
  for (let m = 0; m < 60; m += props.step) slots.push(m)
  return slots
})

// Convert to 24h for emit
function emit24h() {
  let h24 = selectedHour.value % 12
  if (period.value === 'PM') h24 += 12
  const val = `${String(h24).padStart(2, '0')}:${String(selectedMin.value).padStart(2, '0')}`
  emit('update:modelValue', val)
}

function pickHour(h: number) {
  selectedHour.value = h
  emit24h()
}

function pickMin(m: number) {
  selectedMin.value = m
  emit24h()
}

function setPeriod(p: 'AM' | 'PM') {
  period.value = p
  emit24h()
}

const displayValue = computed(() => {
  if (!props.modelValue) return props.placeholder ?? 'Select time'
  const [hStr, mStr] = props.modelValue.split(':')
  const h = parseInt(hStr, 10)
  const m = parseInt(mStr, 10)
  const h12 = h === 0 ? 12 : h > 12 ? h - 12 : h
  const ampm = h < 12 ? 'AM' : 'PM'
  return `${h12}:${String(m).padStart(2, '0')} ${ampm}`
})

async function toggle() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    await nextTick()
    scrollToSelected()
  } else {
    emit('blur')
  }
}

function scrollToSelected() {
  const scrollCol = (colRef: HTMLElement | null, selectedIdx: number) => {
    if (!colRef) return
    const buttons = colRef.querySelectorAll<HTMLButtonElement>('.tvtp__slot')
    const btn = buttons[selectedIdx]
    if (btn) btn.scrollIntoView({ block: 'center', behavior: 'instant' })
  }
  scrollCol(hourColRef.value, selectedHour.value - 1)
  scrollCol(minColRef.value, minutes.value.indexOf(selectedMin.value))
}

function handleTriggerKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle() }
  else if (e.key === 'Escape') { isOpen.value = false; emit('blur') }
}

function handleClickOutside(e: MouseEvent) {
  if (wrapperRef.value && !wrapperRef.value.contains(e.target as Node)) {
    if (isOpen.value) { isOpen.value = false; emit('blur') }
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.tvtp-wrap { display: flex; flex-direction: column; gap: var(--tv-space-2); position: relative; }

.tvtp__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  display: flex; align-items: center; gap: var(--tv-space-1);
}
.tvtp__required { color: var(--tv-danger); }

/* ── Trigger (mirrors TVSelect trigger) ── */
.tvtp__trigger {
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
.tvtp__trigger:focus {
  outline: none;
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvtp-wrap--open .tvtp__trigger {
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvtp-wrap--error .tvtp__trigger { border-color: var(--tv-danger); }
.tvtp-wrap--disabled .tvtp__trigger { background: var(--tv-bg-soft); opacity: 0.6; cursor: not-allowed; }

.tvtp__clock-icon { color: var(--tv-text-muted); flex-shrink: 0; }
.tvtp__value { flex: 1; }
.tvtp__value--placeholder { color: var(--tv-text-muted); }
.tvtp__chevron {
  color: var(--tv-text-muted); flex-shrink: 0;
  transition: transform 200ms ease, color 150ms ease;
}
.tvtp-wrap--open .tvtp__chevron { transform: rotate(180deg); color: var(--tv-primary); }

/* ── Dropdown ── */
.tvtp__dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: var(--tv-z-dropdown);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-primary);
  border-radius: var(--tv-radius);
  box-shadow: var(--tv-shadow-md);
  width: 200px;
}

.tvtp__cols {
  display: flex;
  align-items: center;
  gap: 0;
  padding: var(--tv-space-2) var(--tv-space-2) 0;
}

.tvtp__col {
  flex: 1;
  max-height: 200px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  scrollbar-width: thin;
  scrollbar-color: var(--tv-border) transparent;
}
.tvtp__col::-webkit-scrollbar { width: 3px; }
.tvtp__col::-webkit-scrollbar-thumb { background: var(--tv-border); border-radius: 99px; }

.tvtp__col--ampm { flex: 0 0 44px; }

.tvtp__sep {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  padding: 0 2px;
  flex-shrink: 0;
  align-self: flex-start;
  padding-top: 8px;
}

.tvtp__slot {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: var(--tv-space-2) 0;
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: transparent;
  border: none;
  border-radius: var(--tv-radius-sm);
  cursor: pointer;
  transition: background 0.1s, color 0.1s;
  white-space: nowrap;
}
.tvtp__slot:hover { background: var(--tv-primary-soft); color: var(--tv-primary); }
.tvtp__slot--selected {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
  font-weight: var(--tv-font-semibold);
}
.tvtp__slot--selected:hover { background: var(--tv-primary-hover); color: var(--tv-text-inverse); }

.tvtp__footer {
  display: flex;
  justify-content: flex-end;
  padding: var(--tv-space-2) var(--tv-space-3);
  border-top: 1px solid var(--tv-border);
  margin-top: var(--tv-space-2);
}
.tvtp__done {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-primary);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-sm);
  transition: background 0.15s;
}
.tvtp__done:hover { background: var(--tv-primary-soft); }

/* Messages */
.tvtp__message { font-size: var(--tv-text-xs); line-height: var(--tv-leading-snug); }
.tvtp__message--error { color: var(--tv-danger); }
.tvtp__message--hint  { color: var(--tv-text-muted); }

/* Transition */
.dropdown-enter-active { transition: opacity 150ms ease, transform 150ms ease; }
.dropdown-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.dropdown-enter-from { opacity: 0; transform: translateY(-6px); }
.dropdown-leave-to  { opacity: 0; transform: translateY(-4px); }
</style>
