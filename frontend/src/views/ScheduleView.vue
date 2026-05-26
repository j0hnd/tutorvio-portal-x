<template>
  <div class="sv-page">

    <!-- ── Header ── -->
    <div class="sv-header">
      <div class="sv-header__left">
        <h1 class="sv-title">Schedule</h1>
        <span class="sv-tz-badge">{{ timezone }}</span>
      </div>

      <!-- Center: nav + period selects -->
      <div class="sv-header__center">
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
        <div class="sv-period-selects">
          <TVSelect v-model="selectedMonth" :options="monthOptions" class="sv-period-tvselect" />
          <TVSelect v-model="selectedYear"  :options="yearOptions"  class="sv-period-tvselect" />
          <div class="sv-view-switcher" role="group" aria-label="Calendar view">
            <button v-for="v in VIEWS" :key="v.key"
              :class="['sv-view-btn', { 'sv-view-btn--active': currentView === v.key }]"
              type="button" @click="switchView(v.key)">{{ v.label }}</button>
          </div>
        </div>
      </div>

      <!-- Right: teacher filter + action buttons -->
      <div class="sv-header__right">
        <TVSelect
          v-if="showTeacherFilter"
          v-model="filterTeacherId"
          :options="teacherOptions"
          placeholder="All Teachers"
          class="sv-ctrl-tvselect"
        />
        <template v-if="canCreateLesson">
          <button class="sv-add-btn sv-add-btn--ghost" type="button" @click="showHolidayModal = true">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
              <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              <path d="M5 9l2 2 3-3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Block Holiday
          </button>
          <button class="sv-add-btn" type="button" @click="openCreateModal()">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            Add Lesson
          </button>
        </template>
      </div>
    </div>

    <!-- Mobile drawer backdrop -->
    <Transition name="sv-backdrop">
      <div v-if="selectedDate" class="sv-drawer-backdrop" @click="selectedDate = null; selectedHour = null" />
    </Transition>

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
              <span v-if="cell.isUnavailable" class="sv-month__unavail-tag">{{ cell.unavailableLabel }}</span>
              <div class="sv-month__events">
                <template v-for="item in cell.items.slice(0, 4)" :key="item.id">
                  <button
                    v-if="!item.isSlot"
                    :class="['sv-chip', `sv-chip--${statusClass(item.ev.status)}`, { 'sv-chip--trial': item.ev.isTrial }]"
                    type="button" :title="item.ev.title"
                    @click.stop="openLesson(item.ev)"
                  >
                    <span class="sv-chip__time">{{ formatTime(item.ev.startTime) }}</span>
                    <span class="sv-chip__label">{{ item.ev.title }}</span>
                    <span v-if="item.ev.isTrial" class="sv-chip__badge">TRIAL</span>
                    <span v-if="item.ev.isRecurring" class="sv-chip__recur" aria-label="Recurring">↻</span>
                  </button>
                  <button
                    v-else
                    class="sv-chip sv-chip--open-slot" type="button"
                    :title="`Open: ${item.slot.startTime}–${item.slot.endTime}${canManageSlots ? '' : ' · ' + item.slot.teacherName}`"
                    @click.stop="canBook ? openSlot(item.slot) : undefined"
                  >
                    <span class="sv-chip__time">{{ formatSlotHour(item.slot.startTime) }}</span>
                    <span class="sv-chip__label">Open Slot{{ canManageSlots ? '' : ' · ' + item.slot.teacherName }}</span>
                  </button>
                </template>
                <span v-if="cell.items.length > 4" class="sv-month__more">
                  +{{ cell.items.length - 4 }} more
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
                    { 'sv-week__col--selected':    selectedDate === day.dateStr && selectedHour === null },
                  ]"
                >
                  <div
                    v-for="h in HOURS" :key="h"
                    :class="['sv-week__hour-cell',
                      { 'sv-week__hour-cell--selected': selectedDate === day.dateStr && selectedHour === h }
                    ]"
                    @click="selectCell(day.dateStr, h)"
                  >
                    <button
                      v-for="slot in day.slots.filter(s => !s.isBooked && parseInt(s.startTime) === h)" :key="slot.id"
                      class="sv-chip sv-chip--open-slot"
                      type="button"
                      @click.stop="handleSlotBgClick(slot)"
                    >
                      <span class="sv-chip__time">{{ formatSlotHour(slot.startTime) }}</span>
                      <span class="sv-chip__label">Open Slot{{ canManageSlots ? '' : ' · ' + slot.teacherName }}</span>
                    </button>
                    <button
                      v-for="ev in day.events.filter(e => new Date(e.startTime).getHours() === h)" :key="ev.id"
                      :class="['sv-chip', `sv-chip--${statusClass(ev.status)}`]"
                      type="button"
                      @click.stop="openLesson(ev)"
                    >
                      <span class="sv-chip__time">{{ formatTime(ev.startTime) }}</span>
                      <span class="sv-chip__label">{{ ev.title }}</span>
                      <template v-if="roleLabel(ev)"><span class="sv-chip__role"> · {{ roleLabel(ev) }}</span></template>
                      <span v-if="ev.isTrial" class="sv-chip__badge">TRIAL</span>
                      <span v-if="ev.isRecurring" class="sv-chip__recur">↻</span>
                    </button>
                  </div>
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
                <div
                  v-for="h in HOURS" :key="h"
                  :class="['sv-day__hour-cell', { 'sv-day__hour-cell--selected': selectedDate === currentDayStr && selectedHour === h }]"
                  @click="selectCell(currentDayStr, h)"
                >
                  <button
                    v-for="slot in currentDaySlots.filter(s => !s.isBooked && parseInt(s.startTime) === h)" :key="slot.id"
                    class="sv-chip sv-chip--open-slot"
                    type="button"
                    @click.stop="handleSlotBgClick(slot)"
                  >
                    <span class="sv-chip__time">{{ formatSlotHour(slot.startTime) }}</span>
                    <span class="sv-chip__label">Open Slot{{ canManageSlots ? '' : ' · ' + slot.teacherName }}</span>
                  </button>
                  <button
                    v-for="ev in currentDayEvents.filter(e => new Date(e.startTime).getHours() === h)" :key="ev.id"
                    :class="['sv-chip', `sv-chip--${statusClass(ev.status)}`]"
                    type="button"
                    @click.stop="openLesson(ev)"
                  >
                    <span class="sv-chip__time">{{ formatTime(ev.startTime) }}</span>
                    <span class="sv-chip__label">{{ ev.title }}</span>
                    <template v-if="roleLabel(ev)"><span class="sv-chip__role"> · {{ roleLabel(ev) }}</span></template>
                    <span v-if="ev.isTrial" class="sv-chip__badge">TRIAL</span>
                    <span v-if="ev.isRecurring" class="sv-chip__recur">↻</span>
                  </button>
                </div>
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
              <h2 class="sv-panel__date">{{ panelDateLabel }}<template v-if="selectedHour !== null"> · {{ formatHour(selectedHour) }}</template></h2>
            </div>
            <button class="sv-panel__close" type="button" aria-label="Close panel" @click="selectedDate = null; selectedHour = null">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </button>
          </div>

          <!-- Schedules section -->
          <div class="sv-panel__section">
            <div class="sv-panel__section-head">
              <p class="sv-panel__section-label">Schedules</p>
              <button
                v-if="canCreateLesson"
                class="sv-panel__create-btn"
                type="button"
                @click="openCreateModal(selectedDate ?? undefined, selectedHour ?? undefined)"
              >+ Add</button>
            </div>
            <template v-if="panelEvents.length">
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
            </template>
            <div v-else class="sv-panel__empty-inline">
              <svg width="32" height="32" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                <rect x="6" y="8" width="28" height="26" rx="3" stroke="currentColor" stroke-width="1.5" opacity=".35"/>
                <path d="M13 6v4M27 6v4M6 16h28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".35"/>
                <path d="M15 26h10M15 22h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".3"/>
              </svg>
              <span>No schedules for this day.</span>
            </div>
          </div>

          <!-- Available slots section (all roles) -->
          <div v-if="panelOpenSlots.length || canBook" class="sv-panel__section">
            <p class="sv-panel__section-label">
              Available Slots
              <span v-if="panelOpenSlots.length" class="sv-panel__section-count">{{ panelOpenSlots.length }}</span>
            </p>
            <template v-if="panelOpenSlots.length">
              <button
                v-for="slot in panelOpenSlots" :key="slot.id"
                class="sv-panel__slot" type="button"
                @click="canBook ? openSlot(slot) : canManageSlots ? (selectedOpenSlot = slot) : undefined"
              >
                <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M7 4.5V7l2 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
                <span class="sv-panel__slot-time">{{ formatSlotRange(slot.startTime, slot.endTime) }}</span>
                <span class="sv-panel__slot-teacher">{{ slot.teacherName }}</span>
                <span v-if="canBook" class="sv-panel__slot-book">Book →</span>
                <span v-else-if="canManageSlots" class="sv-panel__slot-book">Remove</span>
              </button>
            </template>
            <p v-else class="sv-panel__no-slots">No open slots available on this date.</p>
          </div>

        </aside>
      </Transition>

    </div><!-- /sv-body -->

    <!-- Slot remove confirm (teacher) -->
    <Teleport to="body">
      <div v-if="selectedOpenSlot" class="rsm-backdrop" role="dialog" aria-modal="true" @click.self="selectedOpenSlot = null">
        <div class="rsm-dialog">
          <p class="rsm-title">Remove this open slot?</p>
          <p class="rsm-time">{{ selectedOpenSlot.date }} · {{ selectedOpenSlot.startTime }}–{{ selectedOpenSlot.endTime }}</p>
          <p class="rsm-note">Students will no longer be able to book this slot.</p>
          <div class="rsm-actions">
            <button class="rsm-btn rsm-btn--ghost" type="button" @click="selectedOpenSlot = null">Keep</button>
            <button class="rsm-btn rsm-btn--danger" type="button" @click="handleRemoveSlot">Remove</button>
          </div>
        </div>
      </div>
    </Teleport>

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
    <AdminCreateLessonModal
      v-if="showCreateModal"
      :prefill-date="createPrefillDate"
      :prefill-start="createPrefillStart"
      @close="showCreateModal = false"
      @created="handleCreateLesson"
    />

    <!-- Holiday blocking dialog -->
    <Teleport to="body">
      <div v-if="showHolidayModal" class="hol-backdrop" @click.self="showHolidayModal = false" role="dialog" aria-modal="true">
        <div class="hol-dialog">
          <div class="hol-header">
            <h2 class="hol-title">Block Holiday</h2>
            <button class="hol-close" type="button" @click="showHolidayModal = false" aria-label="Close">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
          </div>
          <p class="hol-desc">Mark a date as a national holiday. All teachers will be marked unavailable on this date.</p>
          <div class="hol-fields">
            <TVDatePicker v-model="holidayDate" label="Date" />
            <div class="hol-field">
              <label class="hol-label" for="hol-label">Holiday Name</label>
              <input id="hol-label" v-model="holidayLabel" type="text" class="hol-input" placeholder="e.g. Independence Day (PH)" />
            </div>
          </div>
          <div class="hol-footer">
            <button class="hol-btn hol-btn--ghost" type="button" @click="showHolidayModal = false">Cancel</button>
            <button class="hol-btn hol-btn--primary" type="button" :disabled="!holidayDate || !holidayLabel.trim()" @click="submitHoliday">
              Block Holiday
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watchEffect, onMounted, onUnmounted } from 'vue'
import { useScheduleStore }  from '@/stores/schedule'
import { useAuthStore }      from '@/stores/auth'
import { useViewAs }         from '@/composables/useViewAs'
import LessonDetailModal          from '@/components/schedule/LessonDetailModal.vue'
import BookingModal               from '@/components/schedule/BookingModal.vue'
import AdminCreateLessonModal     from '@/components/schedule/AdminCreateLessonModal.vue'
import TVSelect                   from '@/components/ui/TVSelect.vue'
import TVDatePicker               from '@/components/ui/TVDatePicker.vue'
import type { ScheduleLesson, AvailabilitySlot } from '@/stores/schedule'

const schedule          = useScheduleStore()
const auth              = useAuthStore()
const { effectiveRole } = useViewAs()

// ---- Config ----
type ViewKey = 'month' | 'week' | 'day'
const VIEWS    = [{ key: 'month' as ViewKey, label: 'Month' }, { key: 'week' as ViewKey, label: 'Week' }, { key: 'day' as ViewKey, label: 'Day' }]
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const HOURS    = Array.from({ length: 19 }, (_, i) => i + 4)   // 4–22
const HOUR_H   = 64   // px per hour
const CAL_START_MIN = 4 * 60  // 4:00 AM = 240 minutes

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']
const YEARS  = Array.from({ length: 10 }, (_, i) => 2023 + i)

// ---- State ----
const currentView     = ref<ViewKey>('month')
const cursor          = ref(new Date())
const filterTeacherId = ref('')
const selectedDate    = ref<string | null>(null)
const selectedHour    = ref<number | null>(null)
const selectedLesson    = ref<ScheduleLesson | null>(null)
const selectedSlot      = ref<AvailabilitySlot | null>(null)
const selectedOpenSlot  = ref<AvailabilitySlot | null>(null)
const showCreateModal    = ref(false)
const createPrefillDate  = ref<string | undefined>(undefined)
const createPrefillStart = ref<string | undefined>(undefined)
const showHolidayModal   = ref(false)
const holidayDate        = ref(new Date().toLocaleDateString('sv-SE'))
const holidayLabel       = ref('')

// ---- Derived ----
const timezone          = computed(() => auth.user?.timezone ?? 'Asia/Manila')
const todayStr          = computed(() => new Date().toLocaleDateString('sv-SE', { timeZone: timezone.value }))
const showTeacherFilter = computed(() =>
  ['ADMIN', 'STAFF'].includes(auth.user?.role ?? '') || effectiveRole.value === 'STUDENT'
)
const canBook           = computed(() => effectiveRole.value === 'STUDENT')
const canManageSlots    = computed(() => effectiveRole.value === 'TEACHER')
const canCreateLesson   = computed(() =>
  ['ADMIN', 'STAFF'].includes(auth.user?.role ?? '') && effectiveRole.value !== 'STUDENT'
)

const cursorMonth = computed(() => cursor.value.getMonth())
const cursorYear  = computed(() => cursor.value.getFullYear())

// TVSelect options
const teacherOptions = computed(() => [
  { value: '', label: 'All Teachers' },
  { value: 'u2', label: 'James Reyes' },
  { value: 'u6', label: 'Sarah Lim' },
  { value: 'u7', label: 'Miguel Santos' },
])
const monthOptions = computed(() =>
  MONTHS.map((m, i) => ({ value: i, label: m })),
)
const yearOptions = computed(() =>
  YEARS.map(y => ({ value: y, label: String(y) })),
)
// Writable wrappers for TVSelect v-model
const selectedMonth = computed({
  get: () => cursorMonth.value,
  set: (v: string | number) => setCursorMonth(Number(v)),
})
const selectedYear = computed({
  get: () => cursorYear.value,
  set: (v: string | number) => setCursorYear(Number(v)),
})

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
  const d = new Date(iso)
  const h = d.getHours()
  const m = d.getMinutes()
  const base = h === 0 ? 12 : h > 12 ? h - 12 : h
  const suffix = h < 12 ? 'am' : 'pm'
  return m ? `${base}:${String(m).padStart(2, '0')}${suffix}` : `${base}${suffix}`
}

function formatSlotRange(start: string, end: string): string {
  return `${formatSlotHour(start)} – ${formatSlotHour(end)}`
}

function formatSlotHour(hhmm: string): string {
  const h = parseInt(hhmm.split(':')[0], 10)
  if (h === 0) return '12am'
  if (h < 12) return `${h}am`
  if (h === 12) return '12pm'
  return `${h - 12}pm`
}

function formatHour(h: number): string {
  return h < 12 ? `${h} AM` : h === 12 ? '12 PM' : `${h - 12} PM`
}

function statusClass(status: string): string {
  switch (status) {
    case 'COMPLETED':            return 'completed'
    case 'CANCELLED':            return 'cancelled'
    case 'MISSED_BY_STUDENT':
    case 'MISSED_BY_TEACHER':    return 'missed'
    case 'TRIAL':                return 'trial'
    case 'IN_PROGRESS':          return 'live'
    case 'RESCHEDULED':          return 'rescheduled'
    case 'PENDING_CONFIRMATION': return 'pending'
    default:                     return 'scheduled'
  }
}

function statusLabel(status: string): string {
  switch (status) {
    case 'SCHEDULED':            return 'Scheduled'
    case 'IN_PROGRESS':          return 'Live'
    case 'COMPLETED':            return 'Completed'
    case 'CANCELLED':            return 'Cancelled'
    case 'MISSED_BY_STUDENT':    return 'Missed'
    case 'MISSED_BY_TEACHER':    return 'Tchr missed'
    case 'TRIAL':                return 'Trial'
    case 'RESCHEDULED':          return 'Rescheduled'
    case 'PENDING_CONFIRMATION': return 'Pending Confirmation'
    default:                     return status
  }
}

function isUnavailableDate(dateStr: string): boolean {
  return schedule.myUnavailableDates.some(u => u.date === dateStr)
}

function getUnavailableLabel(dateStr: string): string {
  return schedule.myUnavailableDates.find(u => u.date === dateStr)?.label ?? 'Unavailable'
}

function eventsForDate(dateStr: string): ScheduleLesson[] {
  return schedule.myLessons
    .filter(l => toDateStr(new Date(l.startTime)) === dateStr)
    .sort((a, b) => isoToMinutes(a.startTime) - isoToMinutes(b.startTime))
}

function slotsForDate(dateStr: string): AvailabilitySlot[] {
  return schedule.myAvailabilitySlots
    .filter(s => s.date === dateStr)
    .sort((a, b) => timeToMinutes(a.startTime) - timeToMinutes(b.startTime))
}

type MonthItem =
  | { isSlot: false; id: string; sortMin: number; ev: ScheduleLesson }
  | { isSlot: true;  id: string; sortMin: number; slot: AvailabilitySlot }

function monthItemsForDate(dateStr: string): MonthItem[] {
  const events = eventsForDate(dateStr).map(ev => ({
    isSlot: false as const, id: ev.id, sortMin: isoToMinutes(ev.startTime), ev,
  }))
  const slots = slotsForDate(dateStr).filter(s => !s.isBooked).map(slot => ({
    isSlot: true as const, id: slot.id, sortMin: timeToMinutes(slot.startTime), slot,
  }))
  return [...events, ...slots].sort((a, b) => a.sortMin - b.sortMin)
}

function roleLabel(lesson: ScheduleLesson): string {
  return effectiveRole.value === 'TEACHER' ? lesson.studentName : lesson.teacherName
}

// ---- Navigation ----
function switchView(v: ViewKey): void {
  if (selectedDate.value && v !== 'month') {
    cursor.value = new Date(`${selectedDate.value}T12:00:00`)
  }
  currentView.value = v
}

function navigate(dir: 1 | -1): void {
  const d = new Date(cursor.value)
  if (currentView.value === 'month') d.setMonth(d.getMonth() + dir)
  else if (currentView.value === 'week') d.setDate(d.getDate() + dir * 7)
  else d.setDate(d.getDate() + dir)
  cursor.value = d
  selectedDate.value = null
}

function goToday(): void {
  cursor.value = new Date()
  selectedDate.value = null
}

// ---- Selected date (side panel) ----
function selectDate(dateStr: string): void {
  if (selectedDate.value === dateStr && selectedHour.value === null) {
    selectedDate.value = null
  } else {
    selectedDate.value = dateStr
    selectedHour.value = null
  }
}

function selectCell(dateStr: string, hour: number): void {
  if (selectedDate.value === dateStr && selectedHour.value === hour) {
    selectedDate.value = null
    selectedHour.value = null
  } else {
    selectedDate.value = dateStr
    selectedHour.value = hour
  }
}

const panelEvents = computed((): ScheduleLesson[] => {
  if (!selectedDate.value) return []
  const all = eventsForDate(selectedDate.value)
  if (selectedHour.value === null) return all
  return all.filter(e => new Date(e.startTime).getHours() === selectedHour.value)
})

const panelOpenSlots = computed((): AvailabilitySlot[] => {
  if (!selectedDate.value) return []
  const all = slotsForDate(selectedDate.value).filter(s => !s.isBooked)
  if (selectedHour.value === null) return all
  return all.filter(s => parseInt(s.startTime) === selectedHour.value)
})

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
  const today      = todayStr.value
  const gridStart  = startOfWeek(first)
  return Array.from({ length: 42 }, (_, i) => {
    const d       = addDays(gridStart, i)
    const dateStr = toDateStr(d)
    return {
      day:              d.getDate(),
      dateStr,
      inMonth:          d.getMonth() === month,
      isToday:          dateStr === today,
      isUnavailable:    isUnavailableDate(dateStr),
      unavailableLabel: isUnavailableDate(dateStr) ? getUnavailableLabel(dateStr) : '',
      items:            monthItemsForDate(dateStr),
    }
  })
})

// ---- Week grid ----
const weekDays = computed(() => {
  const s     = startOfWeek(cursor.value)
  const today = todayStr.value
  return Array.from({ length: 7 }, (_, i) => {
    const d       = addDays(s, i)
    const dateStr = toDateStr(d)
    const events  = eventsForDate(dateStr)
    const slots   = slotsForDate(dateStr)
    return { dow: WEEKDAYS[d.getDay()], date: d.getDate(), dateStr,
      isToday: dateStr === today, isUnavailable: isUnavailableDate(dateStr),
      events, slots, layout: computeDayLayout(events, slots) }
  })
})

// ---- Day view ----
const currentDayStr    = computed(() => toDateStr(cursor.value))
const isDayToday       = computed(() => currentDayStr.value === todayStr.value)
const dayUnavailable   = computed(() => isUnavailableDate(currentDayStr.value))
const currentDayEvents = computed(() => eventsForDate(currentDayStr.value))
const currentDaySlots  = computed(() => slotsForDate(currentDayStr.value))
const currentDayLayout = computed(() => computeDayLayout(currentDayEvents.value, currentDaySlots.value))
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
// ---- Overlap layout (side-by-side columns for concurrent events/slots) ----
type LayoutPos = { left: string; width: string; right: string }

function computeDayLayout(
  events: ScheduleLesson[],
  slots: AvailabilitySlot[],
): Map<string, LayoutPos> {
  const blocks = [
    ...events.map(e => ({ id: `e_${e.id}`, start: isoToMinutes(e.startTime),  end: isoToMinutes(e.endTime) })),
    ...slots.map(s  => ({ id: `s_${s.id}`, start: timeToMinutes(s.startTime), end: timeToMinutes(s.endTime) })),
  ].sort((a, b) => a.start - b.start)

  const result = new Map<string, LayoutPos>()
  if (!blocks.length) return result

  // Greedy track assignment
  const tracks: number[] = []
  const assigned: { id: string; track: number; start: number; end: number }[] = []
  for (const b of blocks) {
    const t = tracks.findIndex(end => end <= b.start)
    const track = t === -1 ? tracks.length : t
    if (t === -1) tracks.push(b.end); else tracks[t] = b.end
    assigned.push({ ...b, track })
  }

  // For each block compute width based on max concurrent tracks during its span
  for (const a of assigned) {
    const concurrent = assigned.filter(o => o.start < a.end && o.end > a.start)
    const maxTrack = Math.max(...concurrent.map(c => c.track)) + 1
    result.set(a.id, {
      left:  `${(a.track / maxTrack) * 100}%`,
      width: `${(1 / maxTrack) * 100}%`,
      right: 'auto',
    })
  }
  return result
}

function eventStyle(ev: ScheduleLesson, layout?: Map<string, LayoutPos>): Record<string, string> {
  const top = ((isoToMinutes(ev.startTime) - CAL_START_MIN) / 60) * HOUR_H
  const pos = layout?.get(`e_${ev.id}`)
  return { top: `${top}px`, left: pos?.left ?? '0', right: pos?.right ?? '0', width: pos?.width ?? 'auto' }
}
function slotStyle(slot: AvailabilitySlot, layout?: Map<string, LayoutPos>): Record<string, string> {
  const top = ((timeToMinutes(slot.startTime) - CAL_START_MIN) / 60) * HOUR_H
  const pos = layout?.get(`s_${slot.id}`)
  return { top: `${top}px`, left: pos?.left ?? '0', right: pos?.right ?? '0', width: pos?.width ?? 'auto' }
}

// ---- Now line ----
const nowTick = ref(Date.now())
let nowTimer: ReturnType<typeof setInterval>
onMounted(() => { nowTimer = setInterval(() => { nowTick.value = Date.now() }, 60_000) })
onUnmounted(() => clearInterval(nowTimer))

const nowLineStyle = computed(() => {
  void nowTick.value
  const now = new Date()
  const top = ((now.getHours() * 60 + now.getMinutes() - CAL_START_MIN) / 60) * HOUR_H
  return { top: `${top}px` }
})

// ---- Modal handlers ----
function openLesson(lesson: ScheduleLesson): void { selectedLesson.value = lesson }

function openSlot(slot: AvailabilitySlot): void {
  if (!canBook.value) return
  selectedSlot.value = slot
}

function handleSlotBgClick(slot: AvailabilitySlot): void {
  if (slot.isBooked) return
  if (canBook.value)         openSlot(slot)
  else if (canManageSlots.value) selectedOpenSlot.value = slot
}

function handleRemoveSlot(): void {
  if (!selectedOpenSlot.value) return
  schedule.removeAvailabilitySlot(selectedOpenSlot.value.id)
  selectedOpenSlot.value = null
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

function openCreateModal(dateStr?: string, hour?: number): void {
  createPrefillDate.value  = dateStr
  createPrefillStart.value = hour !== undefined ? `${String(hour).padStart(2, '0')}:00` : undefined
  showCreateModal.value = true
}

function submitHoliday(): void {
  if (!holidayDate.value || !holidayLabel.value.trim()) return
  schedule.blockHoliday(holidayDate.value, holidayLabel.value.trim())
  showHolidayModal.value = false
  holidayLabel.value = ''
}

function handleCreateLesson(payload: {
  teacherId: string; teacherName: string
  studentId: string; studentName: string
  date: string; startTime: string; endTime: string
  subject: string; isTrial: boolean; isRecurring: boolean
}): void {
  schedule.createLesson(
    payload.teacherId, payload.teacherName,
    payload.studentId, payload.studentName,
    payload.date, payload.startTime, payload.endTime,
    payload.subject, payload.isTrial, payload.isRecurring,
  )
  showCreateModal.value = false
}
</script>

<style scoped>
/* ── Page ── */
.sv-page {
  padding: var(--tv-space-4) var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  height: 100%;
  box-sizing: border-box;
}

/* ── Header ── */
.sv-header {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: var(--tv-space-3);
}
.sv-header__left  { display: flex; align-items: center; gap: var(--tv-space-3); }
.sv-header__center { display: flex; align-items: center; gap: var(--tv-space-2); justify-content: center; }
.sv-header__right  { display: flex; align-items: center; gap: var(--tv-space-2); justify-content: flex-end; flex-wrap: wrap; }

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

.sv-ctrl-tvselect { min-width: 140px; }
.sv-period-tvselect { min-width: 100px; }

.sv-add-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  padding: 0 var(--tv-space-3); height: 42px;
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  color: var(--tv-text-inverse); background: var(--tv-primary);
  border: 1px solid transparent; border-radius: var(--tv-radius); cursor: pointer;
  transition: background 0.15s; white-space: nowrap;
}
.sv-add-btn:hover { background: var(--tv-primary-hover); }
.sv-add-btn--ghost {
  background: transparent;
  border-color: var(--tv-border);
  color: var(--tv-text-secondary);
}
.sv-add-btn--ghost:hover { background: var(--tv-bg-soft); }

/* Holiday dialog */
.hol-backdrop {
  position: fixed; inset: 0; z-index: 1000;
  background: hsla(215,25%,10%,.45);
  display: flex; align-items: center; justify-content: center;
  padding: var(--tv-space-4); backdrop-filter: blur(2px);
}
.hol-dialog {
  background: var(--tv-bg-card); border-radius: var(--tv-radius-lg);
  box-shadow: var(--tv-shadow-lg); width: 100%; max-width: 420px;
  padding: var(--tv-space-6); display: flex; flex-direction: column; gap: var(--tv-space-4);
}
.hol-header { display: flex; align-items: center; justify-content: space-between; }
.hol-title { font-size: var(--tv-text-lg); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.hol-close {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm);
  background: transparent; color: var(--tv-text-secondary); cursor: pointer; transition: background .15s;
}
.hol-close:hover { background: var(--tv-bg-soft); }
.hol-desc { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); margin: 0; }
.hol-fields { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.hol-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.hol-label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium); color: var(--tv-text-secondary); }
.hol-input {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm);
  outline: none; font-family: inherit; box-sizing: border-box; width: 100%;
  transition: border-color .15s;
}
.hol-input:focus { border-color: var(--tv-primary); }
.hol-footer { display: flex; justify-content: flex-end; gap: var(--tv-space-2); }
.hol-btn {
  display: inline-flex; align-items: center; padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  border-radius: var(--tv-radius-sm); border: 1px solid transparent; cursor: pointer; transition: background .15s, opacity .15s;
}
.hol-btn:disabled { opacity: .45; cursor: not-allowed; }
.hol-btn--primary { background: var(--tv-primary); color: var(--tv-text-inverse); }
.hol-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.hol-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.hol-btn--ghost:hover { background: var(--tv-bg-soft); }

.sv-view-switcher { display: flex; border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius); overflow: hidden; height: 42px; }
.sv-view-btn {
  padding: 0 var(--tv-space-4); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium); color: var(--tv-text-secondary);
  background: var(--tv-bg-card); border: none; cursor: pointer; transition: background .15s, color .15s;
  height: 100%;
}
.sv-view-btn + .sv-view-btn { border-left: 1.5px solid var(--tv-border); }
.sv-view-btn--active { background: var(--tv-primary); color: var(--tv-text-inverse); }

.sv-nav { display: flex; align-items: center; gap: var(--tv-space-1); }
.sv-nav-btn {
  width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  background: var(--tv-bg-card); color: var(--tv-text-secondary); cursor: pointer; transition: background .15s;
}
.sv-nav-btn:hover { background: var(--tv-bg-soft); }
.sv-today-btn {
  padding: 0 var(--tv-space-4); font-size: var(--tv-text-sm); height: 42px;
  font-weight: var(--tv-font-medium); border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius); background: var(--tv-bg-card);
  color: var(--tv-text-secondary); cursor: pointer; transition: background .15s;
}
.sv-today-btn:hover { background: var(--tv-bg-soft); }

.sv-period-selects { display: flex; align-items: center; gap: var(--tv-space-1); }

/* ── Body layout ── */
.sv-body {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--tv-space-4);
  align-items: stretch;
  min-width: 0;
  flex: 1;
  min-height: 0;
}
.sv-body--panel { grid-template-columns: 1fr 310px; }

.sv-cal { min-width: 0; display: flex; flex-direction: column; height: 100%; }

/* ── Month view ── */
.sv-month {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
  /* Single unified grid so header and cells share identical column widths */
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
}
.sv-month__weekdays {
  display: contents; /* children participate directly in parent grid */
}
.sv-month__wday {
  padding: var(--tv-space-2) 0; text-align: center; font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold); color: var(--tv-text-muted);
  text-transform: uppercase; letter-spacing: .05em;
  background: var(--tv-bg-soft); border-bottom: 1px solid var(--tv-border);
}
.sv-month__grid { display: contents; }
.sv-month__cell {
  min-height: 140px; padding: var(--tv-space-2); cursor: pointer;
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
.sv-month__unavail-tag {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 10px; font-weight: var(--tv-font-medium);
  color: var(--tv-danger-fg); padding: 1px 5px; border-radius: 3px;
  background: var(--tv-danger-soft); border: 1px solid var(--tv-danger-border);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
  align-self: flex-start;
}

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
.sv-chip__role { opacity: .75; overflow: hidden; text-overflow: ellipsis; flex-shrink: 1; min-width: 0; }

/* Absolutely-positioned chip inside week/day time grid */
.sv-grid-chip {
  position: absolute;
  z-index: 2;
  min-width: 0;
}
.sv-chip--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }
.sv-chip--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sv-chip--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); text-decoration: line-through; }
.sv-chip--missed      { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sv-chip--trial       { background: var(--tv-purple-soft);  color: var(--tv-purple); }
.sv-chip--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sv-chip--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border: 1px solid hsl(24,70%,70%); }
.sv-chip--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); border: 1px dashed hsl(220,52%,65%); }
.sv-chip--open-slot   { background: hsl(38,90%,94%); color: hsl(38,70%,32%); border: 1px dashed hsl(38,65%,65%); }

/* ── Week view ── */
.sv-week {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
  display: flex; flex-direction: column; flex: 1; min-height: 0;
}
.sv-week__scroll-wrap { overflow: auto; flex: 1; min-height: 0; }
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
.sv-week__col { border-right: 1px solid var(--tv-border); position: relative; }
.sv-week__col:last-child { border-right: none; }
.sv-week__col--unavailable { background: repeating-linear-gradient(135deg, transparent, transparent 4px, hsl(0,72%,97%) 4px, hsl(0,72%,97%) 5px); }
.sv-week__col--selected { background: var(--tv-primary-soft); }
.sv-week__col--selected.sv-week__col--unavailable { background: repeating-linear-gradient(135deg, var(--tv-primary-soft), var(--tv-primary-soft) 4px, hsl(0,72%,97%) 4px, hsl(0,72%,97%) 5px); }
.sv-week__hour-cell {
  height: 64px; border-bottom: 1px solid var(--tv-border); box-sizing: border-box;
  display: flex; flex-direction: column; gap: 2px; padding: 4px 3px 10px;
  overflow: hidden; cursor: pointer;
}
.sv-week__hour-cell:hover { background: var(--tv-bg); }
.sv-week__hour-cell--selected { background: var(--tv-primary-soft) !important; }

.sv-week__slot-bg, .sv-day__slot-bg {
  position: absolute; left: 0; right: 0;
  background: hsl(38,88%,93%);
  border-left: 3px solid hsl(38,70%,58%);
  pointer-events: auto; cursor: pointer;
  opacity: .85; transition: opacity .15s; z-index: 1;
  display: flex; flex-direction: column; justify-content: flex-start;
  padding: 3px 6px; overflow: hidden; gap: 1px;
}
.sv-week__slot-bg:hover, .sv-day__slot-bg:hover { opacity: 1; }
.sv-week__slot-bg--booked, .sv-day__slot-bg--booked {
  background: var(--tv-neutral-soft); border-left: none; cursor: default; opacity: .5;
}
.sv-slot-bg__header {
  font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
  color: hsl(38,65%,30%); line-height: 1.2; white-space: nowrap; user-select: none;
}
.sv-slot-bg__meta {
  font-size: 10px; font-weight: 400;
  color: hsl(38,55%,38%); line-height: 1.2; white-space: nowrap; overflow: hidden;
  text-overflow: ellipsis; user-select: none;
}

.sv-week__event, .sv-day__event {
  position: absolute; left: 0; right: 0; border-radius: 0;
  padding: 3px 6px; border: none; text-align: left; cursor: pointer; z-index: 2;
  overflow: hidden; transition: opacity .15s, transform .1s;
  display: flex; flex-direction: column; gap: 1px;
}
.sv-week__event:hover, .sv-day__event:hover { opacity: .9; transform: scale(1.01); }
.sv-week__event-title, .sv-day__event-title { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sv-week__event-meta, .sv-day__event-meta { font-size: 10px; opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sv-week__event-badge, .sv-day__event-badge, .sv-week__event-recur, .sv-day__event-recur { font-size: 9px; opacity: .8; }

.sv-week__event--scheduled,   .sv-day__event--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 35%); border-left: 3px solid var(--tv-primary); }
.sv-week__event--completed,   .sv-day__event--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border-left: 3px solid var(--tv-success); }
.sv-week__event--cancelled,   .sv-day__event--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-left: 3px solid var(--tv-neutral-border); text-decoration: line-through; }
.sv-week__event--missed,      .sv-day__event--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-left: 3px solid var(--tv-danger); }
.sv-week__event--trial,       .sv-day__event--trial       { background: var(--tv-purple-soft); color: var(--tv-purple); border-left: 3px solid var(--tv-purple); }
.sv-week__event--live,        .sv-day__event--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); border-left: 3px solid var(--tv-success); animation: pulse-ev 1.5s ease-in-out infinite; }
.sv-week__event--rescheduled, .sv-day__event--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border-left: 3px solid hsl(24,75%,55%); }
.sv-week__event--pending,     .sv-day__event--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); border-left: 3px dashed hsl(220,52%,58%); }
@keyframes pulse-ev { 0%,100% { opacity:1 } 50% { opacity:.7 } }

.sv-week__now-line, .sv-day__now-line { position: absolute; left:0; right:0; height:2px; background:var(--tv-danger); z-index:3; pointer-events:none; }
.sv-week__now-line::before, .sv-day__now-line::before { content:''; position:absolute; left:-5px; top:-4px; width:10px; height:10px; background:var(--tv-danger); border-radius:50%; }

/* ── Day view ── */
.sv-day {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
  display: flex; flex-direction: column; flex: 1; min-height: 0;
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

.sv-day__scroll-wrap { overflow: auto; flex: 1; min-height: 0; }
.sv-day__inner { display: grid; grid-template-columns: 52px 1fr; min-width: 320px; }
.sv-day__time-col { border-right: 1px solid var(--tv-border); background: var(--tv-bg-soft); }
.sv-day__hour-label { height: 64px; display: flex; align-items: flex-start; justify-content: flex-end; padding-right: var(--tv-space-2); padding-top: 2px; font-size: 11px; color: var(--tv-text-muted); border-bottom: 1px solid var(--tv-border); box-sizing: border-box; }
.sv-day__col { position: relative; }
.sv-day__col--unavailable { background: repeating-linear-gradient(135deg, transparent, transparent 4px, hsl(0,72%,97%) 4px, hsl(0,72%,97%) 5px); }
.sv-day__hour-cell {
  height: 64px; border-bottom: 1px solid var(--tv-border); box-sizing: border-box;
  display: flex; flex-direction: row; flex-wrap: wrap; align-content: flex-start;
  gap: 4px; padding: 4px 8px 10px; overflow: hidden; cursor: pointer;
}
.sv-day__hour-cell:hover { background: var(--tv-bg); }
.sv-day__hour-cell--selected { background: var(--tv-primary-soft) !important; }

/* ── Side panel ── */
.sv-panel {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); overflow: hidden;
  display: flex; flex-direction: column; gap: 0;
  height: 100%; overflow-y: auto;
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
.sv-panel__section-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: var(--tv-space-1);
}
.sv-panel__section-head .sv-panel__section-label { margin-bottom: 0; }
.sv-panel__create-btn {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  color: var(--tv-primary); background: transparent; border: none;
  cursor: pointer; padding: 0; line-height: 1;
}
.sv-panel__create-btn:hover { text-decoration: underline; }
.sv-panel__section-label {
  display: flex; align-items: center; gap: var(--tv-space-1);
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted);
  text-transform: uppercase; letter-spacing: .06em; margin: 0 0 var(--tv-space-1);
}
.sv-panel__section-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 18px; height: 18px; padding: 0 5px;
  font-size: 10px; font-weight: var(--tv-font-semibold);
  background: var(--tv-teal-soft); color: var(--tv-teal-fg);
  border-radius: var(--tv-radius-full); line-height: 1;
}
.sv-panel__empty-inline {
  display: flex; flex-direction: column; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-4) var(--tv-space-2); color: var(--tv-text-muted); text-align: center;
}
.sv-panel__empty-inline span { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

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
.sv-panel__item-dot--scheduled   { background: var(--tv-primary); }
.sv-panel__item-dot--completed   { background: var(--tv-success); }
.sv-panel__item-dot--cancelled   { background: var(--tv-neutral); }
.sv-panel__item-dot--missed      { background: var(--tv-danger); }
.sv-panel__item-dot--trial       { background: var(--tv-purple); }
.sv-panel__item-dot--live        { background: var(--tv-success); }
.sv-panel__item-dot--rescheduled { background: hsl(24,75%,55%); }
.sv-panel__item-dot--pending     { background: transparent; border: 2px dashed hsl(220,52%,58%); }

.sv-panel__item-body { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
.sv-panel__item-title  { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.sv-panel__item-meta   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.sv-panel__item-person { font-size: var(--tv-text-xs); color: var(--tv-text-secondary); }

.sv-panel__item-status {
  font-size: 10px; font-weight: var(--tv-font-semibold); white-space: nowrap;
  padding: 1px var(--tv-space-1); border-radius: 3px; flex-shrink: 0; align-self: flex-start;
}
.sv-panel__item-status--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }
.sv-panel__item-status--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sv-panel__item-status--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); }
.sv-panel__item-status--missed      { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sv-panel__item-status--trial       { background: var(--tv-purple-soft);  color: var(--tv-purple); }
.sv-panel__item-status--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sv-panel__item-status--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); }
.sv-panel__item-status--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); }

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

/* ── Remove slot dialog ── */
.rsm-backdrop {
  position: fixed; inset: 0; background: hsla(215,25%,10%,.4);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000; backdrop-filter: blur(2px);
}
.rsm-dialog {
  background: var(--tv-bg-card); border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-lg); padding: var(--tv-space-5);
  width: 100%; max-width: 320px; display: flex; flex-direction: column; gap: var(--tv-space-2);
}
.rsm-title  { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.rsm-time   { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text-secondary); margin: 0; }
.rsm-note   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0 0 var(--tv-space-1); }
.rsm-actions { display: flex; gap: var(--tv-space-2); justify-content: flex-end; }
.rsm-btn {
  padding: var(--tv-space-2) var(--tv-space-4); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium); border-radius: var(--tv-radius-sm);
  border: 1px solid transparent; cursor: pointer; transition: background .15s;
}
.rsm-btn--ghost { background: var(--tv-bg-soft); border-color: var(--tv-border); color: var(--tv-text-secondary); }
.rsm-btn--ghost:hover { background: var(--tv-bg); }
.rsm-btn--danger { background: var(--tv-danger); color: #fff; border-color: var(--tv-danger); }
.rsm-btn--danger:hover { filter: brightness(.9); }

/* ── Responsive ── */
@media (max-width: 1200px) and (min-width: 768px) {
  .sv-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: var(--tv-space-3);
  }
  .sv-header__left {
    grid-column: 1;
  }
  .sv-header__right {
    grid-column: 2;
    justify-content: flex-end;
  }
  .sv-header__center {
    grid-column: 1 / -1;
    justify-content: center;
    width: 100%;
    border-top: 1px solid var(--tv-border);
    padding-top: var(--tv-space-3);
    flex-wrap: wrap;
  }
}

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

/* Timezone badge: mobile variant hidden by default */
.sv-tz-badge--mobile { display: none; }
/* Drawer backdrop hidden on desktop */
.sv-drawer-backdrop { display: none; }

@media (max-width: 767px) {
  /* Hide mobile tz badge duplicate (we show desktop badge via left-section which is now first) */
  .sv-tz-badge--mobile { display: none; }

  /* Header stacks: title+tz (left) first, then actions (right), then nav+period (center) */
  .sv-header {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: var(--tv-space-2);
  }
  .sv-header__left   { order: -2; justify-content: space-between; }
  .sv-header__right  { order: -1; }
  .sv-header__center { order: 0; }

  .sv-header__center {
    flex-direction: column;
    align-items: stretch;
    gap: var(--tv-space-2);
  }
  .sv-nav { justify-content: center; }
  .sv-period-selects {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: var(--tv-space-1);
    align-items: center;
  }
  .sv-period-tvselect { min-width: 0; width: 100%; }
  .sv-view-switcher { height: 38px; }
  .sv-view-btn { padding: 0 var(--tv-space-2); font-size: var(--tv-text-xs); }

  /* Right: teacher select full-width, buttons in 2-col grid */
  .sv-header__right {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--tv-space-1);
  }
  .sv-ctrl-tvselect { grid-column: 1 / -1; min-width: 0; }
  .sv-add-btn { justify-content: center; padding: 0 var(--tv-space-2); height: 38px; font-size: var(--tv-text-xs); }

  /* Calendar: display:block breaks the grid min-width:0 constraint, enabling true scroll */
  .sv-body, .sv-body--panel { display: block; }
  .sv-body { overflow-x: visible; }
  .sv-cal  { min-width: 0; width: 100%; }

  /* Month cell adjustments for compact mobile view */
  .sv-month {
    grid-template-columns: repeat(7, minmax(0, 1fr));
  }
  .sv-month__cell {
    min-height: 64px;
    padding: var(--tv-space-1);
    align-items: center;
  }
  .sv-month__day {
    align-self: center;
    font-size: var(--tv-text-xs);
  }
  .sv-month__cell--today .sv-month__day {
    width: 20px;
    height: 20px;
    font-size: 10px;
  }
  .sv-month__events {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 3px;
    justify-content: center;
    margin-top: 2px;
    width: 100%;
  }
  .sv-month__unavail-tag {
    font-size: 8px;
    padding: 0 2px;
    max-width: 100%;
    align-self: center;
  }
  .sv-chip {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    padding: 0;
    min-width: 0;
    border: none !important;
  }
  .sv-chip__time,
  .sv-chip__label,
  .sv-chip__badge,
  .sv-chip__recur,
  .sv-chip__role {
    display: none;
  }
  .sv-month__more {
    font-size: 8px;
    margin-left: 1px;
    line-height: 1;
  }

  /* Week/day scroll */
  .sv-week__scroll-wrap { min-height: 380px; }
  .sv-day__scroll-wrap  { min-height: 380px; }

  /* Side panel → full-screen overlay */
  .sv-panel {
    position: fixed;
    inset: 0;
    border-radius: 0;
    border: none;
    box-shadow: none;
    z-index: var(--tv-z-modal);
    max-height: unset;
    height: 100%;
  }
  .sv-panel-enter-from, .sv-panel-leave-to { opacity: 1; transform: translateY(100%); }
  .sv-panel-enter-active, .sv-panel-leave-active { transition: transform 0.28s ease; opacity: 1; }

  /* Drawer backdrop */
  .sv-drawer-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    background: hsla(215, 25%, 10%, 0.45);
    z-index: calc(var(--tv-z-modal) - 1);
    backdrop-filter: blur(2px);
  }
  .sv-backdrop-enter-active, .sv-backdrop-leave-active { transition: opacity 0.22s ease; }
  .sv-backdrop-enter-from, .sv-backdrop-leave-to { opacity: 0; }
}
</style>
