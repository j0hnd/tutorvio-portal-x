<template>
  <Teleport to="body">
    <div class="bm-backdrop" @click.self="$emit('close')" role="dialog" aria-modal="true" aria-labelledby="bm-title">
      <div class="bm-dialog">

        <!-- Header -->
        <div class="bm-header">
          <h2 class="bm-title" id="bm-title">Book a Lesson</h2>
          <button class="bm-close" type="button" aria-label="Close" @click="$emit('close')">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <!-- Slot info -->
        <div class="bm-slot-info">
          <div class="bm-slot-row">
            <span class="bm-slot-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
            </span>
            <span>{{ slotDate }}</span>
          </div>
          <div class="bm-slot-row">
            <span class="bm-slot-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M7 4.5V7l2 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
            </span>
            <span>{{ slot.startTime }} – {{ isTrial ? trialEndTime : slot.endTime }}</span>
            <span class="bm-slot-dur">({{ isTrial ? '30 min' : '60 min' }})</span>
          </div>
          <div class="bm-slot-row">
            <span class="bm-slot-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="5" r="2.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M1.5 12.5c0-2.8 2.5-4.5 5.5-4.5s5.5 1.7 5.5 4.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
            </span>
            <span>{{ slot.teacherName }}</span>
          </div>
        </div>

        <!-- Lesson type toggle -->
        <div class="bm-field">
          <label class="bm-label">Lesson Type</label>
          <div class="bm-type-toggle">
            <button
              :class="['bm-type-btn', { 'bm-type-btn--active': !isTrial }]"
              type="button"
              @click="isTrial = false"
            >
              Regular <span class="bm-type-dur">60 min</span>
            </button>
            <button
              :class="['bm-type-btn', { 'bm-type-btn--active': isTrial }]"
              type="button"
              @click="isTrial = true"
            >
              Trial <span class="bm-type-dur">30 min</span>
            </button>
          </div>
          <p v-if="isTrial" class="bm-trial-note">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" style="flex-shrink:0">
              <circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.2"/>
              <path d="M6 5v3M6 3.5h.01" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
            The teacher's full 1-hour slot will be blocked for this trial booking.
          </p>
        </div>

        <!-- Subject input -->
        <div class="bm-field">
          <label class="bm-label" for="bm-subject">Subject / Topic</label>
          <input
            id="bm-subject"
            v-model="subject"
            class="bm-input"
            type="text"
            placeholder="e.g. Business English, IELTS Prep, Conversational…"
            autocomplete="off"
          />
          <p v-if="subjectError" class="bm-field-error">{{ subjectError }}</p>
        </div>

        <!-- Notes -->
        <div class="bm-field">
          <label class="bm-label" for="bm-notes">Notes <span class="bm-optional">(optional)</span></label>
          <textarea
            id="bm-notes"
            v-model="notes"
            class="bm-textarea"
            rows="2"
            placeholder="Any specific topics or goals for this lesson?"
          />
        </div>

        <!-- Actions -->
        <div class="bm-actions">
          <button class="bm-btn bm-btn--secondary" type="button" @click="$emit('close')">Cancel</button>
          <button class="bm-btn bm-btn--primary" type="button" :disabled="!subject.trim()" @click="confirm">
            Confirm Booking
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { AvailabilitySlot } from '@/stores/schedule'

const props = defineProps<{
  slot: AvailabilitySlot
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'booked', slotId: string, subject: string, isTrial: boolean): void
}>()

const isTrial = ref(false)
const subject = ref('')
const notes   = ref('')
const subjectError = ref('')

const slotDate = computed(() => {
  const d = new Date(`${props.slot.date}T12:00:00`)
  return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
})

const trialEndTime = computed(() => {
  const [h, m] = props.slot.startTime.split(':').map(Number)
  const endMin = h * 60 + m + 30
  const eh = Math.floor(endMin / 60)
  const em = endMin % 60
  return `${String(eh).padStart(2, '0')}:${String(em).padStart(2, '0')}`
})

function confirm(): void {
  if (!subject.value.trim()) {
    subjectError.value = 'Please enter a subject or topic.'
    return
  }
  subjectError.value = ''
  emit('booked', props.slot.id, subject.value.trim(), isTrial.value)
}
</script>

<style scoped>
.bm-backdrop {
  position: fixed;
  inset: 0;
  background: hsla(215, 25%, 10%, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: var(--tv-space-4);
  backdrop-filter: blur(2px);
}

.bm-dialog {
  background: var(--tv-bg-card);
  border-radius: var(--tv-radius-lg);
  box-shadow: var(--tv-shadow-lg);
  width: 100%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  padding: var(--tv-space-6);
}

.bm-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.bm-title {
  font-size: var(--tv-text-lg);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.bm-close {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: transparent;
  color: var(--tv-text-secondary);
  cursor: pointer;
  transition: background 0.15s;
}
.bm-close:hover { background: var(--tv-bg-soft); }

.bm-slot-info {
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  padding: var(--tv-space-3) var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.bm-slot-row {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
}

.bm-slot-icon { color: var(--tv-text-muted); display: flex; }
.bm-slot-dur  { color: var(--tv-text-muted); font-size: var(--tv-text-xs); }

.bm-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }

.bm-label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.bm-optional { color: var(--tv-text-muted); font-weight: var(--tv-font-normal); }

.bm-type-toggle {
  display: flex;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  overflow: hidden;
}

.bm-type-btn {
  flex: 1;
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  background: var(--tv-bg-card);
  color: var(--tv-text-secondary);
  border: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-2);
}
.bm-type-btn + .bm-type-btn { border-left: 1px solid var(--tv-border); }
.bm-type-btn--active { background: var(--tv-primary); color: var(--tv-text-inverse); }
.bm-type-dur { font-size: var(--tv-text-xs); opacity: 0.8; }

.bm-trial-note {
  display: flex;
  align-items: flex-start;
  gap: var(--tv-space-1);
  font-size: var(--tv-text-xs);
  color: var(--tv-warning-fg);
  background: var(--tv-warning-soft);
  border: 1px solid var(--tv-warning-border);
  border-radius: var(--tv-radius-sm);
  padding: var(--tv-space-2) var(--tv-space-3);
  margin: 0;
}

.bm-input, .bm-textarea {
  width: 100%;
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
  font-family: inherit;
  resize: vertical;
}
.bm-input:focus, .bm-textarea:focus { border-color: var(--tv-primary); }

.bm-field-error { font-size: var(--tv-text-xs); color: var(--tv-danger-fg); margin: 0; }

.bm-actions {
  display: flex;
  gap: var(--tv-space-2);
  justify-content: flex-end;
}

.bm-btn {
  padding: var(--tv-space-2) var(--tv-space-5);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  border-radius: var(--tv-radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}
.bm-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.bm-btn--secondary {
  background: var(--tv-bg-soft);
  border-color: var(--tv-border);
  color: var(--tv-text-secondary);
}
.bm-btn--secondary:hover:not(:disabled) { background: var(--tv-bg); }
.bm-btn--primary {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
}
.bm-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
</style>
