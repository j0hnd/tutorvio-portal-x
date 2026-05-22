<template>
  <div class="sv-page">

    <!-- Header -->
    <div class="sv-header">
      <div class="sv-header__left">
        <h1 class="sv-title">Schedule</h1>
        <span class="sv-tz-badge">{{ timezone }}</span>
      </div>
      <div class="sv-header__right">
        <!-- Teacher filter (admin/staff only) -->
        <select
          v-if="showTeacherFilter"
          v-model="filterTeacherId"
          class="sv-teacher-select"
          aria-label="Filter by teacher"
        >
          <option value="">All Teachers</option>
          <option value="u2">James Reyes</option>
        </select>

        <!-- View switcher -->
        <div class="sv-view-switcher" role="group" aria-label="Calendar view">
          <button
            v-for="v in VIEWS"
            :key="v.key"
            :class="['sv-view-btn', { 'sv-view-btn--active': currentView === v.key }]"
            type="button"
            @click="currentView = v.key"
          >{{ v.label }}</button>
        </div>

        <!-- Navigation -->
        <div class="sv-nav">
          <button class="sv-nav-btn" type="button" aria-label="Previous" @click="navigate(-1)">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M10 12L6 8l4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <button class="sv-today-btn" type="button" @click="goToday">Today</button>
          <button class="sv-nav-btn" type="button" aria-label="Next" @click="navigate(1)">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>

        <span class="sv-period-label">{{ periodLabel }}</span>
      </div>
    </div>

    <!-- Month View -->
    <div v-if="currentView === 'month'" class="sv-month">
      <div class="sv-month__weekdays">
        <span v-for="d in WEEKDAYS" :key="d" class="sv-month__wday">{{ d }}</span>
      </div>
      <div class="sv-month__grid">
        <div
          v-for="cell in monthCells"
          :key="cell.dateStr"
          :class="[
            'sv-month__cell',
            { 'sv-month__cell--other': !cell.inMonth },
            { 'sv-month__cell--today': cell.isToday },
            { 'sv-month__cell--unavailable': cell.isUnavailable },
          ]"
        >
          <span class="sv-month__day">{{ cell.day }}</span>
          <div class="sv-month__events">
            <button
              v-for="ev in cell.events.slice(0, 3)"
              :key="ev.id"
              :class="['sv-chip', `sv-chip--${statusClass(ev.status)}`, { 'sv-chip--trial': ev.isTrial }]"
              type="button"
              :title="ev.title"
              @click="openLesson(ev)"
            >
              <span class="sv-chip__time">{{ formatTime(ev.startTime) }}</span>
              <span class="sv-chip__label">{{ ev.title }}</span>
              <span v-if="ev.isTrial" class="sv-chip__trial">TRIAL</span>
              <span v-if="ev.isRecurring" class="sv-chip__recur" aria-label="Recurring">↻</span>
            </button>
            <!-- Open availability slots (students see these) -->
            <button
              v-for="slot in cell.slots.slice(0, 2)"
              :key="slot.id"
              class="sv-chip sv-chip--open-slot"
              type="button"
              :title="`Available: ${slot.startTime}–${slot.endTime}`"
              @click="openSlot(slot)"
            >
              <span class="sv-chip__time">{{ slot.startTime }}</span>
              <span class="sv-chip__label">Open slot</span>
            </button>
            <span
              v-if="cell.events.length + cell.slots.length > 3"
              class="sv-month__more"
            >+{{ cell.events.length + cell.slots.length - 3 }} more</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Week View -->
    <div v-else-if="currentView === 'week'" class="sv-week">
      <!-- Day headers -->
      <div class="sv-week__head">
        <div class="sv-week__time-gutter" />
        <div
          v-for="day in weekDays"
          :key="day.dateStr"
          :class="['sv-week__day-head', { 'sv-week__day-head--today': day.isToday }]"
        >
          <span class="sv-week__dow">{{ day.dow }}</span>
          <span :class="['sv-week__date', { 'sv-week__date--today': day.isToday }]">{{ day.date }}</span>
        </div>
      </div>
      <!-- Time grid -->
      <div class="sv-week__body" ref="weekBodyRef">
        <div class="sv-week__time-col">
          <div v-for="h in HOURS" :key="h" class="sv-week__hour-label">
            {{ formatHour(h) }}
          </div>
        </div>
        <div
          v-for="day in weekDays"
          :key="day.dateStr"
          :class="['sv-week__col', { 'sv-week__col--unavailable': day.isUnavailable }]"
        >
          <div v-for="h in HOURS" :key="h" class="sv-week__hour-cell" />
          <!-- Availability slot backgrounds -->
          <div
            v-for="slot in day.slots"
            :key="slot.id"
            :class="['sv-week__slot-bg', { 'sv-week__slot-bg--booked': slot.isBooked }]"
            :style="slotStyle(slot.startTime, slot.endTime)"
            :title="slot.isBooked ? `Booked: ${slot.startTime}–${slot.endTime}` : `Available: ${slot.startTime}–${slot.endTime}`"
            @click="!slot.isBooked && canBook ? openSlot(slot) : undefined"
          />
          <!-- Lesson events -->
          <button
            v-for="ev in day.events"
            :key="ev.id"
            :class="['sv-week__event', `sv-week__event--${statusClass(ev.status)}`, { 'sv-week__event--trial': ev.isTrial }]"
            :style="eventStyle(ev)"
            type="button"
            @click="openLesson(ev)"
          >
            <span class="sv-week__event-title">{{ ev.title }}</span>
            <span class="sv-week__event-meta">
              {{ formatTime(ev.startTime) }}–{{ formatTime(ev.endTime) }}
              <template v-if="roleLabel(ev)"> · {{ roleLabel(ev) }}</template>
            </span>
            <span v-if="ev.isTrial" class="sv-week__event-trial">TRIAL</span>
            <span v-if="ev.isRecurring" class="sv-week__event-recur" aria-label="Recurring">↻</span>
          </button>
          <!-- Current time indicator (today only) -->
          <div
            v-if="day.isToday"
            class="sv-week__now-line"
            :style="nowLineStyle"
          />
        </div>
      </div>
    </div>

    <!-- Day View -->
    <div v-else class="sv-day">
      <div class="sv-day__head">
        <span :class="['sv-day__date-label', { 'sv-day__date-label--today': isDayToday }]">
          {{ dayLabel }}
        </span>
        <span v-if="isDayToday" class="sv-day__today-chip">Today</span>
        <span v-if="dayUnavailable" class="sv-day__unavail-chip">Unavailable</span>
      </div>
      <div class="sv-day__body">
        <div class="sv-day__time-col">
          <div v-for="h in HOURS" :key="h" class="sv-day__hour-label">{{ formatHour(h) }}</div>
        </div>
        <div :class="['sv-day__col', { 'sv-day__col--unavailable': dayUnavailable }]">
          <div v-for="h in HOURS" :key="h" class="sv-day__hour-cell" />
          <!-- Availability slot backgrounds -->
          <div
            v-for="slot in currentDaySlots"
            :key="slot.id"
            :class="['sv-day__slot-bg', { 'sv-day__slot-bg--booked': slot.isBooked }]"
            :style="slotStyle(slot.startTime, slot.endTime)"
            :title="slot.isBooked ? `Booked: ${slot.startTime}–${slot.endTime}` : `Available: ${slot.startTime}–${slot.endTime}`"
            @click="!slot.isBooked && canBook ? openSlot(slot) : undefined"
          />
          <!-- Lesson events -->
          <button
            v-for="ev in currentDayEvents"
            :key="ev.id"
            :class="['sv-day__event', `sv-day__event--${statusClass(ev.status)}`, { 'sv-day__event--trial': ev.isTrial }]"
            :style="eventStyle(ev)"
            type="button"
            @click="openLesson(ev)"
          >
            <span class="sv-day__event-title">{{ ev.title }}</span>
            <span class="sv-day__event-meta">
              {{ formatTime(ev.startTime) }}–{{ formatTime(ev.endTime) }}
              <template v-if="roleLabel(ev)"> · {{ roleLabel(ev) }}</template>
            </span>
            <span v-if="ev.isTrial" class="sv-day__event-trial">TRIAL</span>
          </button>
          <div v-if="isDayToday" class="sv-day__now-line" :style="nowLineStyle" />
        </div>
      </div>
    </div>

    <!-- Modals -->
    <LessonDetailModal
      v-if="selectedLesson"
      :lesson="selectedLesson"
      @close="selectedLesson = null"
      @cancel="handleCancel"
      @reschedule="handleReschedule"
    />
    <BookingModal
      v-if="selectedSlot"
      :slot="selectedSlot"
      @close="selectedSlot = null"
      @booked="handleBooked"
    />

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watchEffect } from 'vue'
import { useScheduleStore }  from '@/stores/schedule'
import { useAuthStore }      from '@/stores/auth'
import { useViewAs }         from '@/composables/useViewAs'
import LessonDetailModal     from '@/components/schedule/LessonDetailModal.vue'
import BookingModal          from '@/components/schedule/BookingModal.vue'
import type { ScheduleLesson, AvailabilitySlot } from '@/stores/schedule'

const schedule      = useScheduleStore()
const auth          = useAuthStore()
const { effectiveRole } = useViewAs()

// ---- Config ----
const VIEWS   = [{ key: 'month', label: 'Month' }, { key: 'week', label: 'Week' }, { key: 'day', label: 'Day' }] as const
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const HOURS    = Array.from({ length: 15 }, (_, i) => i + 7)   // 7–21
const HOUR_H   = 64   // px per hour in time-grid views

// ---- State ----
type ViewKey = 'month' | 'week' | 'day'
const currentView     = ref<ViewKey>('week')
const cursor          = ref(new Date('2026-05-22'))   // today
const filterTeacherId = ref('')
const selectedLesson  = ref<ScheduleLesson | null>(null)
const selectedSlot    = ref<AvailabilitySlot | null>(null)

// ---- Derived ----
const timezone       = computed(() => auth.user?.timezone ?? 'Asia/Manila')
const showTeacherFilter = computed(() => effectiveRole.value === 'ADMIN' || effectiveRole.value === 'STAFF')
const canBook        = computed(() => effectiveRole.value === 'STUDENT')

// sync store teacher filter
watchEffect(() => {
  schedule.selectedTeacherId = filterTeacherId.value || null
})

// ---- Helpers ----
function toDateStr(d: Date): string {
  return d.toLocaleDateString('sv-SE')   // YYYY-MM-DD
}

function startOfMonth(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), 1)
}

function startOfWeek(d: Date): Date {
  const s = new Date(d)
  s.setDate(d.getDate() - d.getDay())
  return s
}

function addDays(d: Date, n: number): Date {
  const r = new Date(d)
  r.setDate(r.getDate() + n)
  return r
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
}

function formatHour(h: number): string {
  return h < 12 ? `${h} AM` : h === 12 ? '12 PM' : `${h - 12} PM`
}

function statusClass(status: string): string {
  switch (status) {
    case 'COMPLETED':         return 'completed'
    case 'CANCELLED':         return 'cancelled'
    case 'MISSED_BY_STUDENT':
    case 'MISSED_BY_TEACHER': return 'missed'
    case 'TRIAL':             return 'trial'
    case 'IN_PROGRESS':       return 'live'
    default:                  return 'scheduled'
  }
}

function isUnavailableDate(dateStr: string): boolean {
  return schedule.myUnavailableDates.some(u => u.date === dateStr)
}

function eventsForDate(dateStr: string): ScheduleLesson[] {
  return schedule.myLessons.filter(l => toDateStr(new Date(l.startTime)) === dateStr)
}

function slotsForDate(dateStr: string): AvailabilitySlot[] {
  if (!canBook.value && effectiveRole.value !== 'TEACHER' && !showTeacherFilter.value) return []
  return schedule.myAvailabilitySlots.filter(s => s.date === dateStr)
}

function roleLabel(lesson: ScheduleLesson): string {
  const role = effectiveRole.value
  if (role === 'STUDENT') return lesson.teacherName
  if (role === 'TEACHER') return lesson.studentName
  return `${lesson.teacherName} / ${lesson.studentName}`
}

// ---- Period label ----
const periodLabel = computed(() => {
  if (currentView.value === 'month') {
    return cursor.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
  }
  if (currentView.value === 'week') {
    const s = startOfWeek(cursor.value)
    const e = addDays(s, 6)
    const sm = s.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    const em = e.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    return `${sm} – ${em}`
  }
  return cursor.value.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
})

// ---- Navigation ----
function navigate(dir: 1 | -1): void {
  const d = new Date(cursor.value)
  if (currentView.value === 'month') d.setMonth(d.getMonth() + dir)
  else if (currentView.value === 'week') d.setDate(d.getDate() + dir * 7)
  else d.setDate(d.getDate() + dir)
  cursor.value = d
}

function goToday(): void {
  cursor.value = new Date('2026-05-22')
}

// ---- Month grid ----
const monthCells = computed(() => {
  const month = cursor.value.getMonth()
  const first = startOfMonth(cursor.value)
  const today = '2026-05-22'
  const cells = []
  // Start from Sunday of the first week
  const gridStart = startOfWeek(first)
  for (let i = 0; i < 42; i++) {
    const d       = addDays(gridStart, i)
    const dateStr = toDateStr(d)
    cells.push({
      day:         d.getDate(),
      dateStr,
      inMonth:     d.getMonth() === month,
      isToday:     dateStr === today,
      isUnavailable: isUnavailableDate(dateStr),
      events:      eventsForDate(dateStr),
      slots:       canBook.value ? slotsForDate(dateStr) : [],
    })
  }
  return cells
})

// ---- Week grid ----
const weekDays = computed(() => {
  const s     = startOfWeek(cursor.value)
  const today = '2026-05-22'
  return Array.from({ length: 7 }, (_, i) => {
    const d       = addDays(s, i)
    const dateStr = toDateStr(d)
    return {
      dow:         WEEKDAYS[d.getDay()],
      date:        d.getDate(),
      dateStr,
      isToday:     dateStr === today,
      isUnavailable: isUnavailableDate(dateStr),
      events:      eventsForDate(dateStr),
      slots:       slotsForDate(dateStr),
    }
  })
})

// ---- Day view ----
const currentDayStr   = computed(() => toDateStr(cursor.value))
const isDayToday      = computed(() => currentDayStr.value === '2026-05-22')
const dayUnavailable  = computed(() => isUnavailableDate(currentDayStr.value))
const currentDayEvents = computed(() => eventsForDate(currentDayStr.value))
const currentDaySlots  = computed(() => slotsForDate(currentDayStr.value))
const dayLabel         = computed(() =>
  cursor.value.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
)

// ---- Event/slot positioning ----
function timeToMinutes(timeStr: string): number {
  const [h, m] = timeStr.split(':').map(Number)
  return h * 60 + m
}

function isoToMinutes(iso: string): number {
  const d = new Date(iso)
  return d.getHours() * 60 + d.getMinutes()
}

function eventStyle(ev: ScheduleLesson): Record<string, string> {
  const startMin = isoToMinutes(ev.startTime)
  const endMin   = isoToMinutes(ev.endTime)
  const gridTop  = 7 * 60  // grid starts at 7am
  const top      = ((startMin - gridTop) / 60) * HOUR_H
  const height   = Math.max(((endMin - startMin) / 60) * HOUR_H, 24)
  return { top: `${top}px`, height: `${height}px` }
}

function slotStyle(startTime: string, endTime: string): Record<string, string> {
  const startMin = timeToMinutes(startTime)
  const endMin   = timeToMinutes(endTime)
  const gridTop  = 7 * 60
  const top      = ((startMin - gridTop) / 60) * HOUR_H
  const height   = ((endMin - startMin) / 60) * HOUR_H
  return { top: `${top}px`, height: `${height}px` }
}

// ---- Now line ----
const nowLineStyle = computed(() => {
  const now    = new Date()
  const mins   = now.getHours() * 60 + now.getMinutes()
  const gridTop = 7 * 60
  const top    = ((mins - gridTop) / 60) * HOUR_H
  return { top: `${top}px` }
})

// ---- Modal handlers ----
function openLesson(lesson: ScheduleLesson): void {
  selectedLesson.value = lesson
}

function openSlot(slot: AvailabilitySlot): void {
  if (!canBook.value) return
  selectedSlot.value = slot
}

function handleCancel(lessonId: string): void {
  schedule.cancelLesson(lessonId)
  selectedLesson.value = null
}

function handleReschedule(lessonId: string, newStart: string, newEnd: string): void {
  schedule.rescheduleLesson(lessonId, newStart, newEnd)
  selectedLesson.value = null
}

function handleBooked(slotId: string, subject: string, isTrial: boolean): void {
  const userId = auth.user?.id ?? 'u1'
  const userName = auth.user ? `${auth.user.firstName} ${auth.user.lastName}` : 'Student'
  schedule.bookSlot(slotId, userId, userName, subject, isTrial)
  selectedSlot.value = null
}
</script>

<style scoped>
/* ---- Page shell ---- */
.sv-page {
  padding: var(--tv-space-6);
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

/* ---- Header ---- */
.sv-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--tv-space-3);
}

.sv-header__left { display: flex; align-items: center; gap: var(--tv-space-3); }
.sv-header__right { display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap; }

.sv-title {
  font-size: var(--tv-text-xl);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.sv-tz-badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-full);
  padding: 2px var(--tv-space-2);
}

.sv-teacher-select {
  font-size: var(--tv-text-sm);
  padding: var(--tv-space-1) var(--tv-space-3);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  color: var(--tv-text);
  cursor: pointer;
}

.sv-view-switcher {
  display: flex;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  overflow: hidden;
}

.sv-view-btn {
  padding: var(--tv-space-1) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
  background: var(--tv-bg-card);
  border: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.sv-view-btn + .sv-view-btn { border-left: 1px solid var(--tv-border); }
.sv-view-btn--active {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
}

.sv-nav { display: flex; align-items: center; gap: var(--tv-space-1); }
.sv-nav-btn {
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  color: var(--tv-text-secondary);
  cursor: pointer;
  transition: background 0.15s;
}
.sv-nav-btn:hover { background: var(--tv-bg-soft); }

.sv-today-btn {
  padding: var(--tv-space-1) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  color: var(--tv-text-secondary);
  cursor: pointer;
  transition: background 0.15s;
}
.sv-today-btn:hover { background: var(--tv-bg-soft); }

.sv-period-label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  min-width: 180px;
}

/* ---- Month view ---- */
.sv-month {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  overflow: hidden;
}

.sv-month__weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background: var(--tv-bg-soft);
  border-bottom: 1px solid var(--tv-border);
}

.sv-month__wday {
  padding: var(--tv-space-2) 0;
  text-align: center;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.sv-month__grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}

.sv-month__cell {
  min-height: 100px;
  padding: var(--tv-space-2);
  border-right: 1px solid var(--tv-border);
  border-bottom: 1px solid var(--tv-border);
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sv-month__cell:nth-child(7n) { border-right: none; }

.sv-month__cell--other { background: var(--tv-bg); }
.sv-month__cell--other .sv-month__day { color: var(--tv-text-muted); }
.sv-month__cell--today { background: var(--tv-primary-soft); }
.sv-month__cell--unavailable { background: repeating-linear-gradient(135deg, transparent, transparent 4px, var(--tv-danger-soft) 4px, var(--tv-danger-soft) 5px); }

.sv-month__day {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  line-height: 1.4;
  align-self: flex-start;
}
.sv-month__cell--today .sv-month__day {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
  width: 22px; height: 22px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: var(--tv-text-xs);
}

.sv-month__events { display: flex; flex-direction: column; gap: 2px; }
.sv-month__more { font-size: var(--tv-text-xs); color: var(--tv-text-muted); padding: 0 2px; }

/* ---- Event chips (month view) ---- */
.sv-chip {
  display: flex;
  align-items: center;
  gap: 3px;
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: var(--tv-font-medium);
  border: none;
  cursor: pointer;
  text-align: left;
  max-width: 100%;
  overflow: hidden;
  white-space: nowrap;
  transition: opacity 0.15s;
}
.sv-chip:hover { opacity: 0.85; }
.sv-chip__time { opacity: 0.75; flex-shrink: 0; }
.sv-chip__label { overflow: hidden; text-overflow: ellipsis; flex: 1; }
.sv-chip__trial, .sv-chip__recur { flex-shrink: 0; font-size: 9px; opacity: 0.8; }

.sv-chip--scheduled   { background: var(--tv-primary-soft);  color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }
.sv-chip--completed   { background: var(--tv-success-soft);  color: var(--tv-success-fg); }
.sv-chip--cancelled   { background: var(--tv-neutral-soft);  color: var(--tv-neutral);    text-decoration: line-through; }
.sv-chip--missed      { background: var(--tv-danger-soft);   color: var(--tv-danger-fg); }
.sv-chip--trial       { background: var(--tv-purple-soft);   color: var(--tv-purple); }
.sv-chip--live        { background: var(--tv-success-soft);  color: var(--tv-success-fg); }
.sv-chip--open-slot   { background: var(--tv-teal-soft);     color: var(--tv-teal-fg);    border: 1px dashed hsl(186, 50%, 70%); }

/* ---- Week/Day shared grid styles ---- */
.sv-week, .sv-day {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* --- Week head --- */
.sv-week__head {
  display: grid;
  grid-template-columns: 52px repeat(7, 1fr);
  border-bottom: 1px solid var(--tv-border);
  background: var(--tv-bg-soft);
  position: sticky;
  top: 0;
  z-index: 2;
}

.sv-week__time-gutter { border-right: 1px solid var(--tv-border); }

.sv-week__day-head {
  padding: var(--tv-space-2) var(--tv-space-2);
  text-align: center;
  border-right: 1px solid var(--tv-border);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.sv-week__day-head:last-child { border-right: none; }

.sv-week__dow { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.sv-week__date { font-size: var(--tv-text-base); font-weight: var(--tv-font-medium); color: var(--tv-text); width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
.sv-week__date--today { background: var(--tv-primary); color: var(--tv-text-inverse); }

/* --- Week body --- */
.sv-week__body {
  display: grid;
  grid-template-columns: 52px repeat(7, 1fr);
  overflow-y: auto;
  max-height: 640px;
}

.sv-week__time-col {
  border-right: 1px solid var(--tv-border);
  background: var(--tv-bg-soft);
}

.sv-week__hour-label {
  height: 64px;
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  padding-right: var(--tv-space-2);
  padding-top: 2px;
  font-size: 11px;
  color: var(--tv-text-muted);
  border-bottom: 1px solid var(--tv-border);
  box-sizing: border-box;
}

.sv-week__col {
  position: relative;
  border-right: 1px solid var(--tv-border);
}
.sv-week__col:last-child { border-right: none; }
.sv-week__col--unavailable { background: repeating-linear-gradient(135deg, transparent, transparent 4px, hsl(0, 72%, 97%) 4px, hsl(0, 72%, 97%) 5px); }

.sv-week__hour-cell {
  height: 64px;
  border-bottom: 1px solid var(--tv-border);
  box-sizing: border-box;
}

/* --- Slot backgrounds --- */
.sv-week__slot-bg, .sv-day__slot-bg {
  position: absolute;
  left: 0; right: 0;
  background: var(--tv-teal-soft);
  border-left: 3px solid hsl(186, 70%, 55%);
  pointer-events: auto;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.15s;
  z-index: 1;
}
.sv-week__slot-bg:hover, .sv-day__slot-bg:hover { opacity: 1; }
.sv-week__slot-bg--booked, .sv-day__slot-bg--booked {
  background: var(--tv-neutral-soft);
  border-left-color: var(--tv-neutral-border);
  cursor: default;
  opacity: 0.5;
}

/* --- Events (week/day) --- */
.sv-week__event, .sv-day__event {
  position: absolute;
  left: 3px; right: 3px;
  border-radius: var(--tv-radius-sm);
  padding: 3px 6px;
  border: none;
  text-align: left;
  cursor: pointer;
  z-index: 2;
  overflow: hidden;
  transition: opacity 0.15s, transform 0.1s;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.sv-week__event:hover, .sv-day__event:hover { opacity: 0.9; transform: scale(1.01); }

.sv-week__event-title, .sv-day__event-title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sv-week__event-meta, .sv-day__event-meta {
  font-size: 10px;
  opacity: 0.85;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sv-week__event-trial, .sv-day__event-trial, .sv-week__event-recur, .sv-day__event-recur {
  font-size: 9px;
  opacity: 0.8;
}

/* Event colors */
.sv-week__event--scheduled, .sv-day__event--scheduled {
  background: var(--tv-primary-soft);
  color: hsl(var(--tv-primary-h), var(--tv-primary-s), 35%);
  border-left: 3px solid var(--tv-primary);
}
.sv-week__event--completed, .sv-day__event--completed {
  background: var(--tv-success-soft);
  color: var(--tv-success-fg);
  border-left: 3px solid var(--tv-success);
}
.sv-week__event--cancelled, .sv-day__event--cancelled {
  background: var(--tv-neutral-soft);
  color: var(--tv-neutral);
  border-left: 3px solid var(--tv-neutral-border);
  text-decoration: line-through;
}
.sv-week__event--missed, .sv-day__event--missed {
  background: var(--tv-danger-soft);
  color: var(--tv-danger-fg);
  border-left: 3px solid var(--tv-danger);
}
.sv-week__event--trial, .sv-day__event--trial {
  background: var(--tv-purple-soft);
  color: var(--tv-purple);
  border-left: 3px solid var(--tv-purple);
}
.sv-week__event--live, .sv-day__event--live {
  background: var(--tv-success-soft);
  color: var(--tv-success-fg);
  border-left: 3px solid var(--tv-success);
  animation: pulse-border 1.5s ease-in-out infinite;
}
@keyframes pulse-border { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }

/* --- Now line --- */
.sv-week__now-line, .sv-day__now-line {
  position: absolute;
  left: 0; right: 0;
  height: 2px;
  background: var(--tv-danger);
  z-index: 3;
  pointer-events: none;
}
.sv-week__now-line::before, .sv-day__now-line::before {
  content: '';
  position: absolute;
  left: -5px; top: -4px;
  width: 10px; height: 10px;
  background: var(--tv-danger);
  border-radius: 50%;
}

/* ---- Day view ---- */
.sv-day__head {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-4);
  border-bottom: 1px solid var(--tv-border);
  background: var(--tv-bg-soft);
}

.sv-day__date-label {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
}
.sv-day__date-label--today { color: var(--tv-primary); }

.sv-day__today-chip, .sv-day__unavail-chip {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}
.sv-day__today-chip  { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }
.sv-day__unavail-chip { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }

.sv-day__body {
  display: grid;
  grid-template-columns: 52px 1fr;
  overflow-y: auto;
  max-height: 640px;
}

.sv-day__time-col {
  border-right: 1px solid var(--tv-border);
  background: var(--tv-bg-soft);
}

.sv-day__hour-label {
  height: 64px;
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  padding-right: var(--tv-space-2);
  padding-top: 2px;
  font-size: 11px;
  color: var(--tv-text-muted);
  border-bottom: 1px solid var(--tv-border);
  box-sizing: border-box;
}

.sv-day__col {
  position: relative;
}
.sv-day__col--unavailable {
  background: repeating-linear-gradient(135deg, transparent, transparent 4px, hsl(0, 72%, 97%) 4px, hsl(0, 72%, 97%) 5px);
}

.sv-day__hour-cell {
  height: 64px;
  border-bottom: 1px solid var(--tv-border);
  box-sizing: border-box;
}

/* ---- Responsive ---- */
@media (max-width: 900px) {
  .sv-page { padding: var(--tv-space-4); }
  .sv-week__body { max-height: 480px; }
  .sv-day__body  { max-height: 480px; }
  .sv-header__right { gap: var(--tv-space-1); }
  .sv-period-label { display: none; }
}

@media (max-width: 640px) {
  .sv-view-btn { padding: var(--tv-space-1) var(--tv-space-2); font-size: var(--tv-text-xs); }
  .sv-month__cell { min-height: 72px; }
}
</style>
