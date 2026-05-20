<template>
  <div class="user-form-page">
    <!-- Page header -->
    <div class="user-form-page__header">
      <button class="user-form-page__back" @click="router.push('/admin/users')" aria-label="Back to users">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
          <path d="M11 13l-4-4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Users
      </button>
      <h1 class="user-form-page__title">Create User</h1>
    </div>

    <form class="user-form-layout" novalidate @submit.prevent="handleSubmit">
      <!-- Left: main form -->
      <div class="user-form-layout__main">

        <!-- Basic info -->
        <section class="form-section">
          <h2 class="form-section__title">Basic Information</h2>
          <div class="form-row">
            <TVInput
              v-model="form.firstName"
              label="First Name"
              required
              :error="errors.firstName"
              autocomplete="given-name"
            />
            <TVInput
              v-model="form.lastName"
              label="Last Name"
              required
              :error="errors.lastName"
              autocomplete="family-name"
            />
          </div>
          <TVInput
            v-model="form.email"
            label="Email Address"
            type="email"
            required
            :error="errors.email"
            autocomplete="email"
          />
          <div class="form-row">
            <TVSelect
              v-model="form.role"
              :options="roleOptions"
              label="Role"
              required
              :error="errors.role"
            />
            <TVSelect
              v-model="form.timezone"
              :options="timezoneOptions"
              label="Timezone"
              required
            />
          </div>
          <div class="form-row">
            <TVInput
              v-model="form.password"
              label="Password"
              type="password"
              required
              :error="errors.password"
              hint="Min. 8 characters"
              autocomplete="new-password"
            />
            <TVInput
              v-model="form.confirmPassword"
              label="Confirm Password"
              type="password"
              required
              :error="errors.confirmPassword"
              autocomplete="new-password"
            />
          </div>
          <label class="toggle-field">
            <span class="toggle-field__label">Active</span>
            <button
              type="button"
              role="switch"
              :aria-checked="form.isActive"
              class="toggle-field__switch"
              :class="{ 'toggle-field__switch--on': form.isActive }"
              @click="form.isActive = !form.isActive"
            >
              <span class="toggle-field__thumb" />
            </button>
            <span class="toggle-field__hint">{{ form.isActive ? 'User can log in' : 'User cannot log in' }}</span>
          </label>
        </section>

        <!-- Role-specific profile -->
        <section v-if="form.role" class="form-section">
          <h2 class="form-section__title">
            {{ roleProfileTitle }}
          </h2>

          <!-- STUDENT fields -->
          <template v-if="form.role === 'STUDENT'">
            <div class="form-row">
              <TVSelect
                v-model="studentProfile.englishLevel"
                :options="englishLevelOptions"
                label="English Level"
              />
              <TVSelect
                v-model="studentProfile.classType"
                :options="classTypeOptions"
                label="Class Type"
              />
            </div>
            <div class="form-row">
              <TVInput v-model="studentProfile.program" label="Course / Program" />
              <TVInput v-model="studentProfile.startDate" label="Start Date" type="date" />
            </div>
            <TVSelect
              v-model="studentProfile.assignedTeacherId"
              :options="teacherOptions"
              label="Assigned Teacher"
              placeholder="Select a teacher"
            />
            <TVInput
              v-model="studentProfile.goals"
              label="Learning Goals"
              hint="What the student wants to achieve"
            />
            <TVInput
              v-model="studentProfile.learningConcerns"
              label="Learning Concerns"
              hint="Areas that need extra attention"
            />
            <TVInput
              v-model="studentProfile.notes"
              label="Notes / Preferences"
              hint="Internal notes (not visible to student)"
            />
          </template>

          <!-- TEACHER fields -->
          <template v-else-if="form.role === 'TEACHER'">
            <TVInput v-model="teacherProfile.specialization" label="Specialization" hint="e.g. Business English, IELTS" />
            <TVInput v-model="teacherProfile.availabilitySummary" label="Availability Summary" hint="e.g. Mon–Fri 9am–5pm GMT" />
            <div class="form-row">
              <TVSelect
                v-model="teacherProfile.internalStatus"
                :options="teacherStatusOptions"
                label="Internal Status"
              />
              <TVSelect
                v-model="teacherProfile.documentStatus"
                :options="documentStatusOptions"
                label="Document Status"
              />
            </div>
            <div class="form-row">
              <TVSelect
                v-model="teacherProfile.contractStatus"
                :options="documentStatusOptions"
                label="Contract Status"
              />
            </div>
            <TVInput v-model="teacherProfile.teachingNotes" label="Teaching Notes" hint="Internal notes about this teacher" />
          </template>

          <!-- ADMIN / STAFF fields -->
          <template v-else-if="form.role === 'ADMIN' || form.role === 'STAFF'">
            <TVInput v-model="adminStaffProfile.department" label="Department / Function" hint="e.g. Operations, Finance, Student Support" />
            <TVInput v-model="adminStaffProfile.accessLimitations" label="Access Limitations" hint="Describe any restrictions on this account" />
            <div class="form-section__subtitle">Permissions</div>
            <div class="permissions-grid" role="group" aria-label="Staff permissions">
              <label
                v-for="perm in allPermissions"
                :key="perm.value"
                class="perm-checkbox"
              >
                <input
                  type="checkbox"
                  :value="perm.value"
                  :checked="adminStaffProfile.permissions?.includes(perm.value)"
                  class="perm-checkbox__input"
                  @change="togglePermission(perm.value)"
                />
                <span class="perm-checkbox__box" aria-hidden="true" />
                <span class="perm-checkbox__label">{{ perm.label }}</span>
              </label>
            </div>
          </template>
        </section>
      </div>

      <!-- Right: summary card -->
      <aside class="user-form-layout__aside">
        <div class="summary-card">
          <div class="summary-card__photo-wrap">
            <div class="summary-card__avatar">
              <img v-if="photoPreviewUrl" :src="photoPreviewUrl" alt="Profile photo" />
              <span v-else>{{ previewInitials }}</span>
            </div>
            <label class="summary-card__photo-btn" aria-label="Upload profile photo" title="Upload photo">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M1 10.5V12h1.5l4.5-4.5-1.5-1.5L1 10.5zM11.7 3.3a.996.996 0 0 0 0-1.41L10.11.7a.996.996 0 0 0-1.41 0L7.41 2l2.83 2.83 1.46-1.53z" fill="currentColor"/>
              </svg>
              <input type="file" accept="image/*" class="photo-upload__input" @change="handlePhotoChange" />
            </label>
          </div>
          <p class="summary-card__name">{{ previewName || 'New User' }}</p>
          <p class="summary-card__role">{{ form.role ? roleLabel(form.role) : 'No role selected' }}</p>
          <TVBadge
            :label="form.isActive ? 'Active' : 'Inactive'"
            :variant="form.isActive ? 'active' : 'neutral'"
            dot
          />
          <div v-if="form.email" class="summary-card__email">{{ form.email }}</div>
        </div>

        <div class="aside-actions">
          <TVButton type="submit" variant="primary" :loading="submitting" class="aside-actions__btn">
            Create User
          </TVButton>
          <TVButton type="button" variant="ghost" class="aside-actions__btn" @click="router.push('/admin/users')">
            Cancel
          </TVButton>
        </div>
      </aside>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUsersStore } from '@/stores/users'
import { useToast } from '@/composables/useToast'
import TVButton from '@/components/ui/TVButton.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVBadge from '@/components/ui/TVBadge.vue'
import type {
  UserRole,
  SelectOption,
  StudentProfile,
  TeacherProfile,
  AdminStaffProfile,
  StaffPermission,
  EnglishLevel,
  ClassType,
  TeacherInternalStatus,
  DocumentStatus,
} from '@/types'

const router = useRouter()
const store = useUsersStore()
const toast = useToast()

if (!store.users.length) store.fetchUsers()

/* ── Form state ── */
const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  role: '' as UserRole | '',
  timezone: 'Asia/Manila',
  password: '',
  confirmPassword: '',
  isActive: true,
})

const studentProfile = ref<StudentProfile>({})
const teacherProfile = ref<TeacherProfile>({})
const adminStaffProfile = ref<AdminStaffProfile>({ permissions: [] })

const errors = ref<Record<string, string>>({})
const submitting = ref(false)
const photoPreviewUrl = ref<string | null>(null)

onUnmounted(() => {
  if (photoPreviewUrl.value) URL.revokeObjectURL(photoPreviewUrl.value)
})

/* ── Computed ── */
const previewInitials = computed(() => {
  const f = form.value.firstName[0] ?? ''
  const l = form.value.lastName[0] ?? ''
  return (f + l).toUpperCase() || '?'
})

const previewName = computed(() =>
  [form.value.firstName, form.value.lastName].filter(Boolean).join(' '),
)

const roleProfileTitle = computed(() => {
  const map: Partial<Record<UserRole, string>> = {
    STUDENT: 'Student Profile',
    TEACHER: 'Teacher Profile',
    ADMIN: 'Admin Profile',
    STAFF: 'Staff Profile',
  }
  return form.value.role ? (map[form.value.role] ?? 'Profile') : 'Profile'
})

const teacherOptions = computed<SelectOption[]>(() => [
  { value: '', label: 'No teacher assigned' },
  ...store.getTeachers().map(t => ({
    value: t.id,
    label: `${t.firstName} ${t.lastName}`,
  })),
])

/* ── Select options ── */
const roleOptions: SelectOption[] = [
  { value: 'STUDENT', label: 'Student' },
  { value: 'TEACHER', label: 'Teacher' },
  { value: 'ADMIN', label: 'Admin' },
  { value: 'STAFF', label: 'Staff' },
]

const timezoneOptions: SelectOption[] = [
  { value: 'Asia/Manila', label: 'Asia/Manila (PHT)' },
  { value: 'Europe/London', label: 'Europe/London (GMT)' },
  { value: 'America/New_York', label: 'America/New York (EST)' },
  { value: 'America/Los_Angeles', label: 'America/Los Angeles (PST)' },
  { value: 'Asia/Singapore', label: 'Asia/Singapore (SGT)' },
  { value: 'Asia/Tokyo', label: 'Asia/Tokyo (JST)' },
  { value: 'Europe/Paris', label: 'Europe/Paris (CET)' },
  { value: 'Australia/Sydney', label: 'Australia/Sydney (AEST)' },
]

const englishLevelOptions: SelectOption[] = [
  { value: 'BEGINNER', label: 'Beginner (A1)' },
  { value: 'ELEMENTARY', label: 'Elementary (A2)' },
  { value: 'INTERMEDIATE', label: 'Intermediate (B1)' },
  { value: 'UPPER_INTERMEDIATE', label: 'Upper Intermediate (B2)' },
  { value: 'ADVANCED', label: 'Advanced (C1)' },
  { value: 'PROFICIENCY', label: 'Proficiency (C2)' },
]

const classTypeOptions: SelectOption[] = [
  { value: 'ONLINE', label: 'Online' },
  { value: 'IN_PERSON', label: 'In-Person' },
  { value: 'HYBRID', label: 'Hybrid' },
]

const teacherStatusOptions: SelectOption[] = [
  { value: 'ACTIVE', label: 'Active' },
  { value: 'ON_LEAVE', label: 'On Leave' },
  { value: 'PROBATION', label: 'Probation' },
  { value: 'INACTIVE', label: 'Inactive' },
]

const documentStatusOptions: SelectOption[] = [
  { value: 'PENDING', label: 'Pending' },
  { value: 'SUBMITTED', label: 'Submitted' },
  { value: 'APPROVED', label: 'Approved' },
  { value: 'REJECTED', label: 'Rejected' },
]

const allPermissions: { value: StaffPermission; label: string }[] = [
  { value: 'VIEW_BILLING', label: 'View Billing' },
  { value: 'EDIT_STUDENTS', label: 'Edit Students' },
  { value: 'VIEW_PAYROLL', label: 'View Payroll' },
  { value: 'MANAGE_SCHEDULE', label: 'Manage Schedule' },
  { value: 'SEND_COMMUNICATIONS', label: 'Send Communications' },
]

/* ── Helpers ── */
function roleLabel(role: UserRole): string {
  const map: Record<UserRole, string> = { STUDENT: 'Student', TEACHER: 'Teacher', ADMIN: 'Admin', STAFF: 'Staff' }
  return map[role]
}

function togglePermission(perm: StaffPermission): void {
  const perms = adminStaffProfile.value.permissions ?? []
  if (perms.includes(perm)) {
    adminStaffProfile.value.permissions = perms.filter(p => p !== perm)
  } else {
    adminStaffProfile.value.permissions = [...perms, perm]
  }
}

function handlePhotoChange(e: Event): void {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  if (photoPreviewUrl.value) URL.revokeObjectURL(photoPreviewUrl.value)
  photoPreviewUrl.value = URL.createObjectURL(file)
}

/* ── Validation ── */
function validate(): boolean {
  const e: Record<string, string> = {}
  if (!form.value.firstName.trim()) e.firstName = 'First name is required'
  if (!form.value.lastName.trim()) e.lastName = 'Last name is required'
  if (!form.value.email.trim()) {
    e.email = 'Email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    e.email = 'Enter a valid email address'
  }
  if (!form.value.role) e.role = 'Role is required'
  if (!form.value.password) {
    e.password = 'Password is required'
  } else if (form.value.password.length < 8) {
    e.password = 'Password must be at least 8 characters'
  }
  if (form.value.password !== form.value.confirmPassword) {
    e.confirmPassword = 'Passwords do not match'
  }
  errors.value = e
  return Object.keys(e).length === 0
}

/* ── Submit ── */
async function handleSubmit(): Promise<void> {
  if (!validate()) return
  submitting.value = true
  await new Promise(r => setTimeout(r, 400))

  store.createUser({
    firstName: form.value.firstName,
    lastName: form.value.lastName,
    email: form.value.email,
    role: form.value.role as UserRole,
    timezone: form.value.timezone,
    isActive: form.value.isActive,
    studentProfile: form.value.role === 'STUDENT' ? studentProfile.value : undefined,
    teacherProfile: form.value.role === 'TEACHER' ? teacherProfile.value : undefined,
    adminStaffProfile: (form.value.role === 'ADMIN' || form.value.role === 'STAFF')
      ? adminStaffProfile.value
      : undefined,
  })

  toast.success(`${form.value.firstName} ${form.value.lastName} has been created.`)
  submitting.value = false
  router.push('/admin/users')
}
</script>

<style scoped>
.user-form-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

/* Header */
.user-form-page__header {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.user-form-page__back {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: color var(--tv-transition-fast);
}

.user-form-page__back:hover { color: var(--tv-primary); }

.user-form-page__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin: 0;
}

/* Two-column layout */
.user-form-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: var(--tv-space-5);
  align-items: start;
}

.user-form-layout__main {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

/* Form sections */
.form-section {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-5);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

.form-section__title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0 0 var(--tv-space-1);
  padding-bottom: var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
}

.form-section__subtitle {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-secondary);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-4);
}

.photo-upload__input {
  display: none;
}

/* Toggle */
.toggle-field {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  cursor: pointer;
}

.toggle-field__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  min-width: 40px;
}

.toggle-field__switch {
  width: 44px;
  height: 24px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-border);
  border: none;
  cursor: pointer;
  position: relative;
  transition: background-color var(--tv-transition-fast);
  flex-shrink: 0;
  padding: 0;
}

.toggle-field__switch--on {
  background: var(--tv-primary);
}

.toggle-field__thumb {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: white;
  box-shadow: var(--tv-shadow-sm);
  transition: transform var(--tv-transition-fast);
}

.toggle-field__switch--on .toggle-field__thumb {
  transform: translateX(20px);
}

.toggle-field__hint {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

/* Permissions grid */
.permissions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-3);
}

.perm-checkbox {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  cursor: pointer;
}

.perm-checkbox__input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.perm-checkbox__box {
  width: 18px;
  height: 18px;
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color var(--tv-transition-fast), background-color var(--tv-transition-fast);
}

.perm-checkbox__input:checked + .perm-checkbox__box {
  background: var(--tv-primary);
  border-color: var(--tv-primary);
}

.perm-checkbox__input:checked + .perm-checkbox__box::after {
  content: '';
  width: 10px;
  height: 6px;
  border-left: 2px solid white;
  border-bottom: 2px solid white;
  transform: rotate(-45deg) translateY(-1px);
  display: block;
}

.perm-checkbox__label {
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
}

/* Aside */
.user-form-layout__aside {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  position: sticky;
  top: calc(var(--tv-header-height) + var(--tv-space-4));
}

.summary-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-5);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--tv-space-2);
  text-align: center;
}

.summary-card__photo-wrap {
  position: relative;
  width: 72px;
  height: 72px;
  margin-bottom: var(--tv-space-1);
}

.summary-card__avatar {
  width: 72px;
  height: 72px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  font-size: var(--tv-text-xl);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.summary-card__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.summary-card__photo-btn {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 24px;
  height: 24px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border: 2px solid var(--tv-bg-card);
  transition: filter var(--tv-transition-fast);
}

.summary-card__photo-btn:hover {
  filter: brightness(0.9);
}

.summary-card__name {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.summary-card__role {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

.summary-card__email {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  word-break: break-all;
}

.aside-actions {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.aside-actions__btn {
  width: 100%;
  justify-content: center;
}

/* Responsive */
@media (max-width: 1100px) {
  .user-form-layout {
    grid-template-columns: 1fr;
  }

  .user-form-layout__aside {
    position: static;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: flex-start;
  }

  .summary-card {
    flex: 1;
    min-width: 200px;
  }

  .aside-actions {
    flex-direction: row;
    flex: 1;
    min-width: 200px;
    align-self: flex-end;
  }

  .aside-actions__btn { width: auto; flex: 1; }
}

@media (max-width: 767px) {
  .user-form-page { padding: var(--tv-space-4); }

  .form-row { grid-template-columns: 1fr; }

  .permissions-grid { grid-template-columns: 1fr; }
}
</style>
