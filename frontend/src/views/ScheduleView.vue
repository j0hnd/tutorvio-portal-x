<template>
  <div class="sv-page">

    <!-- ── Header ── -->
    <div class="sv-header">
      <div class="sv-header__left">
        <h1 class="sv-title">Schedule</h1>
        <span class="sv-tz-badge">{{ timezone }}</span>
      </div>
      <div class="sv-header__controls">
        <select v-if="showTeacherFilter" v-model="filterTeacherId" class="sv-ctrl-select" aria-label="Filter by teacher">
          <option value="">All Teachers</option>
          <option value="u2">James Reyes</option>
        </select>

        <div class="sv-view-switcher" role="group" aria-label="Calendar view">
          <button v-for="v in VIEWS" :key="v.key"
            :class="['sv-view-btn', { 'sv-view-btn--active': currentView === v.key }]"
            type="button" @click="currentView = v.key">{{ v.label }}</button>
        </div>

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

        <!-- Selectable month + year -->
        <div class="sv-period-selects">
          <select :value="cursorMonth" class="sv-period-select" @change="setCursorMonth(+($event.target as HTMLSelectElement).value)">
            <option v-for="(m, i) in MONTHS" :key="i" :value="i">{{ m }}</option>
          </select>
          <select :value="cursorYear" class="sv-period-select" @change="setCursorYear(+($event.target as HTMLSelectElement).value)">
            <option v-for="y in YEARS" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ── Body: calendar + optional side panel ── -->
    <div :class="['sv-body', { 'sv-body--panel': !!selectedDate }]">

      <!-- Calendar -->
      <div class="sv-cal">

        <!-- Month view -->
        <div v-if="currentView === 'month'" class="sv-month">
          <div class="sv-month__weekdays">
            <span v-for="d in WEEKDAYS" :key="d" class="sv-month__wday">{{ d }}</span>
          </div>
          <div class="sv-month__grid">
            <div
              v-for="cell in monthCells" :key="cell.dateStr"
              :class="[
                'sv-month__cell',
                { 'sv-month__cell--other':       !cell.inMonth },
                { 'sv-month__cell--today':        cell.isToday },
                { 'sv-month__cell--unavailable':  cell.isUnavailable },
                { 'sv-month__cell--selected':     selectedDate === cell.dateStr },
              ]"
              @click="selectDate(cell.dateStr)"
            >
              <span class="sv-month__day">{{ cell.day }}</span>
              <div class="sv-month__events">
                <button
                  v-for="ev in cell.events.slice(0, 3)" :key="ev.id"
                  :class="['sv-chip', `sv-chip--${statusClass(ev.status)}`, { 'sv-chip--trial': ev.isTrial }]"
                  type="button" :title="ev.title"
                  @click.stop="openLesson(ev)"
                >
                  <span class="sv-chip__time">{{ formatTime(ev.startTime) }}</span>
                  <span class="sv-chip__label">{{ ev.title }}</span>
                  <span v-if="ev.isTrial" class="sv-chip__badge">TRIAL</span>
                  <span v-if="ev.isRecurring" class="sv-chip__recur" aria-label="Recurring">↻</span>
                </button>
                <button
                  v-for="slot in cell.slots.slice(0, 2)" :key="slot.id"
                  class="sv-chip sv-chip--open-slot" type="button"
                  :title="`Available: ${slot.startTime}–${slot.endTime}`"
                  @click.stop="openSlot(slot)"
                >
                  <span class="sv-chip__time">{{ slot.startTime }}</span>
                  <span class="sv-chip__label">Open slot</span>
                </button>
                <span v-if="cell.events.length + cell.slots.length > 3" class="sv-month__more">
                  +{{ cell.events.length + cell.slots.length - 3 }} more
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Week view -->
        <div v-else-if="currentView === 'week'" class="sv-week">
          <div class="sv-week__scroll-wrap">
            <div class="sv-week__inner">
              <!-- Day headers -->
              <div class="sv-week__head">
                <div class="sv-week__time-gutter" />
                <div
                  v-for="day in weekDays" :key="day.dateStr"
                  :class="['sv-week__day-head',
                    { 'sv-week__day-head--today':    day.isToday },
                    { 'sv-week__day-head--selected': selectedDate === day.dateStr },
                  ]"
                  @click="selectDate(day.dateStr)"
                >
                  <span class="sv-week__dow">{{ day.dow }}</span>
                  <span :class="['sv-week__date', { 'sv-week__date--today': day.isToday }]">{{ day.date }}</span>
                </div>
              </div>
              <!-- Time grid -->
              <div class="sv-week__body">
                <div class="sv-week__time-col">
                  <div v-for="h in HOURS" :key="h" class="sv-week__hour-label">{{ formatHour(h) }}</div>
                </div>
                <div
                  v-for="day in weekDays" :key="day.dateStr"
                  :class="['sv-week__col',
                    { 'sv-week__col--unavailable': day.isUnavailable },
                    { 'sv-week__col--selected':    selectedDate === day.dateStr },
                  ]"
                >
                  <div v-for="h in HOURS" :key="h" class="sv-week__hour-cell" />
                  <div
                    v-for="slot in day.slots" :key="slot.id"
                    :class="['sv-week__slot-bg', { 'sv-week__slot-bg--booked': slot.isBooked }]"
                    :style="slotStyle(slot.startTime, slot.endTime)"
                    :title="slot.isBooked ? `Booked: ${slot.startTime}–${slot.endTime}` : `Available: ${slot.startTime}–${slot.endTime}`"
                    @click="!slot.isBooked && canBook ? openSlot(slot) : undefined"
                  />
                  <button
                    v-for="ev in day.events" :key="ev.id"
                    :class="['sv-week__event', `sv-week__event--${statusClass(ev.status)}`, { 'sv-week__event--trial': ev.isTrial }]"
                    :style="eventStyle(ev)" type="button"
                    @click="openLesson(ev)"
                  >
                    <span class="sv-week__event-title">{{ ev.title }}</span>
                    <span class="sv-week__event-meta">
                      {{ formatTime(ev.startTime) }}–{{ formatTime(ev.endTime) }}
                      <template v-if="roleLabel(ev)"> · {{ roleLabel(ev) }}</template>
                    </span>
                    <span v-if="ev.isTrial" class="sv-week__event-badge">TRIAL</span>
                    <span v-if="ev.isRecurring" class="sv-week__event-recur">↻</span>
                  </button>
                  <div v-if="day.isToday" class="sv-week__now-line" :style="nowLineStyle" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Day view -->
        <div v-else class="sv-day">
          <div class="sv-day__head">
            <span :class="['sv-day__date-label', { 'sv-day__date-label--today': isDayToday }]">{{ dayLabel }}</span>
            <span v-if="isDayToday" class="sv-day__chip sv-day__chip--today">Today</span>
            <span v-if="dayUnavailable" class="sv-day__chip sv-day__chip--unavail">Unavailable</span>
          </div>
          <div class="sv-day__scroll-wrap">
            <div class="sv-day__inner">
              <div class="sv-day__time-col">
                <div v-for="h in HOURS" :key="h" class="sv-day__hour-label">{{ formatHour(h) }}</div>
              </div>
              <div :class="['sv-day__col', { 'sv-day__col--unavailable': dayUnavailable }]">
                <div v-for="h in HOURS" :key="h" class="sv-day__hour-cell" />
                <div
                  v-for="slot in currentDaySlots" :key="slot.id"
                  :class="['sv-day__slot-bg', { 'sv-day__slot-bg--booked': slot.isBooked }]"
                  :style="slotStyle(slot.startTime, slot.endTime)"
                  :title="slot.isBooked ? `Booked: ${slot.startTime}–${slot.endTime}` : `Available: ${slot.startTime}–${slot.endTime}`"
                  @click="!slot.isBooked && canBook ? openSlot(slot) : undefined"
                />
                <button
                  v-for="ev in currentDayEvents" :key="ev.id"
                  :class="['sv-day__event', `sv-day__event--${statusClass(ev.status)}`, { 'sv-day__event--trial': ev.isTrial }]"
                  :style="eventStyle(ev)" type="button"
                  @click="openLesson(ev)"
                >
                  <span class="sv-day__event-title">{{ ev.title }}</span>
                  <span class="sv-day__event-meta">
                    {{ formatTime(ev.startTime) }}–{{ formatTime(ev.endTime) }}
                    <template v-if="roleLabel(ev)"> · {{ roleLabel(ev) }}</template>
                  </span>
                  <span v-if="ev.isTrial" class="sv-day__event-badge">TRIAL</span>
                </button>
                <div v-if="isDayToday" class="sv-day__now-line" :style="nowLineStyle" />
              </div>
            </div>
          </div>
        </div>

      </div><!-- /sv-cal -->

      <!-- ── Side panel ── -->
      <Transition name="sv-panel">
        <aside v-if="selectedDate" class="sv-panel" aria-label="Day detail">
          <div class="sv-panel__header">
            <div class="sv-panel__header-text">
              <p class="sv-panel__dow">{{ panelDow }}</p>
              <h2 class="sv-panel__date">{{ panelDateLabel }}</h2>
            </div>
            <button class="sv-panel__close" type="button" aria-label="Close panel" @click="selectedDate = null">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </button>
          </div>

          <!-- Events list -->
          <div v-if="panelEvents.length" class="sv-panel__section">
            <p class="sv-panel__section-label">Schedules</p>
            <button
              v-for="ev in panelEvents" :key="ev.id"
              :class="['sv-panel__item', `sv-panel__item--${statusClass(ev.status)}`]"
              type="button" @click="openLesson(ev)"
            >
              <span :class="['sv-panel__item-dot', `sv-panel__item-dot--${statusClass(ev.status)}`]" />
              <div class="sv-panel__item-body">
                <span class="sv-panel__item-title">{{ ev.title }}</span>
                <span class="sv-panel__item-meta">{{ formatTime(ev.startTime) }} – {{ formatTime(ev.endTime) }}</span>
                <span class="sv-panel__item-person">{{ roleLabel(ev) }}</span>
              </div>
              <span :class="['sv-panel__item-status', `sv-panel__item-status--${statusClass(ev.status)}`]">
                {{ statusLabel(ev.status) }}
              </span>
            </button>
          </div>

          <!-- Open slots section (non-empty days for students) -->
          <div v-if="canBook && panelOpenSlots.length && panelEvents.length" class="sv-panel__section">
            <p class="sv-panel__section-label">Available Slots</p>
            <button
              v-for="slot in panelOpenSlots" :key="slot.id"
              class="sv-panel__slot" type="button" @click="openSlot(slot)"
            >
              <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M7 4.5V7l2 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              <span class="sv-panel__slot-time">{{ slot.startTime }} – {{ slot.endTime }}</span>
              <span class="sv-panel__slot-teacher">{{ slot.teacherName }}</span>
              <span class="sv-panel__slot-book">Book →</span>
            </button>
          </div>

          <!-- Empty state -->
          <div v-if="!panelEvents.length" class="sv-panel__empty">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
              <rect x="6" y="8" width="28" height="26" rx="3" stroke="currentColor" stroke-width="1.5" opacity=".3"/>
              <path d="M13 6v4M27 6v4M6 16h28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".3"/>
              <path d="M15 24l2.5 2.5L25 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity=".4"/>
            </svg>
            <p class="sv-panel__empty-text">No schedules for this day.</p>
            <template v-if="canBook">
              <button v-if="panelOpenSlots.length" class="sv-panel__add-btn" type="button" @click="handleAddSchedule">
                <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Add Schedule
              </button>
              <p v-else class="sv-panel__no-slots">No open slots available on this date.</p>
            </template>
          </div>

        </aside>
      </Transition>

    </div><!-- /sv-body -->

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

const schedule          = useScheduleStore()
const auth              = useAuthStore()
const { effectiveRole } = useViewAs()

// ---- Config ----
type ViewKey = 'month' | 'week' | 'day'
const VIEWS    = [{ key: 'month' as ViewKey, label: 'Month' }, { key: 'week' as ViewKey, label: 'Week' }, { key: 'day' as ViewKey, label: 'Day' }]
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const HOURS    = Array.from({ length: 15 }, (_, i) => i + 7)   // 7–21
const HOUR_H   = 64   // px per hour

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']
const YEARS  = Array.from({ length: 10 }, (_, i) => 2023 + i)

// ---- State ----
const currentView     = ref<ViewKey>('week')
const cursor          = ref(new Date('2026-05-22'))
const filterTeacherId = ref('')
const selectedDate    = ref<string | null>(null)
const selectedLesson  = ref<ScheduleLesson | null>(null)
const selectedSlot    = ref<AvailabilitySlot | null>(null)

// ---- Derived ----
const timezone          = computed(() => auth.user?.timezone ?? 'Asia/Manila')
const showTeacherFilter = computed(() => effectiveRole.value === 'ADMIN' || effectiveRole.value === 'STAFF')
const canBook           = computed(() => effectiveRole.value === 'STUDENT')

const cursorMonth = computed(() => cursor.value.getMonth())
const cursorYear  = computed(() => cursor.value.getFullYear())

function setCursorMonth(m: number): void {
  const d = new Date(cursor.value); d.setMonth(m); cursor.value = d
}
function setCursorYear(y: number): void {
  const d = new Date(cursor.value); d.setFullYear(y); cursor.value = d
}

watchEffect(() => { schedule.selectedTeacherId = filterTeacherId.value || null })

// ---- Helpers ----
function toDateStr(d: Date): string { return d.toLocaleDateString('sv-SE') }

function startOfMonth(d: Date): Date { return new Date(d.getFullYear(), d.getMonth(), 1) }

function startOfWeek(d: Date): Date {
  const s = new Date(d); s.setDate(d.getDate() - d.getDay()); return s
}

function addDays(d: Date, n: number): Date {
  const r = new Date(d); r.setDate(r.getDate() + n); return r
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

function statusLabel(status: string): string {
  switch (status) {
    case 'SCHEDULED':         return 'Scheduled'
    case 'IN_PROGRESS':       return 'Live'
    case 'COMPLETED':         return 'Completed'
    case 'CANCELLED':         return 'Cancelled'
    case 'MISSED_BY_STUDENT': return 'Missed'
    case 'MISSED_BY_TEACHER': return 'Tchr missed'
    case 'TRIAL':             return 'Trial'
    default:                  return status
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

// ---- Navigation ----
function navigate(dir: 1 | -1): void {
  const d = new Date(cursor.value)
  if (currentView.value === 'month') d.setMonth(d.getMonth() + dir)
  else if (currentView.value === 'week') d.setDate(d.getDate() + dir * 7)
  else d.setDate(d.getDate() + dir)
  cursor.value = d
  selectedDate.value = null
}

function goToday(): void {
  cursor.value = new Date('2026-05-22')
  selectedDate.value = null
}

// ---- Selected date (side panel) ----
function selectDate(dateStr: string): void {
  selectedDate.value = selectedDate.value === dateStr ? null : dateStr
}

const panelEvents = computed((): ScheduleLesson[] =>
  selectedDate.value ? eventsForDate(selectedDate.value) : []
)

const panelOpenSlots = computed((): AvailabilitySlot[] =>
  selectedDate.value ? slotsForDate(selectedDate.value).filter(s => !s.isBooked) : []
)

const panelDow = computed((): string =>
  selectedDate.value
    ? new Date(`${selectedDate.value}T12:00:00`).toLocaleDateString('en-US', { weekday: 'long' })
    : ''
)

const panelDateLabel = computed((): string =>
  selectedDate.value
    ? new Date(`${selectedDate.value}T12:00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
    : ''
)

// ---- Month grid ----
const monthCells = computed(() => {
  const month      = cursor.value.getMonth()
  const first      = startOfMonth(cursor.value)
  const today      = '2026-05-22'
  const gridStart  = startOfWeek(first)
  return Array.from({ length: 42 }, (_, i) => {
    const d       = addDays(gridStart, i)
    const dateStr = toDateStr(d)
    return {
      day:           d.getDate(),
      dateStr,
      inMonth:       d.getMonth() === month,
      isToday:       dateStr === today,
      isUnavailable: isUnavailableDate(dateStr),
      events:        eventsForDate(dateStr),
      slots:         canBook.value ? slotsForDate(dateStr) : [],
    }
  })
})

// ---- Week grid ----
const weekDays = computed(() => {
  const s     = startOfWeek(cursor.value)
  const today = '2026-05-22'
  return Array.from({ length: 7 }, (_, i) => {
    const d       = addDays(s, i)
    const dateStr = toDateStr(d)
    return { dow: WEEKDAYS[d.getDay()], date: d.getDate(), dateStr,
      isToday: dateStr === today, isUnavailable: isUnavailableDate(dateStr),
      events: eventsForDate(dateStr), slots: slotsForDate(dateStr) }
  })
})

// ---- Day view ----
const currentDayStr    = computed(() => toDateStr(cursor.value))
const isDayToday       = computed(() => currentDayStr.value === '2026-05-22')
const dayUnavailable   = computed(() => isUnavailableDate(currentDayStr.value))
const currentDayEvents = computed(() => eventsForDate(currentDayStr.value))
const currentDaySlots  = computed(() => slotsForDate(currentDayStr.value))
const dayLabel         = computed(() =>
  cursor.value.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
)

// ---- Event/slot positioning ----
function timeToMinutes(t: string): number {
  const [h, m] = t.split(':').map(Number); return h * 60 + m
}
function isoToMinutes(iso: string): number {
  const d = new Date(iso); return d.getHours() * 60 + d.getMinutes()
}
function eventStyle(ev: ScheduleLesson): Record<string, string> {
  const top    = ((isoToMinutes(ev.startTime) - 420) / 60) * HOUR_H
  const height = Math.max(((isoToMinutes(ev.endTime) - isoToMinutes(ev.startTime)) / 60) * HOUR_H, 24)
  return { top: `${top}px`, height: `${height}px` }
}
function slotStyle(start: string, end: string): Record<string, string> {
  const top    = ((timeToMinutes(start) - 420) / 60) * HOUR_H
  const height = ((timeToMinutes(end) - timeToMinutes(start)) / 60) * HOUR_H
  return { top: `${top}px`, height: `${height}px` }
}

// ---- Now line ----
const nowLineStyle = computed(() => {
  const now = new Date()
  const top = ((now.getHours() * 60 + now.getMinutes() - 420) / 60) * HOUR_H
  return { top: `${top}px` }
})

// ---- Modal handlers ----
function openLesson(lesson: ScheduleLesson): void { selectedLesson.value = lesson }

function openSlot(slot: AvailabilitySlot): void {
  if (!canBook.value) return
  selectedSlot.value = slot
}

function handleAddSchedule(): void {
  const slot = panelOpenSlots.value[0]
  if (slot) selectedSlot.value = slot
}

function handleCancel(lessonId: string): void {
  schedule.cancelLesson(lessonId); selectedLesson.value = null
}
function handleReschedule(lessonId: string, newStart: string, newEnd: string): void {
  schedule.rescheduleLesson(lessonId, newStart, newEnd); selectedLesson.value = null
}
function handleBooked(slotId: string, subject: string, isTrial: boolean): void {
  const userId   = auth.user?.id ?? 'u1'
  const userName = auth.user ? `${auth.user.firstName} ${auth.user.lastName}` : 'Student'
  schedule.bookSlot(slotId, userId, userName, subject, isTrial)
  selectedSlot.value = null
}
</script>

<style scoped>
/* ── Page ── */
.sv-page {
  padding: var(--tv-space-4) var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

/* ── Header ── */
.sv-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--tv-space-3);
}
.sv-header__left { display: flex; align-items: center; gap: var(--tv-space-3); flex-shrink: 0; }
.sv-header__controls { display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap; }

.sv-title { font-size: var(--tv-text-xl); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.sv-tz-badge {
  font-size: var(--tv-text-xs); color: var(--tv-text-muted);
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-full); padding: 2px var(--tv-space-2);
}

.sv-ctrl-select, .sv-period-select {
  font-size: var(--tv-text-sm); padding: var(--tv-space-1) var(--tv-space-2);
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card); color: var(--tv-text); cursor: pointer;
}

.sv-view-switcher { display: flex; border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm); overflow: hidden; }
.sv-view-btn {
  padding: var(--tv-space-1) var(--tv-space-3); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium); color: var(--tv-text-secondary);
  background: var(--tv-bg-card); border: none; cursor: pointer; transition: background .15s, color .15s;
}
.sv-view-btn + .sv-view-btn { border-left: 1px solid var(--tv-border); }
.sv-view-btn--active { background: var(--tv-primary); color: var(--tv-text-inverse); }

.sv-nav { display: flex; align-items: center; gap: var(--tv-space-1); }
.sv-nav-btn {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card); color: var(--tv-text-secondary); cursor: pointer; transition: background .15s;
}
.sv-nav-btn:hover { background: var(--tv-bg-soft); }
.sv-today-btn {
  padding: var(--tv-space-1) var(--tv-space-3); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm); background: var(--tv-bg-card);
  color: var(--tv-text-secondary); cursor: pointer; transition: background .15s;
}
.sv-today-btn:hover { background: var(--tv-bg-soft); }

.sv-period-selects { display: flex; align-items: center; gap: var(--tv-space-1); }

/* ── Body layout ── */
.sv-body {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--tv-space-4);
  align-items: start;
  min-width: 0;
}
.sv-body--panel { grid-template-columns: 1fr 310px; }

.sv-cal { min-width: 0; }

/* ── Month view ── */
.sv-month {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
}
.sv-month__weekdays {
  display: grid; grid-template-columns: repeat(7, 1fr);
  background: var(--tv-bg-soft); border-bottom: 1px solid var(--tv-border);
}
.sv-month__wday {
  padding: var(--tv-space-2) 0; text-align: center; font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold); color: var(--tv-text-muted);
  text-transform: uppercase; letter-spacing: .05em;
}
.sv-month__grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.sv-month__cell {
  min-height: 100px; padding: var(--tv-space-2); cursor: pointer;
  border-right: 1px solid var(--tv-border); border-bottom: 1px solid var(--tv-border);
  display: flex; flex-direction: column; gap: 2px;
  transition: background .1s;
}
.sv-month__cell:nth-child(7n) { border-right: none; }
.sv-month__cell:hover { background: var(--tv-bg-soft); }
.sv-month__cell--other { background: var(--tv-bg); }
.sv-month__cell--other .sv-month__day { color: var(--tv-text-muted); }
.sv-month__cell--today { background: var(--tv-primary-soft); }
.sv-month__cell--selected { outline: 2px solid var(--tv-primary); outline-offset: -2px; }
.sv-month__cell--unavailable { background: repeating-linear-gradient(135deg, transparent, transparent 4px, var(--tv-danger-soft) 4px, var(--tv-danger-soft) 5px); }

.sv-month__day { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); line-height: 1.4; align-self: flex-start; }
.sv-month__cell--today .sv-month__day {
  background: var(--tv-primary); color: var(--tv-text-inverse);
  width: 22px; height: 22px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; font-size: var(--tv-text-xs);
}
.sv-month__events { display: flex; flex-direction: column; gap: 2px; }
.sv-month__more { font-size: var(--tv-text-xs); color: var(--tv-text-muted); padding: 0 2px; }

/* chips */
.sv-chip {
  display: flex; align-items: center; gap: 3px; padding: 1px 5px; border-radius: 3px;
  font-size: 11px; font-weight: var(--tv-font-medium); border: none; cursor: pointer;
  text-align: left; max-width: 100%; overflow: hidden; white-space: nowrap; transition: opacity .15s;
}
.sv-chip:hover { opacity: .85; }
.sv-chip__time  { opacity: .75; flex-shrink: 0; }
.sv-chip__label { overflow: hidden; text-overflow: ellipsis; flex: 1; }
.sv-chip__badge, .sv-chip__recur { flex-shrink: 0; font-size: 9px; opacity: .8; }
.sv-chip--scheduled { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }
.sv-chip--completed { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sv-chip--cancelled { background: var(--tv-neutral-soft); color: var(--tv-neutral); text-decoration: line-through; }
.sv-chip--missed    { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sv-chip--trial     { background: var(--tv-purple-soft);  color: var(--tv-purple); }
.sv-chip--live      { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sv-chip--open-slot { background: var(--tv-teal-soft); color: var(--tv-teal-fg); border: 1px dashed hsl(186,50%,70%); }

/* ── Week view ── */
.sv-week {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
}
.sv-week__scroll-wrap { overflow: auto; max-height: 700px; }
.sv-week__inner { min-width: 580px; }

.sv-week__head {
  display: grid; grid-template-columns: 52px repeat(7, minmax(72px, 1fr));
  background: var(--tv-bg-soft); border-bottom: 1px solid var(--tv-border);
  position: sticky; top: 0; z-index: 2;
}
.sv-week__time-gutter { border-right: 1px solid var(--tv-border); }
.sv-week__day-head {
  padding: var(--tv-space-2); text-align: center; cursor: pointer;
  border-right: 1px solid var(--tv-border); display: flex; flex-direction: column;
  align-items: center; gap: 2px; transition: background .1s;
}
.sv-week__day-head:last-child { border-right: none; }
.sv-week__day-head:hover { background: var(--tv-bg); }
.sv-week__day-head--selected { background: var(--tv-primary-soft); }
.sv-week__dow  { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; }
.sv-week__date { font-size: var(--tv-text-base); font-weight: var(--tv-font-medium); color: var(--tv-text); width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
.sv-week__date--today { background: var(--tv-primary); color: var(--tv-text-inverse); }

.sv-week__body { display: grid; grid-template-columns: 52px repeat(7, minmax(72px, 1fr)); }
.sv-week__time-col { border-right: 1px solid var(--tv-border); background: var(--tv-bg-soft); }
.sv-week__hour-label {
  height: 64px; display: flex; align-items: flex-start; justify-content: flex-end;
  padding-right: var(--tv-space-2); padding-top: 2px; font-size: 11px; color: var(--tv-text-muted);
  border-bottom: 1px solid var(--tv-border); box-sizing: border-box;
}
.sv-week__col { position: relative; border-right: 1px solid var(--tv-border); }
.sv-week__col:last-child { border-right: none; }
.sv-week__col--unavailable { background: repeating-linear-gradient(135deg, transparent, transparent 4px, hsl(0,72%,97%) 4px, hsl(0,72%,97%) 5px); }
.sv-week__col--selected { background: var(--tv-primary-soft); }
.sv-week__col--selected.sv-week__col--unavailable { background: repeating-linear-gradient(135deg, var(--tv-primary-soft), var(--tv-primary-soft) 4px, hsl(0,72%,97%) 4px, hsl(0,72%,97%) 5px); }
.sv-week__hour-cell { height: 64px; border-bottom: 1px solid var(--tv-border); box-sizing: border-box; }

.sv-week__slot-bg, .sv-day__slot-bg {
  position: absolute; left: 0; right: 0; background: var(--tv-teal-soft);
  border-left: 3px solid hsl(186,70%,55%); pointer-events: auto; cursor: pointer;
  opacity: .7; transition: opacity .15s; z-index: 1;
}
.sv-week__slot-bg:hover, .sv-day__slot-bg:hover { opacity: 1; }
.sv-week__slot-bg--booked, .sv-day__slot-bg--booked {
  background: var(--tv-neutral-soft); border-left-color: var(--tv-neutral-border); cursor: default; opacity: .5;
}

.sv-week__event, .sv-day__event {
  position: absolute; left: 3px; right: 3px; border-radius: var(--tv-radius-sm);
  padding: 3px 6px; border: none; text-align: left; cursor: pointer; z-index: 2;
  overflow: hidden; transition: opacity .15s, transform .1s;
  display: flex; flex-direction: column; gap: 1px;
}
.sv-week__event:hover, .sv-day__event:hover { opacity: .9; transform: scale(1.01); }
.sv-week__event-title, .sv-day__event-title { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sv-week__event-meta, .sv-day__event-meta { font-size: 10px; opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sv-week__event-badge, .sv-day__event-badge, .sv-week__event-recur, .sv-day__event-recur { font-size: 9px; opacity: .8; }

.sv-week__event--scheduled, .sv-day__event--scheduled { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 35%); border-left: 3px solid var(--tv-primary); }
.sv-week__event--completed, .sv-day__event--completed { background: var(--tv-success-soft); color: var(--tv-success-fg); border-left: 3px solid var(--tv-success); }
.sv-week__event--cancelled, .sv-day__event--cancelled { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-left: 3px solid var(--tv-neutral-border); text-decoration: line-through; }
.sv-week__event--missed,    .sv-day__event--missed    { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-left: 3px solid var(--tv-danger); }
.sv-week__event--trial,     .sv-day__event--trial     { background: var(--tv-purple-soft); color: var(--tv-purple); border-left: 3px solid var(--tv-purple); }
.sv-week__event--live,      .sv-day__event--live      { background: var(--tv-success-soft); color: var(--tv-success-fg); border-left: 3px solid var(--tv-success); animation: pulse-ev 1.5s ease-in-out infinite; }
@keyframes pulse-ev { 0%,100% { opacity:1 } 50% { opacity:.7 } }

.sv-week__now-line, .sv-day__now-line { position: absolute; left:0; right:0; height:2px; background:var(--tv-danger); z-index:3; pointer-events:none; }
.sv-week__now-line::before, .sv-day__now-line::before { content:''; position:absolute; left:-5px; top:-4px; width:10px; height:10px; background:var(--tv-danger); border-radius:50%; }

/* ── Day view ── */
.sv-day {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
}
.sv-day__head {
  display: flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-4);
  border-bottom: 1px solid var(--tv-border); background: var(--tv-bg-soft);
}
.sv-day__date-label { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.sv-day__date-label--today { color: var(--tv-primary); }
.sv-day__chip { font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium); padding: 2px var(--tv-space-2); border-radius: var(--tv-radius-full); }
.sv-day__chip--today  { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }
.sv-day__chip--unavail { background: var(--tv-danger-soft); color: var(--tv-danger-fg); }

.sv-day__scroll-wrap { overflow: auto; max-height: 700px; }
.sv-day__inner { display: grid; grid-template-columns: 52px 1fr; min-width: 320px; }
.sv-day__time-col { border-right: 1px solid var(--tv-border); background: var(--tv-bg-soft); }
.sv-day__hour-label { height: 64px; display: flex; align-items: flex-start; justify-content: flex-end; padding-right: var(--tv-space-2); padding-top: 2px; font-size: 11px; color: var(--tv-text-muted); border-bottom: 1px solid var(--tv-border); box-sizing: border-box; }
.sv-day__col { position: relative; }
.sv-day__col--unavailable { background: repeating-linear-gradient(135deg, transparent, transparent 4px, hsl(0,72%,97%) 4px, hsl(0,72%,97%) 5px); }
.sv-day__hour-cell { height: 64px; border-bottom: 1px solid var(--tv-border); box-sizing: border-box; }

/* ── Side panel ── */
.sv-panel {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
  display: flex; flex-direction: column; gap: 0;
  max-height: 700px; overflow-y: auto;
}

.sv-panel__header {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: var(--tv-space-4); border-bottom: 1px solid var(--tv-border);
  background: var(--tv-bg-soft); position: sticky; top: 0; z-index: 1;
}
.sv-panel__header-text { display: flex; flex-direction: column; gap: 2px; }
.sv-panel__dow  { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; margin: 0; }
.sv-panel__date { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }

.sv-panel__close {
  width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm); background: transparent;
  color: var(--tv-text-secondary); cursor: pointer; flex-shrink: 0; transition: background .15s;
}
.sv-panel__close:hover { background: var(--tv-bg); }

.sv-panel__section { padding: var(--tv-space-3) var(--tv-space-4); display: flex; flex-direction: column; gap: var(--tv-space-2); }
.sv-panel__section + .sv-panel__section { border-top: 1px solid var(--tv-border); }
.sv-panel__section-label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .06em; margin: 0 0 var(--tv-space-1); }

.sv-panel__item {
  display: flex; align-items: flex-start; gap: var(--tv-space-2); padding: var(--tv-space-2) var(--tv-space-3);
  border-radius: var(--tv-radius); border: 1px solid var(--tv-border);
  background: var(--tv-bg-soft); cursor: pointer; text-align: left;
  transition: background .15s; width: 100%;
}
.sv-panel__item:hover { background: var(--tv-bg); }
.sv-panel__item-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px;
}
.sv-panel__item-dot--scheduled { background: var(--tv-primary); }
.sv-panel__item-dot--completed { background: var(--tv-success); }
.sv-panel__item-dot--cancelled { background: var(--tv-neutral); }
.sv-panel__item-dot--missed    { background: var(--tv-danger); }
.sv-panel__item-dot--trial     { background: var(--tv-purple); }
.sv-panel__item-dot--live      { background: var(--tv-success); }

.sv-panel__item-body { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
.sv-panel__item-title  { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.sv-panel__item-meta   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.sv-panel__item-person { font-size: var(--tv-text-xs); color: var(--tv-text-secondary); }

.sv-panel__item-status {
  font-size: 10px; font-weight: var(--tv-font-semibold); white-space: nowrap;
  padding: 1px var(--tv-space-1); border-radius: 3px; flex-shrink: 0; align-self: flex-start;
}
.sv-panel__item-status--scheduled { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }
.sv-panel__item-status--completed { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sv-panel__item-status--cancelled { background: var(--tv-neutral-soft); color: var(--tv-neutral); }
.sv-panel__item-status--missed    { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sv-panel__item-status--trial     { background: var(--tv-purple-soft);  color: var(--tv-purple); }
.sv-panel__item-status--live      { background: var(--tv-success-soft); color: var(--tv-success-fg); }

.sv-panel__slot {
  display: flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-2) var(--tv-space-3); border-radius: var(--tv-radius);
  border: 1px dashed hsl(186,50%,70%); background: var(--tv-teal-soft);
  color: var(--tv-teal-fg); cursor: pointer; font-size: var(--tv-text-sm);
  text-align: left; transition: background .15s; width: 100%;
}
.sv-panel__slot:hover { background: hsl(186,60%,88%); }
.sv-panel__slot-time    { font-weight: var(--tv-font-medium); flex: 1; }
.sv-panel__slot-teacher { font-size: var(--tv-text-xs); color: var(--tv-teal-fg); opacity: .8; }
.sv-panel__slot-book    { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); flex-shrink: 0; }

.sv-panel__empty {
  display: flex; flex-direction: column; align-items: center; gap: var(--tv-space-3);
  padding: var(--tv-space-8) var(--tv-space-4); color: var(--tv-text-muted); text-align: center;
}
.sv-panel__empty-text { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); margin: 0; }
.sv-panel__no-slots   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.sv-panel__add-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-5); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold); background: var(--tv-primary);
  color: var(--tv-text-inverse); border: none; border-radius: var(--tv-radius-sm);
  cursor: pointer; transition: background .15s;
}
.sv-panel__add-btn:hover { background: var(--tv-primary-hover); }

/* panel transition */
.sv-panel-enter-active, .sv-panel-leave-active { transition: opacity .2s, transform .2s; }
.sv-panel-enter-from, .sv-panel-leave-to { opacity: 0; transform: translateX(12px); }

/* ── Responsive ── */
@media (max-width: 1100px) {
  .sv-body--panel { grid-template-columns: 1fr 280px; }
}

@media (max-width: 900px) {
  .sv-page { padding: var(--tv-space-3) var(--tv-space-4); }
  /* panel stacks below calendar */
  .sv-body--panel { grid-template-columns: 1fr; }
  .sv-panel { max-height: 400px; }
  /* panel slide from bottom on mobile */
  .sv-panel-enter-from, .sv-panel-leave-to { opacity: 0; transform: translateY(12px); }
}

@media (max-width: 640px) {
  .sv-header__controls { gap: var(--tv-space-1); }
  .sv-view-btn { padding: var(--tv-space-1) var(--tv-space-2); font-size: var(--tv-text-xs); }
  .sv-month__cell { min-height: 70px; padding: var(--tv-space-1); }
  .sv-week__scroll-wrap { max-height: 540px; }
  .sv-day__scroll-wrap  { max-height: 540px; }
  .sv-period-selects .sv-period-select { font-size: var(--tv-text-xs); padding: 2px var(--tv-space-1); }
}
</style>
