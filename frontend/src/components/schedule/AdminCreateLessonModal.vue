<template>
  <Teleport to="body">
    <div class="acl-backdrop" @click.self="$emit('close')" role="dialog" aria-modal="true" aria-labelledby="acl-title">
      <div class="acl-dialog">

        <div class="acl-header">
          <h2 class="acl-title" id="acl-title">Create Lesson</h2>
          <button class="acl-close" type="button" aria-label="Close" @click="$emit('close')">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <form class="acl-form" @submit.prevent="submit">

          <!-- Date + Time -->
          <div class="acl-row">
            <div class="acl-field">
              <label class="acl-label" for="acl-date">Date</label>
              <input id="acl-date" v-model="form.date" type="date" class="acl-input" required />
            </div>
            <div class="acl-field">
              <label class="acl-label" for="acl-start">Start Time</label>
              <input id="acl-start" v-model="form.startTime" type="time" class="acl-input" step="1800" required />
            </div>
            <div class="acl-field">
              <label class="acl-label" for="acl-end">End Time</label>
              <input id="acl-end" v-model="form.endTime" type="time" class="acl-input" step="1800" required />
            </div>
          </div>

          <!-- Teacher -->
          <div class="acl-field">
            <label class="acl-label" for="acl-teacher">Teacher</label>
            <select id="acl-teacher" v-model="form.teacherId" class="acl-select" required>
              <option value="">Select teacher…</option>
              <option v-for="t in TEACHERS" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>

          <!-- Student -->
          <div class="acl-field">
            <label class="acl-label" for="acl-student">Student</label>
            <select id="acl-student" v-model="form.studentId" class="acl-select" required>
              <option value="">Select student…</option>
              <option v-for="s in STUDENTS" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <!-- Subject -->
          <div class="acl-field">
            <label class="acl-label" for="acl-subject">Subject / Topic</label>
            <input id="acl-subject" v-model="form.subject" type="text" class="acl-input" placeholder="e.g. Business English" required />
          </div>

          <!-- Lesson type + Recurring -->
          <div class="acl-toggles">
            <div class="acl-type-toggle">
              <button
                :class="['acl-type-btn', { 'acl-type-btn--active': !form.isTrial }]"
                type="button"
                @click="form.isTrial = false"
              >Regular <span class="acl-type-dur">60 min</span></button>
              <button
                :class="['acl-type-btn', { 'acl-type-btn--active': form.isTrial }]"
                type="button"
                @click="form.isTrial = true"
              >Trial <span class="acl-type-dur">30 min</span></button>
            </div>

            <label class="acl-check">
              <input v-model="form.isRecurring" type="checkbox" class="acl-check__input" />
              <span class="acl-check__box"></span>
              <span class="acl-check__label">Recurring lesson <span class="acl-check__note">↻ weekly</span></span>
            </label>
          </div>

          <p v-if="endBeforeStart" class="acl-error">End time must be after start time.</p>

          <div class="acl-footer">
            <button class="acl-btn acl-btn--ghost" type="button" @click="$emit('close')">Cancel</button>
            <button class="acl-btn acl-btn--primary" type="submit" :disabled="endBeforeStart">
              Create Lesson
            </button>
          </div>

        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { reactive, computed } from 'vue'

const props = defineProps<{
  prefillDate?: string
  prefillStart?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'created', payload: {
    teacherId: string; teacherName: string
    studentId: string; studentName: string
    date: string; startTime: string; endTime: string
    subject: string; isTrial: boolean; isRecurring: boolean
  }): void
}>()

const TEACHERS = [
  { id: 'u2', name: 'James Reyes' },
  { id: 'u6', name: 'Sarah Lim' },
  { id: 'u7', name: 'Miguel Santos' },
]

const STUDENTS = [
  { id: 'u3', name: 'Ana Cruz' },
  { id: 'u4', name: 'Ben Torres' },
  { id: 'u5', name: 'Carlos Diaz' },
]

const form = reactive({
  date:        props.prefillDate  ?? new Date().toLocaleDateString('sv-SE'),
  startTime:   props.prefillStart ?? '09:00',
  endTime:     props.prefillStart ? `${String(Number(props.prefillStart.slice(0, 2)) + 1).padStart(2, '0')}:00` : '10:00',
  teacherId:   '',
  studentId:   '',
  subject:     '',
  isTrial:     false,
  isRecurring: false,
})

const endBeforeStart = computed(() => !!form.startTime && !!form.endTime && form.endTime <= form.startTime)

function submit(): void {
  if (endBeforeStart.value || !form.teacherId || !form.studentId) return
  const teacher = TEACHERS.find(t => t.id === form.teacherId)!
  const student = STUDENTS.find(s => s.id === form.studentId)!
  emit('created', {
    teacherId:   form.teacherId,
    teacherName: teacher.name,
    studentId:   form.studentId,
    studentName: student.name,
    date:        form.date,
    startTime:   form.startTime,
    endTime:     form.endTime,
    subject:     form.subject,
    isTrial:     form.isTrial,
    isRecurring: form.isRecurring,
  })
}
</script>

<style scoped>
.acl-backdrop {
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

.acl-dialog {
  background: var(--tv-bg-card);
  border-radius: var(--tv-radius-lg);
  box-shadow: var(--tv-shadow-lg);
  width: 100%;
  max-width: 520px;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  padding: var(--tv-space-6);
  max-height: 90vh;
  overflow-y: auto;
}

.acl-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.acl-title {
  font-size: var(--tv-text-lg);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.acl-close {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: transparent;
  color: var(--tv-text-secondary);
  cursor: pointer;
  transition: background 0.15s;
}
.acl-close:hover { background: var(--tv-bg-soft); }

.acl-form {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

.acl-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: var(--tv-space-3);
}

.acl-field {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-1);
}

.acl-label {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
}

.acl-input,
.acl-select {
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
  width: 100%;
}
.acl-input:focus,
.acl-select:focus { border-color: var(--tv-primary); }

.acl-toggles {
  display: flex;
  align-items: center;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}

.acl-type-toggle {
  display: flex;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  overflow: hidden;
  flex-shrink: 0;
}

.acl-type-btn {
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  background: transparent;
  color: var(--tv-text-secondary);
  border: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
}
.acl-type-btn--active {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
}
.acl-type-dur {
  font-size: var(--tv-text-xs);
  opacity: 0.75;
}

.acl-check {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  cursor: pointer;
  user-select: none;
}
.acl-check__input { display: none; }
.acl-check__box {
  width: 16px; height: 16px;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-xs, 3px);
  background: var(--tv-bg-card);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s, border-color 0.15s;
}
.acl-check__input:checked + .acl-check__box {
  background: var(--tv-primary);
  border-color: var(--tv-primary);
}
.acl-check__input:checked + .acl-check__box::after {
  content: '';
  display: block;
  width: 8px; height: 5px;
  border-left: 1.5px solid white;
  border-bottom: 1.5px solid white;
  transform: rotate(-45deg) translate(0, -1px);
}
.acl-check__label { font-size: var(--tv-text-sm); color: var(--tv-text); }
.acl-check__note { color: var(--tv-text-muted); font-size: var(--tv-text-xs); margin-left: 2px; }

.acl-error {
  font-size: var(--tv-text-sm);
  color: var(--tv-danger-fg);
  margin: 0;
}

.acl-footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--tv-space-2);
}

.acl-btn {
  display: inline-flex;
  align-items: center;
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  border-radius: var(--tv-radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}
.acl-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.acl-btn--primary {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
}
.acl-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.acl-btn--ghost {
  background: transparent;
  border-color: var(--tv-border);
  color: var(--tv-text-secondary);
}
.acl-btn--ghost:hover { background: var(--tv-bg-soft); }

@media (max-width: 480px) {
  .acl-row { grid-template-columns: 1fr; }
  .acl-toggles { flex-direction: column; align-items: flex-start; }
}
</style>
