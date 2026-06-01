<template>
  <div
    :class="['tv-select-wrapper', { 'tv-select-wrapper--error': !!error, 'tv-select-wrapper--disabled': disabled, 'tv-select-wrapper--open': isOpen }]"
    ref="wrapperRef"
  >
    <label v-if="label" :id="`${selectId}-label`" class="tv-select__label">
      {{ label }}
      <span v-if="required" class="tv-select__required" aria-hidden="true">*</span>
    </label>

    <!-- Trigger -->
    <button
      ref="triggerRef"
      :id="selectId"
      type="button"
      class="tv-select__trigger"
      role="combobox"
      :aria-expanded="isOpen"
      :aria-haspopup="'listbox'"
      :aria-labelledby="`${selectId}-label ${selectId}`"
      :aria-describedby="error ? `${selectId}-error` : hint ? `${selectId}-hint` : undefined"
      :aria-invalid="!!error"
      :disabled="disabled"
      @click="toggleDropdown"
      @keydown="handleKeydown"
    >
      <span :class="['tv-select__value', { 'tv-select__value--placeholder': !selectedOption }]">
        {{ selectedOption ? selectedOption.label : placeholder || 'Select an option' }}
      </span>
      <svg
        class="tv-select__chevron"
        width="16" height="16" viewBox="0 0 16 16" fill="none"
        aria-hidden="true"
      >
        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <!-- Dropdown — teleported to body to escape stacking context / overflow clipping -->
    <Teleport to="body">
      <Transition name="tv-select-drop">
        <ul
          v-if="isOpen"
          ref="dropdownRef"
          class="tv-select__dropdown"
          :style="dropdownStyle"
          role="listbox"
          :aria-labelledby="`${selectId}-label`"
          :aria-activedescendant="highlightedIndex >= 0 ? `${selectId}-opt-${highlightedIndex}` : undefined"
        >
          <li
            v-for="(option, index) in options"
            :key="option.value"
            :id="`${selectId}-opt-${index}`"
            role="option"
            :aria-selected="modelValue === option.value"
            :aria-disabled="option.disabled"
            :class="[
              'tv-select__option',
              {
                'tv-select__option--selected':   modelValue === option.value,
                'tv-select__option--highlighted': highlightedIndex === index,
                'tv-select__option--disabled':    option.disabled,
              }
            ]"
            @mouseenter="!option.disabled && (highlightedIndex = index)"
            @mousedown.prevent
            @click="!option.disabled && selectOption(option)"
          >
            <span class="tv-select__option-label">{{ option.label }}</span>
            <svg
              v-if="modelValue === option.value"
              class="tv-select__option-check"
              width="14" height="14" viewBox="0 0 14 14" fill="none"
              aria-hidden="true"
            >
              <path d="M2.5 7l3.5 3.5 6-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </li>
          <li v-if="options.length === 0" class="tv-select__empty" role="option" aria-disabled="true">
            No options available
          </li>
        </ul>
      </Transition>
    </Teleport>

    <p v-if="error" :id="`${selectId}-error`" class="tv-select__message tv-select__message--error" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${selectId}-hint`" class="tv-select__message tv-select__message--hint">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import type { SelectOption } from '@/types'

interface Props {
  modelValue?: string | number
  options: SelectOption[]
  label?: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
  error?: string
  hint?: string
  id?: string
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  required: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
  blur: [event: FocusEvent]
}>()

const selectId = computed(() => props.id ?? `tv-select-${Math.random().toString(36).slice(2, 9)}`)
const isOpen = ref(false)
const highlightedIndex = ref(-1)
const wrapperRef  = ref<HTMLElement | null>(null)
const triggerRef  = ref<HTMLButtonElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

const selectedOption = computed(() =>
  props.options.find(o => o.value === props.modelValue) ?? null
)

function positionDropdown() {
  const trigger = triggerRef.value
  if (!trigger) return
  const rect = trigger.getBoundingClientRect()
  const dropW = Math.max(rect.width, 160)
  const dropH = Math.min(props.options.length * 38 + 10, 240)
  const spaceBelow = window.innerHeight - rect.bottom
  const top = spaceBelow >= dropH ? rect.bottom + 4 : rect.top - dropH - 4
  const left = Math.min(rect.left, window.innerWidth - dropW - 8)
  dropdownStyle.value = {
    position: 'fixed',
    top: `${top}px`,
    left: `${left}px`,
    width: `${dropW}px`,
    zIndex: '1200',
  }
}

async function toggleDropdown() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    const idx = props.options.findIndex(o => o.value === props.modelValue)
    highlightedIndex.value = idx >= 0 ? idx : 0
    await nextTick()
    positionDropdown()
  } else {
    triggerRef.value?.blur()
  }
}

function selectOption(option: SelectOption) {
  emit('update:modelValue', option.value)
  isOpen.value = false
  highlightedIndex.value = -1
  triggerRef.value?.blur()
}

function handleKeydown(e: KeyboardEvent) {
  const enabledIndexes = props.options
    .map((o, i) => (!o.disabled ? i : -1))
    .filter(i => i >= 0)

  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    if (!isOpen.value) { toggleDropdown(); return }
    if (highlightedIndex.value >= 0 && !props.options[highlightedIndex.value]?.disabled) {
      selectOption(props.options[highlightedIndex.value])
    }
  } else if (e.key === 'Escape') {
    isOpen.value = false
    triggerRef.value?.blur()
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (!isOpen.value) { toggleDropdown(); return }
    const cur = enabledIndexes.indexOf(highlightedIndex.value)
    highlightedIndex.value = enabledIndexes[Math.min(cur + 1, enabledIndexes.length - 1)]
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    const cur = enabledIndexes.indexOf(highlightedIndex.value)
    highlightedIndex.value = enabledIndexes[Math.max(cur - 1, 0)]
  } else if (e.key === 'Tab') {
    isOpen.value = false
  }
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
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.tv-select-wrapper { display: flex; flex-direction: column; gap: var(--tv-space-2); position: relative; }

.tv-select__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
}

.tv-select__required { color: var(--tv-danger); }

.tv-select__trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
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

.tv-select__trigger:focus {
  outline: none;
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}

.tv-select-wrapper--open .tv-select__trigger {
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
}

.tv-select-wrapper--error .tv-select__trigger { border-color: var(--tv-danger); }

.tv-select-wrapper--disabled .tv-select__trigger {
  background: var(--tv-bg-soft);
  opacity: 0.6;
  cursor: not-allowed;
}

.tv-select__value { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tv-select__value--placeholder { color: var(--tv-text-muted); }

.tv-select__chevron {
  color: var(--tv-text-muted);
  flex-shrink: 0;
  transition: transform 200ms ease, color 150ms ease;
}

.tv-select-wrapper--open .tv-select__chevron {
  transform: rotate(180deg);
  color: var(--tv-primary);
}

.tv-select__message { font-size: var(--tv-text-xs); line-height: var(--tv-leading-snug); }
.tv-select__message--error { color: var(--tv-danger); }
.tv-select__message--hint  { color: var(--tv-text-muted); }
</style>

<!-- Dropdown is teleported to body — must be unscoped -->
<style>
.tv-select__dropdown {
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-primary);
  border-radius: var(--tv-radius);
  box-shadow: var(--tv-shadow-md);
  list-style: none;
  max-height: 240px;
  overflow-y: auto;
  padding: var(--tv-space-1) 0;
}

.tv-select__dropdown::-webkit-scrollbar { width: 4px; }
.tv-select__dropdown::-webkit-scrollbar-thumb { background: var(--tv-border); border-radius: 9999px; }

.tv-select__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  cursor: pointer;
  transition: background-color 120ms ease, color 120ms ease;
  min-height: 38px;
  gap: var(--tv-space-2);
}

.tv-select__option:hover,
.tv-select__option--highlighted {
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
}

.tv-select__option--selected {
  font-weight: 600;
  color: var(--tv-primary);
}

.tv-select__option--selected:not(.tv-select__option--highlighted) {
  background: hsl(var(--tv-primary-h), 70%, 97%);
}

.tv-select__option--disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.tv-select__option-label { flex: 1; }
.tv-select__option-check { color: var(--tv-primary); flex-shrink: 0; }

.tv-select__empty {
  padding: var(--tv-space-3) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  text-align: center;
}

/* Transition */
.tv-select-drop-enter-active { transition: opacity 150ms ease, transform 150ms ease; }
.tv-select-drop-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.tv-select-drop-enter-from   { opacity: 0; transform: translateY(-6px); }
.tv-select-drop-leave-to     { opacity: 0; transform: translateY(-4px); }
</style>
