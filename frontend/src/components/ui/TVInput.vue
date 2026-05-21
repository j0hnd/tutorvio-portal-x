<template>
  <div :class="['tv-input-wrapper', { 'tv-input-wrapper--error': !!error, 'tv-input-wrapper--disabled': disabled }]">
    <label v-if="label" :for="inputId" class="tv-input__label">
      {{ label }}
      <span v-if="required" class="tv-input__required" aria-hidden="true">*</span>
    </label>

    <div class="tv-input__field-wrap">
      <span v-if="$slots['icon-start']" class="tv-input__icon tv-input__icon--start" aria-hidden="true">
        <slot name="icon-start" />
      </span>

      <!-- Password input (hidden when showing as text) -->
      <input
        v-if="isPassword"
        v-show="!showPassword"
        :id="inputId"
        v-bind="$attrs"
        :class="[
          'tv-input__field',
          { 'tv-input__field--icon-start': $slots['icon-start'] },
          'tv-input__field--icon-end',
        ]"
        type="password"
        :value="localValue"
        :disabled="disabled"
        :required="required"
        :placeholder="placeholder"
        :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
        :aria-invalid="!!error"
        autocomplete="current-password"
        @input="handleInput"
        @blur="emit('blur', $event)"
        @focus="emit('focus', $event)"
      />
      <!-- Text input for revealed password -->
      <input
        v-if="isPassword"
        v-show="showPassword"
        :class="[
          'tv-input__field',
          { 'tv-input__field--icon-start': $slots['icon-start'] },
          'tv-input__field--icon-end',
        ]"
        type="text"
        :value="localValue"
        :disabled="disabled"
        :placeholder="placeholder"
        :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
        :aria-invalid="!!error"
        autocomplete="off"
        @input="handleInput"
        @blur="emit('blur', $event)"
        @focus="emit('focus', $event)"
      />
      <!-- Non-password input -->
      <input
        v-if="!isPassword"
        :id="inputId"
        v-bind="$attrs"
        :class="[
          'tv-input__field',
          { 'tv-input__field--icon-start': $slots['icon-start'] },
          { 'tv-input__field--icon-end': $slots['icon-end'] },
        ]"
        :type="type"
        :value="localValue"
        :disabled="disabled"
        :required="required"
        :placeholder="placeholder"
        :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
        :aria-invalid="!!error"
        @input="handleInput"
        @blur="emit('blur', $event)"
        @focus="emit('focus', $event)"
      />

      <!-- Password eye toggle -->
      <button
        v-if="isPassword"
        type="button"
        class="tv-input__eye-btn"
        :aria-label="showPassword ? 'Hide password' : 'Show password'"
        @click="showPassword = !showPassword"
      >
        <!-- Eye open (show) -->
        <svg v-if="!showPassword" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
          <path d="M1 9s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
          <circle cx="9" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/>
        </svg>
        <!-- Eye closed (hide) -->
        <svg v-else width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
          <path d="M1 1l16 16M7.4 7.5A2.5 2.5 0 0 0 11.5 11M4.2 4.3C2.3 5.6 1 9 1 9s3 6 8 6c1.7 0 3.2-.6 4.5-1.5M7 3.2A7.6 7.6 0 0 1 9 3c5 0 8 6 8 6a14 14 0 0 1-1.9 2.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <span v-else-if="$slots['icon-end']" class="tv-input__icon tv-input__icon--end" aria-hidden="true">
        <slot name="icon-end" />
      </span>
    </div>

    <p v-if="error" :id="`${inputId}-error`" class="tv-input__message tv-input__message--error" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${inputId}-hint`" class="tv-input__message tv-input__message--hint">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'

interface Props {
  modelValue?: string | number
  label?: string
  type?: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
  error?: string
  hint?: string
  id?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
  required: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: [event: FocusEvent]
  focus: [event: FocusEvent]
}>()

const inputId = computed(() => props.id ?? `tv-input-${Math.random().toString(36).slice(2, 9)}`)

const isPassword = computed(() => props.type === 'password')
const showPassword = ref(false)

const localValue = ref(String(props.modelValue ?? ''))

watch(() => props.modelValue, (val) => {
  const str = String(val ?? '')
  if (str !== localValue.value) localValue.value = str
})

function handleInput(e: Event) {
  const val = (e.target as HTMLInputElement).value
  localValue.value = val
  emit('update:modelValue', val)
}
</script>

<style scoped>
.tv-input-wrapper { display: flex; flex-direction: column; gap: var(--tv-space-2); }

.tv-input__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
}

.tv-input__required { color: var(--tv-danger); font-size: var(--tv-text-sm); }

.tv-input__field-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.tv-input__field {
  width: 100%;
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius);
  min-height: 42px;
  transition:
    border-color var(--tv-transition-fast),
    box-shadow var(--tv-transition-fast);
}

.tv-input__field::placeholder { color: var(--tv-text-muted); }

.tv-input__field:focus {
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.15);
  outline: none;
}

.tv-input__field--icon-start { padding-left: 2.5rem; }
.tv-input__field--icon-end   { padding-right: 2.75rem; }

.tv-input__icon {
  position: absolute;
  display: flex;
  align-items: center;
  color: var(--tv-text-muted);
  pointer-events: none;
}
.tv-input__icon--start { left: var(--tv-space-3); }
.tv-input__icon--end   { right: var(--tv-space-3); }

/* Eye toggle button */
.tv-input__eye-btn {
  position: absolute;
  right: var(--tv-space-3);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  transition: color var(--tv-transition-fast), background-color var(--tv-transition-fast);
  cursor: pointer;
}

.tv-input__eye-btn:hover {
  color: var(--tv-text);
  background: var(--tv-bg-soft);
}

/* States */
.tv-input-wrapper--error .tv-input__field {
  border-color: var(--tv-danger);
}
.tv-input-wrapper--error .tv-input__field:focus {
  box-shadow: 0 0 0 3px hsla(0, 72%, 51%, 0.15);
}

.tv-input-wrapper--disabled .tv-input__field {
  background: var(--tv-bg-soft);
  cursor: not-allowed;
  opacity: 0.6;
}

.tv-input__message { font-size: var(--tv-text-xs); line-height: var(--tv-leading-snug); }
.tv-input__message--error { color: var(--tv-danger); }
.tv-input__message--hint  { color: var(--tv-text-muted); }
</style>
