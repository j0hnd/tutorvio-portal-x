<template>
  <div class="ml-page">

    <!-- Header -->
    <div class="ml-header">
      <div class="ml-header__left">
        <h1 class="ml-title">Materials Library</h1>
        <p class="ml-subtitle">Browse and manage learning resources</p>
      </div>
      <button v-if="canManage" class="ml-upload-btn" type="button" @click="openUpload">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M7 1v8M3.5 4.5L7 1l3.5 3.5M2 11h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Upload Material
      </button>
    </div>

    <!-- Filters -->
    <div class="ml-filters">
      <div class="ml-search-wrap">
        <svg class="ml-search-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M10 10l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input v-model="search" class="ml-search" type="search" placeholder="Search..." />
      </div>
      <TVDateRangePicker class="ml-date-range" v-model="filterDateRange" placeholder="Upload date" />
      <TVSelect class="ml-filter-ctrl" v-model="filterCategory" :options="[{ value: '', label: 'All Categories' }, ...CATEGORIES]" placeholder="All Categories" />
      <TVSelect class="ml-filter-ctrl" v-model="filterLevel" :options="[{ value: '', label: 'All Levels' }, ...LEVELS]" placeholder="All Levels" />
      <TVSelect class="ml-filter-ctrl" v-model="filterType" :options="[{ value: '', label: 'All Types' }, ...TYPES]" placeholder="All Types" />
      <TVSelect v-if="canManage" class="ml-filter-ctrl" v-model="filterVisibility" :options="visibilityOptions" placeholder="All Visibility" />
      <button v-if="hasActiveFilters" class="ml-clear-btn" type="button" @click="clearFilters">Clear</button>
      <TVPagination
        v-if="filtered.length > ML_PAGE_SIZE"
        v-model="mlPage"
        :total="filtered.length"
        :page-size="ML_PAGE_SIZE"
        class="ml-pagination"
      />
    </div>

    <!-- Material list -->
    <div v-if="filtered.length" class="ml-list">
      <div v-for="mat in paginated" :key="mat.id" class="ml-item">
        <div :class="['ml-type-icon', `ml-type-icon--${mat.type.toLowerCase()}`]">
          {{ typeIcon(mat.type) }}
        </div>
        <div class="ml-item__body">
          <span class="ml-item__title">{{ mat.title }}</span>
          <p v-if="mat.description" class="ml-item__desc">{{ mat.description }}</p>
          <div class="ml-item__meta">
            <span v-if="mat.category" class="ml-item__cat">{{ categoryLabel(mat.category) }}</span>
            <span v-if="mat.category && mat.level" class="ml-meta-sep">·</span>
            <span v-if="mat.level" class="ml-item__level">{{ levelLabel(mat.level) }}</span>
            <span class="ml-meta-sep">·</span>
            <span>By {{ mat.uploadedByName }}</span>
            <span class="ml-meta-sep">·</span>
            <span>{{ formatDate(mat.uploadedAt) }}</span>
          </div>
        </div>
        <div class="ml-item__actions">
          <div class="ml-item__badges">
            <span :class="['ml-badge', `ml-badge--${mat.type.toLowerCase()}`]">{{ mat.type }}</span>
            <span v-if="canManage" :class="['ml-vis-badge', `ml-vis-badge--${(mat.visibility ?? 'student-visible')}`]">
              {{ visibilityLabel(mat.visibility) }}
            </span>
          </div>
          <a
            :href="mat.url"
            target="_blank"
            rel="noopener"
            :class="['ml-icon-btn', mat.type === 'LINK' ? 'ml-icon-btn--link' : 'ml-icon-btn--download']"
            :title="mat.type === 'LINK' ? 'Open link' : 'Download'"
          >
            <svg v-if="mat.type === 'LINK'" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M6 2H2.5A1.5 1.5 0 0 0 1 3.5v8A1.5 1.5 0 0 0 2.5 13h8A1.5 1.5 0 0 0 12 11.5V8M8 1h5m0 0v5m0-5L6.5 6.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M7 1v6m0 0L4.5 4.5M7 7l2.5-2.5M2 11.5h10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
          <button
            v-if="canManage"
            class="ml-icon-btn ml-icon-btn--delete"
            type="button"
            title="Delete"
            @click="confirmDelete(mat)"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M2 3.5h10M5 3.5V2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5v1M5.5 6v4M8.5 6v4M3 3.5l.7 8a.5.5 0 0 0 .5.5h5.6a.5.5 0 0 0 .5-.5l.7-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!filtered.length" class="ml-empty">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <rect x="6" y="4" width="24" height="32" rx="3" stroke="currentColor" stroke-width="1.5"/>
        <path d="M12 13h12M12 19h12M12 25h7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
      </svg>
      <p>No materials found{{ hasActiveFilters ? ' matching your filters' : '' }}.</p>
      <button v-if="hasActiveFilters" class="ml-clear-btn ml-clear-btn--center" type="button" @click="clearFilters">Clear filters</button>
    </div>

    <!-- Upload Modal -->
    <TVModal v-model="showUpload" title="Upload Material" maxWidth="520px">
      <TVInput v-model="form.title" label="Title" placeholder="e.g. Business Email Phrases" required />
      <div class="ml-form-row">
        <TVSelect v-model="form.type" label="Type" :options="TYPES" />
        <TVSelect v-model="form.visibility" label="Visibility" :options="visibilityOptions.filter(o => o.value !== '')" />
      </div>
      <TVInput v-model="form.url" label="File URL or Link" placeholder="https://..." required />
      <div class="ml-form-row">
        <TVSelect v-model="form.category" label="Category" :options="[{ value: '', label: 'None' }, ...CATEGORIES]" />
        <TVSelect v-model="form.level" label="Level" :options="[{ value: '', label: 'None' }, ...LEVELS]" />
      </div>
      <div class="ml-field">
        <label class="ml-label">Description <span class="ml-label-optional">(optional)</span></label>
        <textarea v-model="form.description" class="ml-textarea" rows="2" placeholder="Brief description of this resource" />
      </div>
      <template #footer>
        <button class="ml-btn ml-btn--ghost" type="button" @click="showUpload = false">Cancel</button>
        <button
          class="ml-btn ml-btn--primary"
          type="button"
          :disabled="!form.title.trim() || !form.url.trim()"
          @click="submitUpload"
        >Upload</button>
      </template>
    </TVModal>

    <!-- Delete confirm modal -->
    <TVModal v-model="showDeleteConfirm" title="Delete Material" maxWidth="400px">
      <p class="ml-confirm-text">Are you sure you want to delete <strong>{{ deletingMat?.title }}</strong>? This cannot be undone.</p>
      <template #footer>
        <button class="ml-btn ml-btn--ghost" type="button" @click="showDeleteConfirm = false">Cancel</button>
        <button class="ml-btn ml-btn--danger" type="button" @click="doDelete">Delete</button>
      </template>
    </TVModal>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore } from '@/stores/auth'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import TVModal from '@/components/ui/TVModal.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVDateRangePicker from '@/components/ui/TVDateRangePicker.vue'
import type { DateRange } from '@/components/ui/TVDateRangePicker.vue'
import TVPagination from '@/components/ui/TVPagination.vue'
import type { LessonMaterial, MaterialType, MaterialCategory, MaterialLevel, MaterialVisibility } from '@/stores/schedule'

const schedule = useScheduleStore()
const auth     = useAuthStore()
const toast    = useToast()
const { effectiveRole } = useViewAs()

const role     = computed(() => effectiveRole.value)
const canManage = computed(() => ['ADMIN', 'TEACHER'].includes(role.value))

// ── Constants ──
const CATEGORIES: { value: MaterialCategory; label: string }[] = [
  { value: 'beginner-english',       label: 'Beginner English' },
  { value: 'business-english',       label: 'Business English' },
  { value: 'travel-english',         label: 'Travel English' },
  { value: 'speaking-practice',      label: 'Speaking Practice' },
  { value: 'grammar-support',        label: 'Grammar Support' },
  { value: 'pronunciation-practice', label: 'Pronunciation Practice' },
  { value: 'tutorvio-custom',        label: 'Tutorvio Custom' },
]

const LEVELS: { value: MaterialLevel; label: string }[] = [
  { value: 'beginner',          label: 'Beginner' },
  { value: 'elementary',        label: 'Elementary' },
  { value: 'intermediate',      label: 'Intermediate' },
  { value: 'upper-intermediate', label: 'Upper Intermediate' },
  { value: 'advanced',          label: 'Advanced' },
  { value: 'all',               label: 'All Levels' },
]

const TYPES: { value: MaterialType; label: string }[] = [
  { value: 'PDF',      label: 'PDF' },
  { value: 'WORKSHEET', label: 'Worksheet' },
  { value: 'SLIDES',   label: 'Slides' },
  { value: 'DOCUMENT', label: 'Document' },
  { value: 'VIDEO',    label: 'Video' },
  { value: 'LINK',     label: 'Link' },
  { value: 'IMAGE',    label: 'Image' },
]

// ── Filters ──
const search           = ref('')
const filterCategory   = ref<MaterialCategory | ''>('')
const filterLevel      = ref<MaterialLevel | ''>('')
const filterType       = ref<MaterialType | ''>('')
const filterVisibility = ref<MaterialVisibility | ''>('')
const todayML          = new Date().toLocaleDateString('sv-SE')
const filterDateRange  = ref<DateRange>({ start: todayML, end: todayML })

const visibilityOptions = [
  { value: '',                label: 'All Visibility' },
  { value: 'public',          label: 'Public' },
  { value: 'student-visible', label: 'Student Visible' },
  { value: 'teacher-only',    label: 'Teacher Only' },
]

const hasActiveFilters = computed(() =>
  !!search.value || !!filterCategory.value || !!filterLevel.value ||
  !!filterType.value || !!filterVisibility.value || !!(filterDateRange.value.start || filterDateRange.value.end)
)

function clearFilters(): void {
  search.value = ''
  filterCategory.value = ''
  filterLevel.value = ''
  filterType.value = ''
  filterVisibility.value = ''
  filterDateRange.value = { start: '', end: '' }
}

// ── Data ──
const materials = computed(() => schedule.getLibraryMaterials(role.value))

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  return materials.value.filter(m => {
    if (q && !m.title.toLowerCase().includes(q) && !m.description?.toLowerCase().includes(q)) return false
    if (filterCategory.value && m.category !== filterCategory.value) return false
    if (filterLevel.value && m.level !== filterLevel.value && m.level !== 'all') return false
    if (filterType.value && m.type !== filterType.value) return false
    if (filterVisibility.value && (m.visibility ?? 'student-visible') !== filterVisibility.value) return false
    const d = m.uploadedAt.slice(0, 10)
    if (filterDateRange.value.start && d < filterDateRange.value.start) return false
    if (filterDateRange.value.end   && d > filterDateRange.value.end)   return false
    return true
  })
})

const ML_PAGE_SIZE = 5
const mlPage = ref(1)
const paginated = computed(() =>
  filtered.value.slice((mlPage.value - 1) * ML_PAGE_SIZE, mlPage.value * ML_PAGE_SIZE)
)
watch(filtered, () => { mlPage.value = 1 })

// ── Upload ──
const showUpload = ref(false)
const form = reactive({
  title: '', type: 'PDF' as MaterialType, url: '',
  category: '' as MaterialCategory | '',
  level: '' as MaterialLevel | '',
  visibility: 'student-visible' as MaterialVisibility,
  description: '',
})

function openUpload(): void {
  form.title = ''; form.type = 'PDF'; form.url = ''
  form.category = ''; form.level = ''; form.visibility = 'student-visible'; form.description = ''
  showUpload.value = true
}

function submitUpload(): void {
  const uploaderName = auth.user ? `${auth.user.firstName} ${auth.user.lastName}` : 'Unknown'
  schedule.addLibraryMaterial({
    title: form.title.trim(),
    type: form.type,
    url: form.url.trim(),
    uploaderName,
    uploaderId: auth.user?.id ?? '',
    category: form.category || undefined,
    level: form.level || undefined,
    visibility: form.visibility,
    description: form.description.trim() || undefined,
  })
  showUpload.value = false
  toast.success('Material uploaded successfully!')
}

// ── Delete ──
const showDeleteConfirm = ref(false)
const deletingMat = ref<LessonMaterial | null>(null)

function confirmDelete(mat: LessonMaterial): void {
  deletingMat.value = mat
  showDeleteConfirm.value = true
}

function doDelete(): void {
  if (!deletingMat.value) return
  schedule.deleteMaterial(deletingMat.value.id)
  showDeleteConfirm.value = false
  deletingMat.value = null
  toast.success('Material deleted.')
}

// ── Helpers ──
function typeIcon(type: MaterialType): string {
  switch (type) {
    case 'PDF':       return '📄'
    case 'WORKSHEET': return '📋'
    case 'SLIDES':    return '📊'
    case 'VIDEO':     return '🎬'
    case 'LINK':      return '🔗'
    case 'DOCUMENT':  return '📝'
    case 'IMAGE':     return '🖼'
    default:          return '📎'
  }
}

function visibilityLabel(v?: string): string {
  if (v === 'public') return 'Public'
  if (v === 'teacher-only') return 'Teacher Only'
  return 'Student Visible'
}

function categoryLabel(c?: string): string {
  return CATEGORIES.find(x => x.value === c)?.label ?? (c ?? '')
}

function levelLabel(l?: string): string {
  return LEVELS.find(x => x.value === l)?.label ?? (l ?? '')
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<style scoped>
.ml-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

@media (max-width: 767px) { .ml-page { padding: var(--tv-space-4); } }

/* Header */
.ml-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}
.ml-title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  margin: 0;
}
.ml-subtitle {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: var(--tv-space-1) 0 0;
}
.ml-upload-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-2) var(--tv-space-4);
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
  border: none;
  border-radius: var(--tv-radius);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  cursor: pointer;
  transition: background 0.15s;
  white-space: nowrap;
  flex-shrink: 0;
}
.ml-upload-btn:hover { background: var(--tv-primary-hover); }

/* Filters */
.ml-filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--tv-space-2);
  align-items: center;
}
.ml-search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
  max-width: 360px;
}
.ml-filter-ctrl { width: 160px; flex-shrink: 0; }
.ml-date-range  { width: 200px; flex-shrink: 0; }
.ml-pagination { margin-left: auto; flex-shrink: 0; }
.ml-search-icon {
  position: absolute;
  left: var(--tv-space-3);
  top: 50%;
  transform: translateY(-50%);
  color: var(--tv-text-muted);
  pointer-events: none;
}
.ml-search {
  width: 100%;
  padding: var(--tv-space-2) var(--tv-space-3) var(--tv-space-2) calc(var(--tv-space-3) + 20px);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius);
  min-height: 38px;
  outline: none;
  transition: border-color 0.15s;
  font-family: inherit;
  box-sizing: border-box;
}
.ml-search:focus { border-color: var(--tv-primary); }
.ml-filter-select {
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius);
  min-height: 38px;
  outline: none;
  cursor: pointer;
  transition: border-color 0.15s;
  font-family: inherit;
}
.ml-filter-select:focus { border-color: var(--tv-primary); }
.ml-clear-btn {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  background: none;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  padding: 0 var(--tv-space-3);
  min-height: 42px; min-width: 72px; display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, background 0.15s;
}
.ml-clear-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }
.ml-clear-btn--center { align-self: center; }

/* Meta */
.ml-meta {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
}
.ml-count {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
}
.ml-filtered-note {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
}

/* List */
.ml-list {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

/* Item */
.ml-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-4);
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.ml-item:hover {
  border-color: var(--tv-primary-muted);
  box-shadow: 0 2px 8px hsla(var(--tv-primary-h), var(--tv-primary-s), 50%, 0.07);
}

.ml-type-icon {
  font-size: 22px;
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--tv-radius);
  flex-shrink: 0;
  background: var(--tv-bg-soft);
}
.ml-type-icon--pdf      { background: hsl(0, 80%, 95%); }
.ml-type-icon--worksheet { background: hsl(220, 80%, 95%); }
.ml-type-icon--slides   { background: hsl(280, 70%, 95%); }
.ml-type-icon--video    { background: hsl(340, 80%, 95%); }
.ml-type-icon--link     { background: hsl(195, 80%, 95%); }
.ml-type-icon--document { background: hsl(38, 90%, 95%); }
.ml-type-icon--image    { background: hsl(145, 60%, 95%); }

.ml-item__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-1);
}
.ml-item__title {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
}
.ml-badge {
  font-size: 10px;
  font-weight: var(--tv-font-semibold);
  padding: 2px 7px;
  border-radius: var(--tv-radius-full);
  border: 1px solid;
  text-transform: uppercase;
  letter-spacing: .03em;
}
.ml-badge--pdf       { background: hsl(0,80%,95%); color: hsl(0,70%,40%); border-color: hsl(0,70%,82%); }
.ml-badge--worksheet { background: hsl(220,80%,95%); color: hsl(220,65%,40%); border-color: hsl(220,65%,80%); }
.ml-badge--slides    { background: hsl(280,70%,95%); color: hsl(280,60%,40%); border-color: hsl(280,60%,80%); }
.ml-badge--video     { background: hsl(340,80%,95%); color: hsl(340,65%,40%); border-color: hsl(340,65%,82%); }
.ml-badge--link      { background: hsl(195,80%,95%); color: hsl(195,65%,35%); border-color: hsl(195,65%,78%); }
.ml-badge--document  { background: hsl(38,90%,93%); color: hsl(38,70%,35%); border-color: hsl(38,70%,78%); }
.ml-badge--image     { background: hsl(145,60%,93%); color: hsl(145,50%,30%); border-color: hsl(145,50%,72%); }

.ml-vis-badge {
  font-size: 10px;
  font-weight: var(--tv-font-semibold);
  padding: 2px 7px;
  border-radius: var(--tv-radius-full);
  border: 1px solid;
  white-space: nowrap;
}
.ml-vis-badge--public           { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ml-vis-badge--student-visible  { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.ml-vis-badge--teacher-only     { background: hsl(38,90%,93%); color: hsl(38,70%,35%); border-color: hsl(38,70%,78%); }

.ml-item__desc {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
  margin: 0;
  line-height: 1.5;
}
.ml-item__meta {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  flex-wrap: wrap;
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  margin-top: 2px;
}
.ml-item__cat, .ml-item__level {
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
}
.ml-meta-sep { color: var(--tv-border); }

/* Actions */
.ml-item__actions {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  flex-shrink: 0;
}
.ml-item__badges {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
  padding-right: var(--tv-space-2);
  border-right: 1px solid var(--tv-border);
}
.ml-icon-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--tv-radius-sm);
  border: 1px solid;
  cursor: pointer;
  text-decoration: none;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.ml-icon-btn--download {
  background: var(--tv-success-soft);
  color: var(--tv-success-fg);
  border-color: var(--tv-success-border);
}
.ml-icon-btn--download:hover { background: hsl(142, 70%, 87%); }
.ml-icon-btn--link {
  background: var(--tv-success-soft);
  color: var(--tv-success-fg);
  border-color: var(--tv-success-border);
}
.ml-icon-btn--link:hover { background: hsl(142, 70%, 87%); }
.ml-icon-btn--delete {
  background: var(--tv-danger-soft);
  color: var(--tv-danger-fg);
  border-color: var(--tv-danger-border);
}
.ml-icon-btn--delete:hover { background: hsl(0, 72%, 88%); }

/* Empty */
.ml-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-10) var(--tv-space-4);
  color: var(--tv-text-muted);
  text-align: center;
}
.ml-empty svg { opacity: 0.35; }
.ml-empty p { font-size: var(--tv-text-sm); margin: 0; }

/* Form */
.ml-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--tv-space-3); }
.ml-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.ml-label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  display: flex;
  gap: var(--tv-space-1);
}
.ml-label-optional { color: var(--tv-text-muted); font-weight: normal; }
.ml-select {
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius);
  min-height: 42px;
  outline: none;
  cursor: pointer;
  font-family: inherit;
  transition: border-color 0.15s;
}
.ml-select:focus { border-color: var(--tv-primary); }
.ml-textarea {
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius);
  resize: vertical;
  outline: none;
  font-family: inherit;
  line-height: 1.5;
  transition: border-color 0.15s;
}
.ml-textarea:focus { border-color: var(--tv-primary); }

/* Confirm text */
.ml-confirm-text {
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  line-height: 1.6;
  margin: 0;
}

/* Buttons */
.ml-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  border-radius: var(--tv-radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  font-family: inherit;
  transition: background 0.15s;
}
.ml-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ml-btn--primary { background: var(--tv-primary); color: var(--tv-text-inverse); }
.ml-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.ml-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.ml-btn--ghost:hover:not(:disabled) { background: var(--tv-bg-soft); }
.ml-btn--danger { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.ml-btn--danger:hover:not(:disabled) { background: var(--tv-danger-fg); color: white; }

@media (max-width: 600px) {
  .ml-item { flex-wrap: wrap; align-items: flex-start; }
  .ml-item__actions { width: 100%; justify-content: flex-end; }
  .ml-form-row { grid-template-columns: 1fr; }
  .ml-search-wrap { width: 100%; }
}
</style>
