<template>
  <div class="av-page">

    <!-- Header -->
    <div class="av-header">
      <div class="av-header__left">
        <h1 class="av-title">Availability</h1>
        <p class="av-subtitle">Set your weekly recurring schedule and mark days off</p>
      </div>
      <div class="av-header__right">
        <!-- Admin teacher selector + timezone -->
        <div v-if="isAdmin" class="av-teacher-block">
          <TVSelect
            v-model="selectedTeacher"
            :options="[{ value: '', label: 'Select a teacher' }, ...teacherOptions]"
            placeholder="Select a teacher"
            aria-label="Select teacher"
            class="av-teacher-tvselect"
          />
          <span class="av-tz-badge">{{ timezone }}</span>
        </div>
        <span v-else class="av-tz-badge">{{ timezone }}</span>
      </div>
    </div>

  <!-- No teacher selected prompt (admin only) -->
  <div v-if="isAdmin && !teacherId" class="av-empty-prompt">
    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" aria-hidden="true">
      <circle cx="15" cy="12" r="5" stroke="currentColor" stroke-width="1.6"/>
      <path d="M4 30c0-5.523 4.925-9 11-9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      <circle cx="27" cy="24" r="6.5" stroke="currentColor" stroke-width="1.6"/>
      <path d="M27 21v3.5l2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <p>Select a teacher above to view and manage their availability.</p>
  </div>

  <!-- Two-column layout -->
  <div v-else class="av-columns">

    <!-- Column 1: Weekly Recurring Availability -->
    <section class="av-card av-col">
      <div class="av-card__header">
        <h2 class="av-card__title">Weekly Recurring Availability</h2>
        <p class="av-card__desc">Click a cell to toggle availability for that hour on that day of week.</p>
      </div>

      <div class="av-grid-wrap">
        <div class="av-grid" :style="`grid-template-columns: 56px repeat(${WEEKDAYS.length}, 1fr)`">
          <div class="av-grid__corner" />
          <div v-for="day in WEEKDAYS" :key="day.key" class="av-grid__day-head">{{ day.label }}</div>

          <template v-for="hour in HOURS" :key="hour">
            <div class="av-grid__hour-label">{{ formatHour(hour) }}</div>
            <button
              v-for="day in WEEKDAYS"
              :key="`${day.key}-${hour}`"
              :class="['av-grid__cell', { 'av-grid__cell--active': isActive(day.key, hour), 'av-grid__cell--booked': isBooked(day.key, hour) }]"
              type="button"
              :aria-label="`${isActive(day.key, hour) ? 'Remove' : 'Add'} availability: ${day.label} at ${formatHour(hour)}`"
              :disabled="isBooked(day.key, hour)"
              :title="isBooked(day.key, hour) ? 'This slot has bookings and cannot be removed' : undefined"
              @click="toggleSlot(day.key, hour)"
            >
              <svg v-if="isBooked(day.key, hour)" width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true" class="av-grid__booked-icon">
                <circle cx="5" cy="5" r="4" fill="currentColor" opacity="0.5"/>
              </svg>
              <svg v-else-if="isActive(day.key, hour)" width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                <path d="M2 5l2.5 2.5L8 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </template>
        </div>
      </div>

      <div class="av-legend">
        <span class="av-legend__item av-legend__item--available">Available</span>
        <span class="av-legend__item av-legend__item--booked">Booked (read-only)</span>
        <span class="av-legend__item av-legend__item--empty">Unavailable</span>
      </div>
    </section>

    <!-- Column 2: Unavailability + Upcoming Booked -->
    <div class="av-col av-col--right">

      <!-- Date-Specific Unavailability -->
      <section class="av-card">
        <div class="av-card__header">
          <h2 class="av-card__title">Date-Specific Unavailability</h2>
          <p class="av-card__desc">Mark full days when you are unavailable regardless of weekly schedule.</p>
        </div>

        <form class="av-unavail-form" @submit.prevent="addUnavailable">
          <div class="av-unavail-fields">
            <div class="av-field">
              <TVDatePicker v-model="newUnavailDate" label="Date" :min="minDate" required />
            </div>
            <div class="av-field">
              <TVSelect v-model="newUnavailReason" :options="reasonOptions" label="Reason" />
            </div>
            <div class="av-field av-field--grow">
              <label class="av-label" for="av-label-input">Label</label>
              <input
                id="av-label-input"
                v-model="newUnavailLabel"
                type="text"
                class="av-input"
                placeholder="e.g. Family trip, Independence Day…"
                required
              />
            </div>
            <button class="av-add-btn" type="submit">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              Add
            </button>
          </div>
        </form>

        <div v-if="unavailList.length" class="av-unavail-list">
          <div v-for="d in unavailList" :key="d.id" class="av-unavail-item">
            <span :class="['av-unavail-reason', `av-unavail-reason--${d.reason.toLowerCase()}`]">{{ reasonLabel(d.reason) }}</span>
            <span class="av-unavail-date">{{ formatUnavailDate(d.date) }}</span>
            <span class="av-unavail-label">{{ d.label }}</span>
            <button class="av-unavail-remove" type="button" :aria-label="`Remove ${d.label}`" @click="removeUnavailable(d.id)">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M3 3l8 8M11 3L3 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
        </div>
        <p v-else class="av-empty">No unavailable dates added yet.</p>
      </section>

      <!-- Upcoming Booked Slots — scrollable, fills remaining height -->
      <section class="av-card av-card--flex">
        <div class="av-card__header">
          <h2 class="av-card__title">Upcoming Booked Slots</h2>
          <p class="av-card__desc">All confirmed bookings in your open slots.</p>
        </div>
        <div v-if="upcomingBooked.length" class="av-booked-list av-booked-list--scroll">
          <div v-for="slot in upcomingBooked" :key="slot.id" class="av-booked-item">
            <div class="av-booked-item__date">
              <span class="av-booked-item__day">{{ slotDay(slot.date) }}</span>
              <span class="av-booked-item__dmy">{{ slotDMY(slot.date) }}</span>
            </div>
            <div class="av-booked-item__time">{{ slot.startTime }} – {{ slot.endTime }}</div>
            <div class="av-booked-item__student">{{ slot.bookedByStudentName ?? 'Student' }}</div>
            <span v-if="slot.isTrial" class="av-booked-item__trial">TRIAL</span>
          </div>
        </div>
        <p v-else class="av-empty">No upcoming booked slots.</p>
      </section>

    </div>
  </div>

</div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore }     from '@/stores/auth'
import { useViewAs }        from '@/composables/useViewAs'
import TVDatePicker from '@/components/ui/TVDatePicker.vue'
import TVSelect     from '@/components/ui/TVSelect.vue'
import type { UnavailableReason } from '@/stores/schedule'

const schedule = useScheduleStore()
const auth     = useAuthStore()
const route    = useRoute()
const { effectiveRole } = useViewAs()

const isAdmin = computed(() => effectiveRole.value === 'ADMIN' || effectiveRole.value === 'STAFF')
const timezone = computed(() => auth.user?.timezone ?? 'Asia/Manila')

const teacherOptions = [
  { value: 'u2', label: 'James Reyes' },
  { value: 'u6', label: 'Sarah Lim' },
  { value: 'u7', label: 'Miguel Santos' },
]

const reasonOptions: { value: UnavailableReason; label: string }[] = [
  { value: 'PERSONAL',         label: 'Personal' },
  { value: 'SICK',             label: 'Sick Leave' },
  { value: 'VACATION',         label: 'Vacation' },
  { value: 'NATIONAL_HOLIDAY', label: 'National Holiday' },
]

const selectedTeacher = ref((route.query.teacher as string) || '')

const teacherId = computed(() =>
  isAdmin.value ? selectedTeacher.value : (auth.user?.id ?? '')
)

// ---- Grid config ----
const WEEKDAYS = [
  { key: 1, label: 'Mon' },
  { key: 2, label: 'Tue' },
  { key: 3, label: 'Wed' },
  { key: 4, label: 'Thu' },
  { key: 5, label: 'Fri' },
  { key: 6, label: 'Sat' },
] as const

const HOURS = Array.from({ length: 14 }, (_, i) => i + 7)   // 7 AM – 8 PM

// ---- Helpers ----
function formatHour(h: number): string {
  return h < 12 ? `${h}am` : h === 12 ? '12pm' : `${h - 12}pm`
}

function isActive(dayOfWeek: number, hour: number): boolean {
  return schedule.getWeeklySlots(teacherId.value).some(
    s => s.dayOfWeek === dayOfWeek && s.hour === hour
  )
}

// Check if any upcoming concrete slot for this day-of-week + hour is booked
function isBooked(dayOfWeek: number, hour: number): boolean {
  const hh = String(hour).padStart(2, '0') + ':00'
  return schedule.availabilitySlots.some(
    s => s.teacherId === teacherId.value &&
         s.startTime === hh &&
         s.isBooked &&
         new Date(s.date).getDay() === dayOfWeek
  )
}

function toggleSlot(dayOfWeek: number, hour: number): void {
  if (isBooked(dayOfWeek, hour)) return
  if (isActive(dayOfWeek, hour)) {
    schedule.removeWeeklySlot(teacherId.value, dayOfWeek, hour)
  } else {
    schedule.addWeeklySlot(teacherId.value, dayOfWeek, hour)
  }
}

// ---- Unavailable dates ----
const minDate = computed(() => new Date().toLocaleDateString('sv-SE'))
const newUnavailDate   = ref('')
const newUnavailReason = ref<UnavailableReason>('PERSONAL')
const newUnavailLabel  = ref('')

const unavailList = computed(() =>
  schedule.unavailableDates
    .filter(d => d.teacherId === teacherId.value)
    .sort((a, b) => a.date.localeCompare(b.date))
)

function reasonLabel(reason: UnavailableReason): string {
  switch (reason) {
    case 'PERSONAL':         return 'Personal'
    case 'SICK':             return 'Sick'
    case 'VACATION':         return 'Vacation'
    case 'NATIONAL_HOLIDAY': return 'Holiday'
    default: return reason
  }
}

function formatUnavailDate(dateStr: string): string {
  return new Date(`${dateStr}T12:00:00`).toLocaleDateString('en-US', {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
  })
}

function addUnavailable(): void {
  if (!newUnavailDate.value || !newUnavailLabel.value.trim()) return
  schedule.addUnavailableDate(
    teacherId.value,
    newUnavailDate.value,
    newUnavailReason.value,
    newUnavailLabel.value.trim(),
  )
  newUnavailDate.value  = ''
  newUnavailLabel.value = ''
}

function removeUnavailable(id: string): void {
  schedule.removeUnavailableDate(id)
}

// ---- Upcoming booked slots ----
const upcomingBooked = computed(() =>
  schedule.availabilitySlots
    .filter(s => s.teacherId === teacherId.value && s.isBooked && s.date >= new Date().toLocaleDateString('sv-SE'))
    .sort((a, b) => a.date.localeCompare(b.date) || a.startTime.localeCompare(b.startTime))
)

function slotDay(dateStr: string): string {
  return new Date(`${dateStr}T12:00:00`).toLocaleDateString('en-US', { weekday: 'short' })
}

function slotDMY(dateStr: string): string {
  return new Date(`${dateStr}T12:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>

<style scoped>
/* ---- Page ---- */
.av-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  height: 100%;
  box-sizing: border-box;
}

.av-empty-prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--tv-space-3);
  padding: 60px var(--tv-space-4);
  color: var(--tv-text-muted);
  text-align: center;
}
.av-empty-prompt svg { opacity: 0.35; }
.av-empty-prompt p { font-size: var(--tv-text-sm); margin: 0; }

/* Two-column layout */
.av-columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-5);
  flex: 1;
  min-height: 0;
  height: calc(100dvh - 160px);
}

/* Column base — both fill the column height, scroll internally */
.av-col {
  overflow-y: auto;
  height: 100%;
}

/* Right column stacks unavailability on top, booked fills remainder */
.av-col--right {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  overflow: visible;
}

/* Booked card grows to fill remaining space in right column */
.av-card--flex {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ---- Header ---- */
.av-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}

.av-header__left { flex: 1; }
.av-header__right { display: flex; align-items: flex-start; gap: var(--tv-space-2); }
.av-teacher-block { display: flex; flex-direction: column; gap: var(--tv-space-2); align-items: flex-end; }

.av-title {
  font-size: var(--tv-text-xl);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0 0 var(--tv-space-1);
}
.av-subtitle { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); margin: 0; }

.av-teacher-select {
  font-size: var(--tv-text-sm);
  padding: var(--tv-space-1) var(--tv-space-3);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  color: var(--tv-text);
  cursor: pointer;
}

.av-tz-badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-full);
  padding: 2px var(--tv-space-2);
}

/* ---- Cards ---- */
.av-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-5);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

.av-card__header { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.av-card__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.av-card__desc { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); margin: 0; }

/* ---- Weekly grid ---- */
.av-grid-wrap { overflow-x: auto; }

.av-grid {
  display: grid;
  gap: 3px;
  min-width: 420px;
}

.av-grid__corner { }

.av-grid__day-head {
  text-align: center;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: var(--tv-space-1) 0 var(--tv-space-2);
}

.av-grid__hour-label {
  font-size: 11px;
  color: var(--tv-text-muted);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding-right: var(--tv-space-2);
}

.av-grid__cell {
  height: 36px;
  border-radius: var(--tv-radius-sm);
  border: 1px solid var(--tv-border);
  background: var(--tv-bg-soft);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, border-color 0.15s;
  color: var(--tv-text-secondary);
}
.av-grid__cell:hover:not(:disabled) { background: var(--tv-primary-soft); border-color: var(--tv-primary-muted); }
.av-grid__cell--active {
  background: var(--tv-primary-soft);
  border-color: var(--tv-primary-muted);
  color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%);
}
.av-grid__cell--active:hover:not(:disabled) { background: var(--tv-primary-muted); }
.av-grid__cell--booked {
  background: var(--tv-neutral-soft);
  border-color: var(--tv-neutral-border);
  color: var(--tv-neutral);
  cursor: not-allowed;
}
.av-grid__booked-icon { color: var(--tv-neutral); }

/* ---- Legend ---- */
.av-legend {
  display: flex;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}

.av-legend__item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-xs);
  color: var(--tv-text-secondary);
}
.av-legend__item::before {
  content: '';
  width: 14px; height: 14px;
  border-radius: 3px;
  border: 1px solid;
  flex-shrink: 0;
}
.av-legend__item--available::before  { background: var(--tv-primary-soft);  border-color: var(--tv-primary-muted); }
.av-legend__item--booked::before     { background: var(--tv-neutral-soft);  border-color: var(--tv-neutral-border); }
.av-legend__item--empty::before      { background: var(--tv-bg-soft);       border-color: var(--tv-border); }

/* ---- Unavailable form ---- */
.av-unavail-form { }

.av-unavail-fields {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  align-items: flex-end;
}

.av-field {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-1);
  min-width: 120px;
}
.av-field--grow { flex: 1; }

.av-label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium); color: var(--tv-text-secondary); }

.av-input, .av-select {
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
.av-input:focus, .av-select:focus { border-color: var(--tv-primary); }

.av-add-btn {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
  border: none;
  border-radius: var(--tv-radius-sm);
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s;
}
.av-add-btn:hover { background: var(--tv-primary-hover); }

/* ---- Unavailable list ---- */
.av-unavail-list {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.av-unavail-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) var(--tv-space-4);
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
}

.av-unavail-reason {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
  white-space: nowrap;
  border: 1px solid transparent;
}
.av-unavail-reason--personal        { background: var(--tv-info-soft);    color: var(--tv-info-fg);    border-color: var(--tv-info-border); }
.av-unavail-reason--sick            { background: var(--tv-danger-soft);  color: var(--tv-danger-fg);  border-color: var(--tv-danger-border); }
.av-unavail-reason--vacation        { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.av-unavail-reason--national_holiday { background: var(--tv-warning-soft); color: var(--tv-warning-fg); border-color: var(--tv-warning-border); }

.av-unavail-date { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); white-space: nowrap; }
.av-unavail-label { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); flex: 1; }
.av-unavail-remove {
  width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: transparent;
  color: var(--tv-text-muted);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
}
.av-unavail-remove:hover { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }

/* ---- Booked list ---- */
.av-booked-list {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.av-booked-list--scroll {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}

.av-booked-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-4);
  padding: var(--tv-space-3) var(--tv-space-4);
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
}

.av-booked-item__date { display: flex; flex-direction: column; min-width: 44px; }
.av-booked-item__day  { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.av-booked-item__dmy  { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.av-booked-item__time { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); white-space: nowrap; }
.av-booked-item__student { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); flex: 1; }
.av-booked-item__trial {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  background: var(--tv-purple-soft);
  color: var(--tv-purple);
  border: 1px solid var(--tv-purple-border);
  border-radius: var(--tv-radius-full);
  padding: 1px var(--tv-space-2);
}

/* ---- Empty state ---- */
.av-empty {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
  padding: var(--tv-space-3) 0;
}

/* ---- Responsive ---- */
@media (max-width: 1024px) {
  .av-columns {
    grid-template-columns: 1fr;
    height: auto;
  }
  .av-col { height: auto; overflow-y: visible; }
  .av-card--flex { min-height: 300px; }
  .av-booked-list--scroll { max-height: 300px; }
}

@media (max-width: 768px) {
  .av-page { padding: var(--tv-space-4); }
  .av-card { padding: var(--tv-space-4); }
  .av-unavail-fields { flex-direction: column; }
  .av-booked-item { flex-wrap: wrap; gap: var(--tv-space-2); }
}
</style>
