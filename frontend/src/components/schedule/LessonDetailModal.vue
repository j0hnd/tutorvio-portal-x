<template>
  <Teleport to="body">
    <div class="ldm-backdrop" @click.self="$emit('close')" role="dialog" aria-modal="true" aria-labelledby="ldm-title">
      <div class="ldm-dialog">

        <!-- Header -->
        <div class="ldm-header">
          <div class="ldm-header__left">
            <h2 class="ldm-title" id="ldm-title">{{ lesson.title }}</h2>
            <span :class="['ldm-status', `ldm-status--${statusClass}`]">{{ statusLabel }}</span>
            <span v-if="lesson.isTrial" class="ldm-trial-badge">TRIAL</span>
            <span v-if="lesson.isRecurring" class="ldm-recur-badge" title="Recurring lesson">↻ Recurring</span>
          </div>
          <button class="ldm-close" type="button" aria-label="Close" @click="$emit('close')">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <!-- Details -->
        <div class="ldm-details">
          <div class="ldm-detail-row">
            <span class="ldm-detail-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
            </span>
            <span class="ldm-detail-label">Date</span>
            <span class="ldm-detail-value">{{ formattedDate }}</span>
          </div>
          <div class="ldm-detail-row">
            <span class="ldm-detail-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M7 4.5V7l2 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
            </span>
            <span class="ldm-detail-label">Time</span>
            <span class="ldm-detail-value">{{ formattedStart }} – {{ formattedEnd }} ({{ duration }} min)</span>
          </div>
          <div class="ldm-detail-row">
            <span class="ldm-detail-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="5" r="2.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M1.5 12.5c0-2.8 2.5-4.5 5.5-4.5s5.5 1.7 5.5 4.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
            </span>
            <span class="ldm-detail-label">Teacher</span>
            <span class="ldm-detail-value">{{ lesson.teacherName }}</span>
          </div>
          <div class="ldm-detail-row">
            <span class="ldm-detail-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="5" r="2.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M1.5 12.5c0-2.8 2.5-4.5 5.5-4.5s5.5 1.7 5.5 4.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
            </span>
            <span class="ldm-detail-label">Student</span>
            <span class="ldm-detail-value">{{ lesson.studentName }}</span>
          </div>
          <div class="ldm-detail-row">
            <span class="ldm-detail-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M2 3h10a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H8l-3 2V10H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2"/>
              </svg>
            </span>
            <span class="ldm-detail-label">Subject</span>
            <span class="ldm-detail-value">{{ lesson.subject }}</span>
          </div>
          <div v-if="lesson.notes" class="ldm-notes">
            <span class="ldm-notes-label">Notes</span>
            <p class="ldm-notes-text">{{ lesson.notes }}</p>
          </div>
        </div>

        <!-- Reschedule form (inline) -->
        <div v-if="showRescheduleForm" class="ldm-reschedule-form">
          <h3 class="ldm-reschedule-title">Reschedule Lesson</h3>
          <div class="ldm-reschedule-fields">
            <div class="ldm-field">
              <label class="ldm-label" for="ldm-new-date">New Date</label>
              <input id="ldm-new-date" v-model="newDate" type="date" class="ldm-input" :min="minDate" />
            </div>
            <div class="ldm-field">
              <label class="ldm-label" for="ldm-new-time">New Start Time</label>
              <input id="ldm-new-time" v-model="newTime" type="time" class="ldm-input" step="1800" />
            </div>
          </div>
          <div class="ldm-reschedule-actions">
            <button class="ldm-btn ldm-btn--ghost" type="button" @click="showRescheduleForm = false">Cancel</button>
            <button class="ldm-btn ldm-btn--primary" type="button" :disabled="!newDate || !newTime" @click="submitReschedule">
              Confirm Reschedule
            </button>
          </div>
        </div>

        <!-- Cancel confirm -->
        <div v-if="showCancelConfirm" class="ldm-cancel-confirm">
          <p class="ldm-cancel-msg">Are you sure you want to cancel this lesson? This cannot be undone.</p>
          <div class="ldm-cancel-actions">
            <button class="ldm-btn ldm-btn--ghost" type="button" @click="showCancelConfirm = false">No, keep it</button>
            <button class="ldm-btn ldm-btn--danger" type="button" @click="confirmCancel">Yes, cancel lesson</button>
          </div>
        </div>

        <!-- Footer actions -->
        <div class="ldm-footer">
          <button
            v-if="lesson.meetingUrl && canJoin"
            class="ldm-btn ldm-btn--primary ldm-btn--icon"
            type="button"
            @click="joinMeeting"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x="1" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
              <path d="M9 5.5l4-2v7l-4-2V5.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
            </svg>
            Join Meeting
          </button>
          <button
            v-if="lesson.canReschedule && !showRescheduleForm && !showCancelConfirm"
            class="ldm-btn ldm-btn--secondary ldm-btn--icon"
            type="button"
            @click="showRescheduleForm = true"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M1 7A6 6 0 1 0 7 1" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
              <path d="M1 1v6h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Reschedule
          </button>
          <button
            v-if="lesson.canCancel && !showCancelConfirm && !showRescheduleForm"
            class="ldm-btn ldm-btn--ghost-danger ldm-btn--icon"
            type="button"
            @click="showCancelConfirm = true"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
              <path d="M5 5l4 4M9 5l-4 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
            Cancel Lesson
          </button>
          <button class="ldm-btn ldm-btn--ghost ldm-btn--ml-auto" type="button" @click="$emit('close')">Close</button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ScheduleLesson } from '@/stores/schedule'

const props = defineProps<{
  lesson: ScheduleLesson
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'cancel', lessonId: string): void
  (e: 'reschedule', lessonId: string, newStart: string, newEnd: string): void
}>()

const showRescheduleForm  = ref(false)
const showCancelConfirm   = ref(false)
const newDate             = ref('')
const newTime             = ref('')

const minDate = computed(() => new Date().toLocaleDateString('sv-SE'))

const statusClass = computed(() => {
  switch (props.lesson.status) {
    case 'COMPLETED':         return 'completed'
    case 'CANCELLED':         return 'cancelled'
    case 'MISSED_BY_STUDENT':
    case 'MISSED_BY_TEACHER': return 'missed'
    case 'TRIAL':             return 'trial'
    case 'IN_PROGRESS':       return 'live'
    default:                  return 'scheduled'
  }
})

const statusLabel = computed(() => {
  switch (props.lesson.status) {
    case 'SCHEDULED':         return 'Scheduled'
    case 'IN_PROGRESS':       return 'Live'
    case 'COMPLETED':         return 'Completed'
    case 'CANCELLED':         return 'Cancelled'
    case 'MISSED_BY_STUDENT': return 'Missed by Student'
    case 'MISSED_BY_TEACHER': return 'Missed by Teacher'
    case 'TRIAL':             return 'Trial'
    default:                  return props.lesson.status
  }
})

const formattedDate = computed(() => new Date(props.lesson.startTime).toLocaleDateString('en-US', {
  weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
}))

const formattedStart = computed(() => new Date(props.lesson.startTime).toLocaleTimeString('en-US', {
  hour: 'numeric', minute: '2-digit', hour12: true,
}))

const formattedEnd = computed(() => new Date(props.lesson.endTime).toLocaleTimeString('en-US', {
  hour: 'numeric', minute: '2-digit', hour12: true,
}))

const duration = computed(() => {
  const ms = new Date(props.lesson.endTime).getTime() - new Date(props.lesson.startTime).getTime()
  return Math.round(ms / 60_000)
})

const canJoin = computed(() =>
  props.lesson.status === 'SCHEDULED' || props.lesson.status === 'IN_PROGRESS' || props.lesson.status === 'TRIAL'
)

function joinMeeting(): void {
  if (props.lesson.meetingUrl) window.open(props.lesson.meetingUrl, '_blank', 'noopener')
}

function submitReschedule(): void {
  if (!newDate.value || !newTime.value) return
  const durationMs = new Date(props.lesson.endTime).getTime() - new Date(props.lesson.startTime).getTime()
  const newStart   = new Date(`${newDate.value}T${newTime.value}:00`).toISOString()
  const newEnd     = new Date(new Date(newStart).getTime() + durationMs).toISOString()
  emit('reschedule', props.lesson.id, newStart, newEnd)
  showRescheduleForm.value = false
}

function confirmCancel(): void {
  emit('cancel', props.lesson.id)
  showCancelConfirm.value = false
}
</script>

<style scoped>
.ldm-backdrop {
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

.ldm-dialog {
  background: var(--tv-bg-card);
  border-radius: var(--tv-radius-lg);
  box-shadow: var(--tv-shadow-lg);
  width: 100%;
  max-width: 500px;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  padding: var(--tv-space-6);
  max-height: 90vh;
  overflow-y: auto;
}

.ldm-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-3);
}

.ldm-header__left {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--tv-space-2);
  flex: 1;
}

.ldm-title {
  font-size: var(--tv-text-lg);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
  line-height: 1.3;
}

.ldm-status {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
  border: 1px solid transparent;
  white-space: nowrap;
}
.ldm-status--scheduled  { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.ldm-status--completed  { background: var(--tv-success-soft);  color: var(--tv-success-fg);  border-color: var(--tv-success-border); }
.ldm-status--cancelled  { background: var(--tv-neutral-soft);  color: var(--tv-neutral);     border-color: var(--tv-neutral-border); text-decoration: line-through; }
.ldm-status--missed     { background: var(--tv-danger-soft);   color: var(--tv-danger-fg);   border-color: var(--tv-danger-border); }
.ldm-status--trial      { background: var(--tv-purple-soft);   color: var(--tv-purple);      border-color: var(--tv-purple-border); }
.ldm-status--live       { background: var(--tv-success-soft);  color: var(--tv-success-fg);  border-color: var(--tv-success-border); }

.ldm-trial-badge {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  background: var(--tv-purple-soft);
  color: var(--tv-purple);
  border: 1px solid var(--tv-purple-border);
  border-radius: var(--tv-radius-full);
  padding: 2px var(--tv-space-2);
}

.ldm-recur-badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-full);
  padding: 2px var(--tv-space-2);
}

.ldm-close {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: transparent;
  color: var(--tv-text-secondary);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s;
}
.ldm-close:hover { background: var(--tv-bg-soft); }

.ldm-details {
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  padding: var(--tv-space-3) var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.ldm-detail-row {
  display: grid;
  grid-template-columns: 20px 80px 1fr;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-sm);
}

.ldm-detail-icon { color: var(--tv-text-muted); display: flex; }
.ldm-detail-label { font-weight: var(--tv-font-medium); color: var(--tv-text-secondary); }
.ldm-detail-value { color: var(--tv-text); }

.ldm-notes { margin-top: var(--tv-space-1); }
.ldm-notes-label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.ldm-notes-text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: var(--tv-space-1) 0 0; line-height: 1.5; }

/* Reschedule form */
.ldm-reschedule-form {
  background: var(--tv-primary-soft);
  border: 1px solid var(--tv-primary-muted);
  border-radius: var(--tv-radius);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
}

.ldm-reschedule-title {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: hsl(var(--tv-primary-h), var(--tv-primary-s), 35%);
  margin: 0;
}

.ldm-reschedule-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-3);
}

.ldm-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.ldm-label {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
}

.ldm-input {
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
}
.ldm-input:focus { border-color: var(--tv-primary); }

.ldm-reschedule-actions { display: flex; gap: var(--tv-space-2); justify-content: flex-end; }

/* Cancel confirm */
.ldm-cancel-confirm {
  background: var(--tv-danger-soft);
  border: 1px solid var(--tv-danger-border);
  border-radius: var(--tv-radius);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
}

.ldm-cancel-msg { font-size: var(--tv-text-sm); color: var(--tv-danger-fg); margin: 0; }
.ldm-cancel-actions { display: flex; gap: var(--tv-space-2); justify-content: flex-end; }

/* Footer */
.ldm-footer {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  flex-wrap: wrap;
}

/* Buttons */
.ldm-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  border-radius: var(--tv-radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}
.ldm-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ldm-btn--primary {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
}
.ldm-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.ldm-btn--secondary {
  background: var(--tv-bg-soft);
  border-color: var(--tv-border);
  color: var(--tv-text-secondary);
}
.ldm-btn--secondary:hover:not(:disabled) { background: var(--tv-bg); }
.ldm-btn--ghost {
  background: transparent;
  border-color: var(--tv-border);
  color: var(--tv-text-secondary);
}
.ldm-btn--ghost:hover:not(:disabled) { background: var(--tv-bg-soft); }
.ldm-btn--ghost-danger {
  background: transparent;
  border-color: var(--tv-danger-border);
  color: var(--tv-danger-fg);
}
.ldm-btn--ghost-danger:hover:not(:disabled) { background: var(--tv-danger-soft); }
.ldm-btn--danger {
  background: var(--tv-danger);
  color: var(--tv-text-inverse);
}
.ldm-btn--danger:hover:not(:disabled) { background: hsl(0, 72%, 44%); }
.ldm-btn--ml-auto { margin-left: auto; }
.ldm-btn--icon { gap: var(--tv-space-2); }

@media (max-width: 480px) {
  .ldm-reschedule-fields { grid-template-columns: 1fr; }
  .ldm-detail-row { grid-template-columns: 20px 70px 1fr; }
}
</style>
