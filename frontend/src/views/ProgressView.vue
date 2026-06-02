<template>
  <div class="pg-page">

    <!-- Header -->
    <div class="pg-header">
      <div>
        <h1 class="pg-title">Progress</h1>
        <p class="pg-subtitle">{{ headerSubtitle }}</p>
      </div>
    </div>

    <!-- Student selector — always visible for teacher/admin -->
    <div v-if="canViewOthers" class="pg-selector-row">
      <TVSelect
        v-model="selectedStudentId"
        :options="studentOptions"
        placeholder="Select a student"
        style="width:260px"
      />
    </div>

    <!-- No student selected -->
    <div v-if="canViewOthers && !selectedStudentId" class="pg-empty">
      <svg width="44" height="44" viewBox="0 0 44 44" fill="none" aria-hidden="true">
        <circle cx="22" cy="14" r="8" stroke="currentColor" stroke-width="1.6"/>
        <path d="M5 42c0-9.389 7.611-17 17-17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        <circle cx="35" cy="35" r="7" stroke="currentColor" stroke-width="1.6"/>
        <path d="M35 32v3.5l2 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <p>Select a student above to view their progress.</p>
    </div>

    <template v-else-if="progress">

      <!-- Row 1: Stats -->
      <div class="pg-stats">
        <div class="pg-stat">
          <span class="pg-stat__val">{{ progress.lessonsCompleted }}</span>
          <span class="pg-stat__label">Lessons Completed</span>
        </div>
        <div class="pg-stat">
          <span class="pg-stat__val">{{ progress.goalsCompleted }}</span>
          <span class="pg-stat__label">Goals Completed</span>
        </div>
        <div class="pg-stat">
          <span class="pg-stat__val">{{ progress.goalsInProgress }}</span>
          <span class="pg-stat__label">Goals In Progress</span>
        </div>
        <div class="pg-stat">
          <span class="pg-stat__val">{{ progress.milestones.filter(m => m.achieved).length }}</span>
          <span class="pg-stat__label">Milestones Reached</span>
        </div>
        <div class="pg-stat pg-stat--level">
          <span class="pg-stat__val">{{ progress.currentLevel }}</span>
          <span class="pg-stat__label">Current Level</span>
        </div>
      </div>

      <!-- Row 2: 3 cards — Skills | Level Movement | Milestones -->
      <div class="pg-row3">

        <!-- Skills — Radar chart -->
        <section class="pg-card">
          <div class="pg-card__header">
            <h2 class="pg-card__title">Skill Progress</h2>
            <button v-if="canUpdate" class="pg-edit-btn" type="button" @click="openEditSkills">Edit</button>
          </div>
          <TVRadarChart
            :labels="progress.skills.map(s => s.label)"
            :data="progress.skills.map(s => s.score)"
            :max="10"
            :size="210"
          />
          <div class="pg-confidence-box">
            <div class="pg-confidence-box__icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M10 2l1.8 5.4H18l-4.9 3.5 1.8 5.5L10 13l-4.9 3.4 1.8-5.5L2 7.4h6.2L10 2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
              </svg>
            </div>
            <div>
              <p class="pg-confidence-box__label">Confidence Growth</p>
              <p class="pg-confidence-box__text">{{ progress.confidenceNote }}</p>
            </div>
          </div>
        </section>

        <!-- Level Movement — CEFR 6-card grid -->
        <section class="pg-card pg-card--scroll">
          <div class="pg-card__header">
            <h2 class="pg-card__title">Level Movement</h2>
          </div>
          <TVCEFRTrack
            :current-level="progress.currentLevel"
            :start-level="progress.startLevel"
          />
        </section>

        <!-- Milestones -->
        <section class="pg-card pg-card--scroll">
          <div class="pg-card__header">
            <h2 class="pg-card__title">Milestones</h2>
          </div>
          <div class="pg-milestones">
            <div v-for="m in progress.milestones" :key="m.id" :class="['pg-milestone', { 'pg-milestone--achieved': m.achieved }]">
              <div class="pg-milestone__icon">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M7 1l1.5 3H12l-2.7 2 1 3.3L7 7.7l-3.3 1.6 1-3.3L2 4h3.5L7 1z" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="pg-milestone__body">
                <p class="pg-milestone__text">{{ m.label }}</p>
                <p v-if="m.achieved && m.date" class="pg-milestone__date">Achieved {{ formatDate(m.date) }}</p>
                <p v-else class="pg-milestone__date pg-milestone__date--pending">Not yet achieved</p>
              </div>
            </div>
          </div>
        </section>

      </div>

      <!-- Row 3: 2 cards — Goals | Teacher Comments -->
      <div class="pg-row2">

        <!-- Learning Goals -->
        <section class="pg-card pg-card--scroll">
          <div class="pg-card__header">
            <h2 class="pg-card__title">Learning Goals</h2>
          </div>
          <div class="pg-goals">
            <div v-for="goal in progress.goals" :key="goal.id" class="pg-goal">
              <div :class="['pg-goal__check', goal.status === 'done' ? 'pg-goal__check--done' : goal.status === 'progress' ? 'pg-goal__check--progress' : '']">
                <svg v-if="goal.status === 'done'" width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                  <path d="M2 5l2.5 2.5 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg v-else-if="goal.status === 'progress'" width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                  <circle cx="5" cy="5" r="2" fill="currentColor"/>
                </svg>
              </div>
              <div class="pg-goal__body">
                <p class="pg-goal__text">{{ goal.text }}</p>
                <span :class="['pg-goal__badge', `pg-goal__badge--${goal.status}`]">
                  {{ goal.status === 'done' ? 'Completed' : goal.status === 'progress' ? 'In Progress' : 'Pending' }}
                </span>
              </div>
            </div>
          </div>
        </section>

        <!-- Teacher Comments -->
        <section class="pg-card pg-card--scroll">
          <div class="pg-card__header">
            <h2 class="pg-card__title">Teacher Comments</h2>
            <button v-if="canUpdate" class="pg-edit-btn" type="button" @click="openAddComment">+ Add</button>
          </div>
          <div class="pg-comments">
            <div v-for="c in progress.teacherComments" :key="c.id" class="pg-comment">
              <p class="pg-comment__text">{{ c.text }}</p>
              <span class="pg-comment__byline">— {{ c.teacherName }}, {{ formatDate(c.date) }}</span>
            </div>
            <p v-if="!progress.teacherComments.length" class="pg-empty-inline">No comments yet.</p>
          </div>
        </section>

      </div>

    </template>

    <!-- Edit Skills Modal -->
    <TVModal v-model="showEditSkills" title="Edit Skill Scores" maxWidth="480px">
      <div class="pg-edit-skills">
        <div v-for="skill in editSkillsCopy" :key="skill.key" class="pg-edit-skill-row">
          <label class="pg-edit-skill-label">{{ skill.label }}</label>
          <div class="pg-edit-skill-controls">
            <input
              v-model.number="skill.score"
              type="range"
              min="1" max="10" step="1"
              class="pg-skill-range"
            />
            <span class="pg-edit-skill-val">{{ skill.score }}/10</span>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="pg-btn pg-btn--ghost" type="button" @click="showEditSkills = false">Cancel</button>
        <button class="pg-btn pg-btn--primary" type="button" @click="saveSkills">Save</button>
      </template>
    </TVModal>

    <!-- Add Comment Modal -->
    <TVModal v-model="showAddComment" title="Add Teacher Comment" maxWidth="420px">
      <div class="pg-field">
        <label class="pg-label">Comment</label>
        <textarea v-model="newComment" class="pg-textarea" rows="4" placeholder="Write a teacher observation or progress note…" />
      </div>
      <template #footer>
        <button class="pg-btn pg-btn--ghost" type="button" @click="showAddComment = false">Cancel</button>
        <button class="pg-btn pg-btn--primary" type="button" :disabled="!newComment.trim()" @click="saveComment">Save</button>
      </template>
    </TVModal>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import { useScheduleStore } from '@/stores/schedule'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVModal from '@/components/ui/TVModal.vue'
import TVCEFRTrack from '@/components/ui/TVCEFRTrack.vue'
import TVRadarChart from '@/components/ui/TVRadarChart.vue'

const route    = useRoute()
const auth     = useAuthStore()
const users    = useUsersStore()
const schedule = useScheduleStore()
const toast    = useToast()
const { effectiveRole } = useViewAs()

const role   = computed(() => effectiveRole.value)
const userId = computed(() => auth.user?.id ?? '')

// ── Can functions ──
const canViewOthers = computed(() => ['TEACHER', 'ADMIN', 'STAFF'].includes(role.value))
const teacherStudentIds = computed(() => {
  const me = users.getUserById(userId.value)
  return me?.teacherProfile?.assignedStudentIds ?? []
})

const canUpdate = computed(() => role.value === 'ADMIN' ||
  (role.value === 'TEACHER' && !!selectedStudentId.value &&
   teacherStudentIds.value.includes(selectedStudentId.value)))

// ── Student selection ──
const selectedStudentId = ref((route.query.student as string) || '')

const studentOptions = computed(() => {
  const base = [{ value: '', label: 'Select a student' }]
  if (role.value === 'TEACHER') {
    const ids = teacherStudentIds.value
    return [
      ...base,
      ...users.filteredUsers({ role: 'STUDENT' })
        .filter(s => ids.includes(s.id))
        .map(s => ({ value: s.id, label: `${s.firstName} ${s.lastName}` })),
    ]
  }
  return [
    ...base,
    ...users.filteredUsers({ role: 'STUDENT' }).map(s => ({ value: s.id, label: `${s.firstName} ${s.lastName}` })),
  ]
})

// For student role, auto-select self
watch(role, () => {
  if (role.value === 'STUDENT') selectedStudentId.value = userId.value
}, { immediate: true })

// ── Resolved student ID ──
const activeStudentId = computed(() =>
  role.value === 'STUDENT' ? userId.value : selectedStudentId.value
)

// ── Header ──
const headerSubtitle = computed(() => {
  if (role.value === 'STUDENT') return 'Your learning journey and skill development'
  if (role.value === 'TEACHER') return 'Track and update assigned student progress'
  return 'Review student development by student, teacher, and course'
})

// ── CEFR levels ──
const CEFR_LEVELS = [
  { code: 'A1', label: 'Beginner',          order: 1 },
  { code: 'A2', label: 'Elementary',         order: 2 },
  { code: 'B1', label: 'Intermediate',       order: 3 },
  { code: 'B2', label: 'Upper Intermediate', order: 4 },
  { code: 'C1', label: 'Advanced',           order: 5 },
  { code: 'C2', label: 'Proficiency',        order: 6 },
]

const LEVEL_TO_CEFR: Record<string, string> = {
  BEGINNER:          'A1',
  ELEMENTARY:        'A2',
  INTERMEDIATE:      'B1',
  UPPER_INTERMEDIATE:'B2',
  ADVANCED:          'C1',
  PROFICIENCY:       'C2',
}

// ── Progress data (derived + mocked per student) ──
interface SkillEntry { key: string; label: string; score: number; note?: string }
interface Goal       { id: string; text: string; status: 'done' | 'progress' | 'pending' }
interface Milestone  { id: string; label: string; achieved: boolean; date?: string }
interface Comment    { id: string; date: string; text: string; teacherName: string }

interface StudentProgress {
  currentLevel: string
  startLevel: string
  lessonsCompleted: number
  goalsCompleted: number
  goalsInProgress: number
  confidenceNote: string
  skills: SkillEntry[]
  goals: Goal[]
  milestones: Milestone[]
  teacherComments: Comment[]
}

const MOCK_PROGRESS: Record<string, StudentProgress> = {
  u1: {
    currentLevel: 'B1', startLevel: 'A2',
    lessonsCompleted: 42, goalsCompleted: 3, goalsInProgress: 2,
    confidenceNote: 'Emma has shown remarkable growth in speaking confidence — she now volunteers answers without being prompted and leads discussions naturally.',
    skills: [
      { key: 'speaking',   label: 'Speaking',               score: 7, note: 'Clear progress — more natural in conversations' },
      { key: 'listening',  label: 'Listening',              score: 8 },
      { key: 'vocabulary', label: 'Vocabulary',             score: 7, note: 'Business vocabulary is strong' },
      { key: 'grammar',    label: 'Grammar',                score: 6, note: 'Formal writing needs attention' },
      { key: 'pronunc',    label: 'Pronunciation',          score: 7 },
      { key: 'confidence', label: 'Confidence',             score: 8, note: 'Significant improvement this quarter' },
      { key: 'fluency',    label: 'Fluency',                score: 7 },
      { key: 'workplace',  label: 'Workplace Communication', score: 8, note: 'Excellent in presentations' },
    ],
    goals: [
      { id: 'g1', text: 'Deliver a confident presentation in English', status: 'done' },
      { id: 'g2', text: 'Write professional business emails without errors', status: 'progress' },
      { id: 'g3', text: 'Lead an English business meeting', status: 'done' },
      { id: 'g4', text: 'Read and summarize business articles fluently', status: 'progress' },
      { id: 'g5', text: 'Pass Cambridge B2 Business English exam', status: 'pending' },
    ],
    milestones: [
      { id: 'm1', label: 'Completed 10 lessons',   achieved: true,  date: '2024-03-15' },
      { id: 'm2', label: 'Completed 25 lessons',   achieved: true,  date: '2024-07-20' },
      { id: 'm3', label: 'Completed 40 lessons',   achieved: true,  date: '2025-01-10' },
      { id: 'm4', label: 'Level up: A2 → B1',      achieved: true,  date: '2024-09-05' },
      { id: 'm5', label: 'First solo presentation', achieved: true,  date: '2024-11-22' },
      { id: 'm6', label: 'Level up: B1 → B2',      achieved: false },
    ],
    teacherComments: [
      { id: 'c1', date: '2026-05-15', text: 'Emma showed excellent command of conditionals today. Her usage is now natural in conversation.', teacherName: 'James Reyes' },
      { id: 'c2', date: '2026-04-28', text: 'Very strong session on business negotiation vocabulary. Emma retained 90% from previous lesson.', teacherName: 'James Reyes' },
      { id: 'c3', date: '2026-03-12', text: 'Speaking confidence is noticeably growing. Less hesitation when starting sentences.', teacherName: 'James Reyes' },
    ],
  },
  u11: {
    currentLevel: 'A2', startLevel: 'A1',
    lessonsCompleted: 18, goalsCompleted: 1, goalsInProgress: 2,
    confidenceNote: 'Marco is building confidence steadily. He used to avoid speaking but now asks clarifying questions regularly.',
    skills: [
      { key: 'speaking',   label: 'Speaking',               score: 4 },
      { key: 'listening',  label: 'Listening',              score: 5 },
      { key: 'vocabulary', label: 'Vocabulary',             score: 4 },
      { key: 'grammar',    label: 'Grammar',                score: 3 },
      { key: 'pronunc',    label: 'Pronunciation',          score: 5 },
      { key: 'confidence', label: 'Confidence',             score: 5, note: 'Improving — asks more questions now' },
      { key: 'fluency',    label: 'Fluency',                score: 3 },
      { key: 'workplace',  label: 'Workplace Communication', score: 3 },
    ],
    goals: [
      { id: 'g1', text: 'Introduce myself confidently in English', status: 'done' },
      { id: 'g2', text: 'Hold a 5-minute conversation without stopping', status: 'progress' },
      { id: 'g3', text: 'Read a short article and summarize it', status: 'progress' },
      { id: 'g4', text: 'Write a formal email requesting information', status: 'pending' },
    ],
    milestones: [
      { id: 'm1', label: 'Completed 10 lessons',   achieved: true,  date: '2025-08-10' },
      { id: 'm2', label: 'Level up: A1 → A2',      achieved: true,  date: '2025-10-03' },
      { id: 'm3', label: 'Completed 25 lessons',   achieved: false },
      { id: 'm4', label: 'First full conversation', achieved: false },
    ],
    teacherComments: [
      { id: 'c1', date: '2026-05-10', text: 'Great improvement in listening. Marco understood most of today\'s dialogue without subtitles.', teacherName: 'Sarah Lim' },
    ],
  },
}

// Default progress for students not in mock
function defaultProgress(studentId: string): StudentProgress {
  const student = users.getUserById(studentId)
  const level = LEVEL_TO_CEFR[student?.studentProfile?.englishLevel ?? 'INTERMEDIATE'] ?? 'B1'
  const completed = schedule.myLessons.filter(l => l.status === 'COMPLETED' && l.studentId === studentId).length
  return {
    currentLevel: level, startLevel: 'A2',
    lessonsCompleted: completed, goalsCompleted: 0, goalsInProgress: 1,
    confidenceNote: 'Progress notes will appear here as lessons are completed.',
    skills: [
      { key: 'speaking',   label: 'Speaking',               score: 5 },
      { key: 'listening',  label: 'Listening',              score: 5 },
      { key: 'vocabulary', label: 'Vocabulary',             score: 5 },
      { key: 'grammar',    label: 'Grammar',                score: 5 },
      { key: 'pronunc',    label: 'Pronunciation',          score: 5 },
      { key: 'confidence', label: 'Confidence',             score: 5 },
      { key: 'fluency',    label: 'Fluency',                score: 5 },
      { key: 'workplace',  label: 'Workplace Communication', score: 5 },
    ],
    goals: student?.studentProfile?.goals
      ? [{ id: 'g1', text: student.studentProfile.goals, status: 'progress' }]
      : [],
    milestones: [{ id: 'm1', label: 'Completed 10 lessons', achieved: completed >= 10, date: undefined }],
    teacherComments: [],
  }
}

const progress = computed((): StudentProgress | null => {
  const id = activeStudentId.value
  if (!id) return null
  return MOCK_PROGRESS[id] ?? defaultProgress(id)
})

const currentLevelOrder = computed(() =>
  CEFR_LEVELS.find(l => l.code === progress.value?.currentLevel)?.order ?? 0
)

// ── Edit skills modal ──
const showEditSkills  = ref(false)
const editSkillsCopy  = ref<SkillEntry[]>([])

function openEditSkills() {
  editSkillsCopy.value = JSON.parse(JSON.stringify(progress.value?.skills ?? []))
  showEditSkills.value = true
}

function saveSkills() {
  const id = activeStudentId.value
  if (!id) return
  const base = MOCK_PROGRESS[id] ?? defaultProgress(id)
  MOCK_PROGRESS[id] = { ...base, skills: JSON.parse(JSON.stringify(editSkillsCopy.value)) }
  showEditSkills.value = false
  toast.success('Skill scores updated.')
}

// ── Add comment modal ──
const showAddComment = ref(false)
const newComment     = ref('')

function openAddComment() {
  newComment.value = ''
  showAddComment.value = true
}

function saveComment() {
  if (!newComment.value.trim()) return
  const id = activeStudentId.value
  if (!id) return
  const base = MOCK_PROGRESS[id] ?? defaultProgress(id)
  const comment: Comment = {
    id: `c${Date.now()}`,
    date: new Date().toISOString().slice(0, 10),
    text: newComment.value.trim(),
    teacherName: `${auth.user?.firstName ?? ''} ${auth.user?.lastName ?? ''}`.trim(),
  }
  MOCK_PROGRESS[id] = { ...base, teacherComments: [comment, ...(base.teacherComments ?? [])] }
  showAddComment.value = false
  toast.success('Comment added.')
}

// ── Helpers ──
function skillBarClass(score: number): string {
  if (score >= 8) return 'pg-skill__bar--high'
  if (score >= 5) return 'pg-skill__bar--mid'
  return 'pg-skill__bar--low'
}

function formatDate(iso: string): string {
  return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<style scoped>
.pg-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}
@media (max-width: 767px) { .pg-page { padding: var(--tv-space-4); } }

/* Header */
.pg-header { display: flex; align-items: flex-start; justify-content: space-between; }
.pg-title  { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0; letter-spacing: -0.025em; }
.pg-subtitle { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: var(--tv-space-1) 0 0; }

/* Student selector row */
.pg-selector-row { display: flex; align-items: center; gap: var(--tv-space-3); }

/* Empty */
.pg-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: var(--tv-space-3); padding: var(--tv-space-10) var(--tv-space-4);
  color: var(--tv-text-muted); text-align: center;
}
.pg-empty svg { opacity: 0.35; }
.pg-empty p { font-size: var(--tv-text-sm); margin: 0; }

/* Stats row */
.pg-stats {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
}
.pg-stat {
  flex: 1; min-width: 120px;
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: 4px;
  box-shadow: var(--tv-shadow-sm);
}
.pg-stat__val   { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); line-height: 1; }
.pg-stat__label { font-size: var(--tv-text-xs); color: var(--tv-text-muted); font-weight: var(--tv-font-medium); }
.pg-stat--level .pg-stat__val { color: var(--tv-primary); }

/* Row layouts */
.pg-row3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--tv-space-4);
  align-items: stretch;
}
.pg-row2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--tv-space-4);
  align-items: stretch;
}
/* Scrollable card body — cap height, overflow scroll */
.pg-card--scroll { max-height: 420px; overflow-y: auto; }

/* Card */
.pg-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  padding: var(--tv-space-5);
  display: flex; flex-direction: column; gap: var(--tv-space-4);
}
.pg-card__header { display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.pg-card__title  { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.pg-card__desc   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: -var(--tv-space-2) 0 0; flex-shrink: 0; }

/* Edit button */
.pg-edit-btn {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  color: var(--tv-primary); background: var(--tv-primary-soft);
  border: 1px solid var(--tv-primary-muted); border-radius: var(--tv-radius-sm);
  padding: 3px var(--tv-space-2); cursor: pointer; transition: background 0.15s;
}
.pg-edit-btn:hover { background: hsl(var(--tv-primary-h), 70%, 90%); }

/* Skills */
.pg-skills { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.pg-skill__header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; }
.pg-skill__name  { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.pg-skill__score { font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold); color: var(--tv-text); }
.pg-skill__max   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); font-weight: var(--tv-font-normal); }
.pg-skill__bar-wrap { height: 8px; background: var(--tv-bg-soft); border-radius: var(--tv-radius-full); overflow: hidden; }
.pg-skill__bar { height: 100%; border-radius: var(--tv-radius-full); transition: width 0.4s ease; }
.pg-skill__bar--high { background: var(--tv-success-fg); }
.pg-skill__bar--mid  { background: var(--tv-primary); }
.pg-skill__bar--low  { background: var(--tv-warning-fg); }
.pg-skill__note { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 2px 0 0; }

/* Confidence callout */
.pg-confidence-box {
  display: flex; align-items: flex-start; gap: var(--tv-space-3);
  background: var(--tv-primary-soft); border: 1px solid var(--tv-primary-muted);
  border-radius: var(--tv-radius-md); padding: var(--tv-space-3);
}
.pg-confidence-box__icon {
  width: 36px; height: 36px; flex-shrink: 0;
  border-radius: var(--tv-radius-sm);
  background: var(--tv-primary); color: var(--tv-text-inverse);
  display: flex; align-items: center; justify-content: center;
}
.pg-confidence-box__label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold); color: var(--tv-primary); text-transform: uppercase; letter-spacing: .05em; margin: 0 0 4px; }
.pg-confidence-box__text  { font-size: var(--tv-text-xs); color: var(--tv-text-secondary); line-height: 1.5; margin: 0; }

/* Goals */
.pg-goals { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.pg-goal  { display: flex; align-items: flex-start; gap: var(--tv-space-3); }
.pg-goal__check {
  width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px;
  border-radius: var(--tv-radius-full); border: 2px solid var(--tv-border);
  display: flex; align-items: center; justify-content: center;
  background: var(--tv-bg-soft);
}
.pg-goal__check--done     { background: var(--tv-success-fg); border-color: var(--tv-success-fg); color: white; }
.pg-goal__check--progress { background: var(--tv-primary); border-color: var(--tv-primary); color: white; }
.pg-goal__body { flex: 1; min-width: 0; }
.pg-goal__text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0 0 4px; line-height: 1.4; }
.pg-goal__badge { display: inline-block; font-size: 10px; font-weight: var(--tv-font-semibold); padding: 1px 8px; border-radius: var(--tv-radius-full); border: 1px solid; }
.pg-goal__badge--done     { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.pg-goal__badge--progress { background: var(--tv-primary-soft); color: var(--tv-primary); border-color: var(--tv-primary-muted); }
.pg-goal__badge--pending  { background: var(--tv-bg-soft); color: var(--tv-text-muted); border-color: var(--tv-border); }

/* Level track — fills remaining card height */
.pg-level-track { display: flex; flex-direction: column; flex: 1; justify-content: space-between; position: relative; padding-left: var(--tv-space-4); }
.pg-level-track::before { content: ''; position: absolute; left: 7px; top: 10px; bottom: 10px; width: 2px; background: var(--tv-border); }
.pg-level-node { display: flex; align-items: flex-start; gap: var(--tv-space-3); padding: var(--tv-space-2) 0; position: relative; }
.pg-level-node__dot {
  width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px;
  border-radius: 50%; border: 2px solid var(--tv-border);
  background: var(--tv-bg-card); position: relative; z-index: 1;
  transition: background 0.2s, border-color 0.2s;
}
.pg-level-node--done .pg-level-node__dot   { background: var(--tv-success-fg); border-color: var(--tv-success-fg); }
.pg-level-node--active .pg-level-node__dot { background: var(--tv-primary); border-color: var(--tv-primary); box-shadow: 0 0 0 3px var(--tv-primary-soft); }
.pg-level-node__info   { display: flex; flex-wrap: wrap; align-items: center; gap: var(--tv-space-2); }
.pg-level-node__code   { font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold); color: var(--tv-text); }
.pg-level-node__label  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.pg-level-node--done .pg-level-node__code  { color: var(--tv-success-fg); }
.pg-level-node--active .pg-level-node__code { color: var(--tv-primary); }
.pg-level-node__current { font-size: 10px; font-weight: var(--tv-font-semibold); background: var(--tv-primary); color: white; padding: 1px 7px; border-radius: var(--tv-radius-full); }
.pg-level-node__started { font-size: 10px; color: var(--tv-text-muted); background: var(--tv-bg-soft); padding: 1px 7px; border-radius: var(--tv-radius-full); }

/* Milestones */
.pg-milestones { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.pg-milestone {
  display: flex; align-items: center; gap: var(--tv-space-3);
  padding: var(--tv-space-2); border-radius: var(--tv-radius);
  border: 1px solid var(--tv-border); opacity: 0.55;
  transition: opacity 0.2s;
}
.pg-milestone--achieved { opacity: 1; border-color: var(--tv-success-border); background: var(--tv-success-soft); }
.pg-milestone__icon {
  width: 28px; height: 28px; flex-shrink: 0;
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  background: var(--tv-bg-soft); color: var(--tv-text-muted);
}
.pg-milestone--achieved .pg-milestone__icon { background: var(--tv-success-fg); color: white; }
.pg-milestone__body { flex: 1; min-width: 0; }
.pg-milestone__text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0 0 2px; font-weight: var(--tv-font-medium); }
.pg-milestone__date { font-size: var(--tv-text-xs); color: var(--tv-success-fg); margin: 0; }
.pg-milestone__date--pending { color: var(--tv-text-muted); }

/* Teacher comments */
.pg-comments { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.pg-comment {
  padding-bottom: var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
}
.pg-comment:last-child { border-bottom: none; padding-bottom: 0; }
.pg-comment__text  { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.5; margin: 0 0 4px; }
.pg-comment__byline { font-size: var(--tv-text-xs); color: var(--tv-text-muted); font-style: italic; }
.pg-empty-inline { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 0; }

/* Edit skills modal */
.pg-edit-skills { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.pg-edit-skill-row { display: flex; flex-direction: column; gap: 4px; }
.pg-edit-skill-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.pg-edit-skill-controls { display: flex; align-items: center; gap: var(--tv-space-3); }
.pg-skill-range { flex: 1; accent-color: var(--tv-primary); }
.pg-edit-skill-val { font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold); color: var(--tv-primary); min-width: 32px; text-align: right; }

/* Add comment modal */
.pg-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.pg-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.pg-textarea {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  resize: vertical; outline: none; font-family: inherit; line-height: 1.5;
  transition: border-color 0.15s;
}
.pg-textarea:focus { border-color: var(--tv-primary); }

/* Buttons */
.pg-btn {
  display: inline-flex; align-items: center; padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold);
  border-radius: var(--tv-radius); border: 1px solid transparent;
  cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.pg-btn--primary { background: var(--tv-primary); color: white; }
.pg-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.pg-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.pg-btn--ghost:hover { background: var(--tv-bg-soft); }
.pg-btn:disabled { opacity: 0.45; cursor: not-allowed; }

@media (max-width: 1100px) {
  .pg-row3 { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 767px) {
  .pg-row3, .pg-row2 { grid-template-columns: 1fr; }
  .pg-stats { flex-wrap: wrap; }
  .pg-stats .pg-stat { min-width: 100px; }
  .pg-stat--selector { flex: 1 1 100%; }
}
</style>
