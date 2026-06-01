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
      ref="triggerRef"
      :id="uid"
      type="button"
      class="tvtp__trigger"
      :aria-expanded="isOpen"
      :aria-haspopup="'dialog'"
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

    <!-- Dropdown — teleported to body to escape overflow clipping -->
    <Teleport to="body">
      <Transition name="tvtp-drop">
        <div
          v-if="isOpen"
          ref="dropdownRef"
          class="tvtp__dropdown"
          :style="dropdownStyle"
          role="dialog"
          :aria-label="label ?? 'Time picker'"
        >
          <div class="tvtp__spinners">

            <!-- Hour spinner -->
            <div class="tvtp__spinner">
              <button type="button" class="tvtp__arrow" aria-label="Hour up"   @click="adjustHour(1)">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 8l4-4 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
              <span class="tvtp__digit">{{ String(displayHour).padStart(2, '0') }}</span>
              <button type="button" class="tvtp__arrow" aria-label="Hour down" @click="adjustHour(-1)">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>

            <span class="tvtp__colon">:</span>

            <!-- Minute spinner -->
            <div class="tvtp__spinner">
              <button type="button" class="tvtp__arrow" aria-label="Minute up"   @click="adjustMin(1)">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 8l4-4 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
              <span class="tvtp__digit">{{ String(selectedMin).padStart(2, '0') }}</span>
              <button type="button" class="tvtp__arrow" aria-label="Minute down" @click="adjustMin(-1)">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>

            <!-- AM / PM toggle -->
            <div class="tvtp__ampm-col">
              <button
                type="button"
                :class="['tvtp__ampm-btn', { 'tvtp__ampm-btn--active': period === 'AM' }]"
                @click="setPeriod('AM')"
              >AM</button>
              <button
                type="button"
                :class="['tvtp__ampm-btn', { 'tvtp__ampm-btn--active': period === 'PM' }]"
                @click="setPeriod('PM')"
              >PM</button>
            </div>

          </div>

          <div class="tvtp__footer">
            <button type="button" class="tvtp__done" @click="close">Done</button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <p v-if="error" :id="`${uid}-error`" class="tvtp__message tvtp__message--error" role="alert">{{ error }}</p>
    <p v-else-if="hint" :id="`${uid}-hint`" class="tvtp__message tvtp__message--hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: string   // HH:MM 24h
  label?: string
  placeholder?: string
  step?: number         // minute step, default 15
  minHour?: number
  maxHour?: number
  disabled?: boolean
  required?: boolean
  error?: string
  hint?: string
  id?: string
}>(), { step: 15, minHour: 0, maxHour: 23, disabled: false, required: false })

const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: []
}>()

const uid         = computed(() => props.id ?? `tvtp-${Math.random().toString(36).slice(2, 9)}`)
const isOpen      = ref(false)
const wrapperRef  = ref<HTMLElement | null>(null)
const triggerRef  = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

// Internal 12h state
const selectedHour = ref(9)   // 1–12
const selectedMin  = ref(0)
const period       = ref<'AM' | 'PM'>('AM')

const displayHour = computed(() => selectedHour.value)

// Minute slots derived from step
const minuteSlots = computed(() => {
  const slots: number[] = []
  for (let m = 0; m < 60; m += props.step) slots.push(m)
  return slots
})

function parseValue(val: string | undefined) {
  if (!val) return
  const [hStr, mStr] = val.split(':')
  const h = parseInt(hStr, 10)
  const m = parseInt(mStr, 10)
  selectedHour.value = h === 0 ? 12 : h > 12 ? h - 12 : h
  // snap minute to nearest slot
  const snapped = minuteSlots.value.reduce((prev, cur) =>
    Math.abs(cur - m) < Math.abs(prev - m) ? cur : prev, minuteSlots.value[0])
  selectedMin.value = snapped
  period.value = h < 12 ? 'AM' : 'PM'
}

watch(() => props.modelValue, parseValue, { immediate: true })
watch(() => props.step, () => parseValue(props.modelValue))

function emit24h() {
  let h24 = selectedHour.value % 12
  if (period.value === 'PM') h24 += 12
  emit('update:modelValue', `${String(h24).padStart(2, '0')}:${String(selectedMin.value).padStart(2, '0')}`)
}

function adjustHour(dir: 1 | -1) {
  let h = selectedHour.value + dir
  if (h > 12) h = 1
  if (h < 1)  h = 12
  selectedHour.value = h
  emit24h()
}

function adjustMin(dir: 1 | -1) {
  const slots = minuteSlots.value
  const idx = slots.indexOf(selectedMin.value)
  let next = idx + dir
  if (next >= slots.length) { next = 0;              adjustHour(1)  }
  if (next < 0)             { next = slots.length-1; adjustHour(-1) }
  selectedMin.value = slots[next]
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
  return `${h12}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`
})

function positionDropdown() {
  const trigger = triggerRef.value
  if (!trigger) return
  const rect = trigger.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const dropH = 160 // approx dropdown height
  const top = spaceBelow >= dropH ? rect.bottom + 4 : rect.top - dropH - 4
  dropdownStyle.value = {
    position: 'fixed',
    top:  `${top}px`,
    left: `${rect.left}px`,
    width: `${Math.max(rect.width, 220)}px`,
    zIndex: '1200',
  }
}

async function toggle() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    await nextTick()
    positionDropdown()
  } else {
    emit('blur')
  }
}

function close() {
  isOpen.value = false
  emit('blur')
}

function handleTriggerKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle() }
  else if (e.key === 'Escape') { close() }
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as Node
  if (
    isOpen.value &&
    wrapperRef.value && !wrapperRef.value.contains(target) &&
    dropdownRef.value && !dropdownRef.value.contains(target)
  ) {
    triggerRef.value?.blur()
    close()
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.tvtp-wrap { display: flex; flex-direction: column; gap: var(--tv-space-2); position: relative; }

.tvtp__label {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text);
  display: flex; align-items: center; gap: var(--tv-space-1);
}
.tvtp__required { color: var(--tv-danger); }

/* Trigger — mirrors TVSelect exactly */
.tvtp__trigger {
  display: flex; align-items: center; gap: var(--tv-space-2); width: 100%;
  padding: var(--tv-space-2) var(--tv-space-3) var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  min-height: 42px; cursor: pointer; text-align: left;
  transition: border-color var(--tv-transition-fast), box-shadow var(--tv-transition-fast);
}
.tvtp__trigger:focus {
  outline: none; border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvtp-wrap--open .tvtp__trigger {
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}
.tvtp-wrap--error .tvtp__trigger    { border-color: var(--tv-danger); }
.tvtp-wrap--disabled .tvtp__trigger { background: var(--tv-bg-soft); opacity: 0.6; cursor: not-allowed; }

.tvtp__clock-icon { color: var(--tv-text-muted); flex-shrink: 0; }
.tvtp__value { flex: 1; }
.tvtp__value--placeholder { color: var(--tv-text-muted); }
.tvtp__chevron { color: var(--tv-text-muted); flex-shrink: 0; transition: transform 200ms ease, color 150ms ease; }
.tvtp-wrap--open .tvtp__chevron { transform: rotate(180deg); color: var(--tv-primary); }

/* Messages */
.tvtp__message { font-size: var(--tv-text-xs); line-height: var(--tv-leading-snug); }
.tvtp__message--error { color: var(--tv-danger); }
.tvtp__message--hint  { color: var(--tv-text-muted); }

/* Transition */
.tvtp-drop-enter-active { transition: opacity 150ms ease, transform 150ms ease; }
.tvtp-drop-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.tvtp-drop-enter-from { opacity: 0; transform: translateY(-6px); }
.tvtp-drop-leave-to  { opacity: 0; transform: translateY(-4px); }
</style>

<!-- Dropdown is teleported — these styles must be unscoped -->
<style>
.tvtp__dropdown {
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-primary);
  border-radius: var(--tv-radius);
  box-shadow: var(--tv-shadow-md);
  overflow: hidden;
  z-index: var(--tv-z-modal-dropdown);
}

.tvtp__spinners {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-1);
  padding: var(--tv-space-3) var(--tv-space-3) var(--tv-space-2);
}

/* Individual spinner column */
.tvtp__spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  flex: 1;
}

.tvtp__arrow {
  display: flex; align-items: center; justify-content: center;
  width: 100%; height: 28px;
  border: none; background: transparent;
  color: var(--tv-text-muted); cursor: pointer; border-radius: var(--tv-radius-sm);
  transition: background 0.12s, color 0.12s;
}
.tvtp__arrow:hover { background: var(--tv-primary-soft); color: var(--tv-primary); }
.tvtp__arrow:active { background: var(--tv-primary); color: var(--tv-text-inverse); }

.tvtp__digit {
  display: flex; align-items: center; justify-content: center;
  width: 100%; height: 40px;
  font-size: var(--tv-text-xl, 1.25rem);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-primary);
  background: var(--tv-primary-soft);
  border-radius: var(--tv-radius-sm);
  letter-spacing: 0.02em;
  user-select: none;
}

.tvtp__colon {
  font-size: var(--tv-text-xl, 1.25rem);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  line-height: 1;
  margin-bottom: 4px; /* visual alignment with digit */
  flex-shrink: 0;
  user-select: none;
}

/* AM / PM column */
.tvtp__ampm-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 0 0 auto;
  margin-left: var(--tv-space-1);
}
.tvtp__ampm-btn {
  padding: 6px 10px;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: transparent;
  color: var(--tv-text-muted);
  cursor: pointer;
  transition: all 0.12s;
  line-height: 1;
}
.tvtp__ampm-btn:hover:not(.tvtp__ampm-btn--active) { background: var(--tv-bg-soft); color: var(--tv-text); }
.tvtp__ampm-btn--active {
  background: var(--tv-primary);
  border-color: var(--tv-primary);
  color: var(--tv-text-inverse);
}

.tvtp__footer {
  display: flex; justify-content: flex-end;
  padding: var(--tv-space-2) var(--tv-space-3);
  border-top: 1px solid var(--tv-border);
}
.tvtp__done {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  color: var(--tv-primary); background: transparent; border: none;
  cursor: pointer; padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-sm); transition: background 0.15s;
}
.tvtp__done:hover { background: var(--tv-primary-soft); }
</style>
