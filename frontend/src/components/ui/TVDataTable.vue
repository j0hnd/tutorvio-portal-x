<template>
  <div class="tv-dt">

    <!-- Loading -->
    <div v-if="loading" class="tv-dt__loading" aria-live="polite">
      <span class="tv-dt__spinner" aria-hidden="true" />
      {{ loadingText }}
    </div>

    <!-- Table -->
    <template v-else>
      <div v-if="hasRows" class="tv-dt__wrap">
        <div class="tv-dt__scroll">
          <table class="tv-dt__table" :aria-label="ariaLabel">
            <thead>
              <tr>
                <th
                  v-for="col in columns"
                  :key="col.key"
                  class="tv-dt__th"
                  :class="[
                    col.align === 'right' && 'tv-dt__th--right',
                    col.align === 'center' && 'tv-dt__th--center',
                    col.hide === 'sm' && 'tv-dt__th--hide-sm',
                    col.hide === 'md' && 'tv-dt__th--hide-md',
                    col.sortable && 'tv-dt__th--sortable',
                  ]"
                  :style="col.width ? { width: col.width } : undefined"
                  :aria-sort="ariaSortAttr(col)"
                >
                  <button
                    v-if="col.sortable"
                    class="tv-dt__sort-btn"
                    :class="sortKey === (col.sortKey ?? col.key) && 'tv-dt__sort-btn--active'"
                    @click="toggleSort(col.sortKey ?? col.key)"
                  >
                    {{ col.label }}
                    <span class="tv-dt__sort-icon" aria-hidden="true">
                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path
                          d="M6 2.5L3 6h6L6 2.5z"
                          :fill="sortKey === (col.sortKey ?? col.key) && sortDir === 'asc' ? 'currentColor' : 'none'"
                          stroke="currentColor"
                          stroke-width="1.2"
                          stroke-linejoin="round"
                        />
                        <path
                          d="M6 9.5L9 6H3l3 3.5z"
                          :fill="sortKey === (col.sortKey ?? col.key) && sortDir === 'desc' ? 'currentColor' : 'none'"
                          stroke="currentColor"
                          stroke-width="1.2"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </span>
                  </button>
                  <span v-else>{{ col.label }}</span>
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(row, idx) in paginatedRows"
                :key="rowKey ? String(row[rowKey]) : idx"
                class="tv-dt__row"
                :class="clickable && 'tv-dt__row--clickable'"
                :tabindex="clickable ? 0 : undefined"
                @click="clickable && $emit('row-click', row)"
                @keydown.enter="clickable && $emit('row-click', row)"
              >
                <td
                  v-for="col in columns"
                  :key="col.key"
                  class="tv-dt__td"
                  :class="[
                    col.align === 'right' && 'tv-dt__td--right',
                    col.align === 'center' && 'tv-dt__td--center',
                    col.hide === 'sm' && 'tv-dt__td--hide-sm',
                    col.hide === 'md' && 'tv-dt__td--hide-md',
                    col.muted && 'tv-dt__td--muted',
                  ]"
                  @click="col.stopClick ? $event.stopPropagation() : undefined"
                >
                  <slot :name="`cell-${col.key}`" :row="row" :value="getCellValue(row, col)">
                    {{ getCellValue(row, col) }}
                  </slot>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer -->
        <div class="tv-dt__footer">
          <p class="tv-dt__count" aria-live="polite">
            Showing
            <strong>{{ fromItem }}</strong>–<strong>{{ toItem }}</strong>
            of <strong>{{ effectiveTotalRows }}</strong>
            {{ effectiveTotalRows === 1 ? 'entry' : 'entries' }}
          </p>

          <div class="tv-dt__pagination" role="navigation" aria-label="Table pagination">
            <!-- Per-page -->
            <div class="tv-dt__per-page">
              <label :for="`${uid}-per-page`" class="tv-dt__per-page-label">Per page</label>
              <select
                :id="`${uid}-per-page`"
                :value="localPageSize"
                class="tv-dt__per-page-select"
                @change="onPageSizeChange"
              >
                <option v-for="n in pageSizeOptions" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>

            <!-- Page buttons -->
            <div class="tv-dt__pages">
              <button
                class="tv-dt__page-btn"
                :disabled="currentPage === 1"
                aria-label="First page"
                @click="currentPage = 1"
              >
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M9 3L5 7l4 4M4 3v8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button
                class="tv-dt__page-btn"
                :disabled="currentPage === 1"
                aria-label="Previous page"
                @click="currentPage--"
              >
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M9 3L5 7l4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>

              <template v-for="page in pageNumbers" :key="page">
                <span v-if="page === '...'" class="tv-dt__page-ellipsis">…</span>
                <button
                  v-else
                  class="tv-dt__page-btn tv-dt__page-btn--num"
                  :class="page === currentPage && 'tv-dt__page-btn--active'"
                  :aria-label="`Page ${page}`"
                  :aria-current="page === currentPage ? 'page' : undefined"
                  @click="currentPage = Number(page)"
                >
                  {{ page }}
                </button>
              </template>

              <button
                class="tv-dt__page-btn"
                :disabled="currentPage === totalPages"
                aria-label="Next page"
                @click="currentPage++"
              >
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button
                class="tv-dt__page-btn"
                :disabled="currentPage === totalPages"
                aria-label="Last page"
                @click="currentPage = totalPages"
              >
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M5 3l4 4-4 4M10 3v8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="tv-dt__empty" aria-live="polite">
        <slot name="empty">
          <div class="tv-dt__empty-icon" aria-hidden="true">
            <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
              <rect x="4" y="8" width="28" height="20" rx="3" stroke="currentColor" stroke-width="1.8"/>
              <path d="M4 14h28" stroke="currentColor" stroke-width="1.8"/>
              <path d="M10 22h6M10 26h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <p class="tv-dt__empty-title">{{ emptyTitle }}</p>
          <p class="tv-dt__empty-sub">{{ emptySubtitle }}</p>
        </slot>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'

/* ── Types ── */
export interface DataTableColumn {
  key: string
  label: string
  sortable?: boolean
  sortKey?: string
  align?: 'left' | 'center' | 'right'
  hide?: 'sm' | 'md'
  width?: string
  muted?: boolean
  stopClick?: boolean
}

export interface DataTableFetchParams {
  page: number
  pageSize: number
  sortKey: string | null
  sortDir: 'asc' | 'desc'
}

type Row = Record<string, unknown>
type SortDir = 'asc' | 'desc'

const props = withDefaults(
  defineProps<{
    columns: DataTableColumn[]
    rows: Row[]
    loading?: boolean
    loadingText?: string
    rowKey?: string
    pageSize?: number
    pageSizeOptions?: number[]
    clickable?: boolean
    ariaLabel?: string
    emptyTitle?: string
    emptySubtitle?: string
    /** Enable server-side mode. The component emits `fetch` on every
     *  page / sort / page-size change instead of processing rows itself. */
    serverSide?: boolean
    /** Total record count from the server — required in server-side mode
     *  so the component can calculate total pages correctly. */
    totalRows?: number
  }>(),
  {
    loading: false,
    loadingText: 'Loading…',
    rowKey: 'id',
    pageSize: 10,
    pageSizeOptions: () => [10, 25, 50],
    clickable: false,
    ariaLabel: 'Data table',
    emptyTitle: 'No results found',
    emptySubtitle: 'Try adjusting your search or filters',
    serverSide: false,
    totalRows: 0,
  },
)

const emit = defineEmits<{
  'row-click': [row: Row]
  /** Fired on mount and whenever page / sort / page-size changes.
   *  Only active when serverSide === true. */
  'fetch': [params: DataTableFetchParams]
}>()

/* ── Unique ID for labels ── */
const uid = `tv-dt-${Math.random().toString(36).slice(2, 7)}`

/* ── Sort state ── */
const sortKey = ref<string | null>(null)
const sortDir = ref<SortDir>('asc')

function toggleSort(key: string): void {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
  currentPage.value = 1
}

function ariaSortAttr(col: DataTableColumn): 'ascending' | 'descending' | 'none' | undefined {
  if (!col.sortable) return undefined
  const k = col.sortKey ?? col.key
  if (sortKey.value !== k) return 'none'
  return sortDir.value === 'asc' ? 'ascending' : 'descending'
}

/* ── Sort rows (client-side only) ── */
const sortedRows = computed<Row[]>(() => {
  if (props.serverSide) return props.rows
  if (!sortKey.value) return props.rows
  const key = sortKey.value
  const dir = sortDir.value === 'asc' ? 1 : -1
  return [...props.rows].sort((a, b) => {
    const av = a[key] ?? ''
    const bv = b[key] ?? ''
    if (av < bv) return -1 * dir
    if (av > bv) return 1 * dir
    return 0
  })
})

/* ── Pagination ── */
const currentPage = ref(1)
const localPageSize = ref(props.pageSize)

/* In client mode reset to page 1 when the row set changes (e.g. filter applied).
   In server mode the parent controls rows, so we must NOT reset — the parent
   already updates rows in response to the fetch event. */
watch(
  () => props.rows,
  () => { if (!props.serverSide) currentPage.value = 1 },
)

function onPageSizeChange(e: Event): void {
  localPageSize.value = Number((e.target as HTMLSelectElement).value)
  currentPage.value = 1
}

/* ── Server-side fetch emitter ── */
watch(
  [currentPage, localPageSize, sortKey, sortDir],
  () => {
    if (!props.serverSide) return
    emit('fetch', {
      page: currentPage.value,
      pageSize: localPageSize.value,
      sortKey: sortKey.value,
      sortDir: sortDir.value,
    })
  },
  { immediate: true },
)

/* ── Total count used for pagination math ── */
const effectiveTotalRows = computed(() =>
  props.serverSide ? props.totalRows : sortedRows.value.length,
)

const totalPages = computed(() =>
  Math.max(1, Math.ceil(effectiveTotalRows.value / localPageSize.value)),
)

const fromItem = computed(() =>
  effectiveTotalRows.value === 0 ? 0 : (currentPage.value - 1) * localPageSize.value + 1,
)

const toItem = computed(() =>
  Math.min(currentPage.value * localPageSize.value, effectiveTotalRows.value),
)

/* ── Paginated rows (client-side only; server already returns one page) ── */
const paginatedRows = computed(() => {
  if (props.serverSide) return props.rows
  const start = (currentPage.value - 1) * localPageSize.value
  return sortedRows.value.slice(start, start + localPageSize.value)
})

/* ── Whether to show table vs empty state ── */
const hasRows = computed(() =>
  props.serverSide ? props.totalRows > 0 : sortedRows.value.length > 0,
)

/* Page number buttons with ellipsis */
const pageNumbers = computed<(number | '...')[]>(() => {
  const total = totalPages.value
  const current = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages: (number | '...')[] = [1]
  if (current > 3) pages.push('...')
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    pages.push(i)
  }
  if (current < total - 2) pages.push('...')
  pages.push(total)
  return pages
})

/* ── Cell value helper ── */
function getCellValue(row: Row, col: DataTableColumn): unknown {
  const key = col.sortKey ?? col.key
  return row[key] ?? row[col.key] ?? ''
}
</script>

<style scoped>
/* ── Wrapper ── */
.tv-dt {
  display: contents;
}

/* ── Loading ── */
.tv-dt__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-10);
  color: var(--tv-text-muted);
  font-size: var(--tv-text-sm);
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
}

.tv-dt__spinner {
  width: 20px;
  height: 20px;
  border: 2px solid var(--tv-border);
  border-top-color: var(--tv-primary);
  border-radius: 50%;
  animation: tv-spin 0.7s linear infinite;
  flex-shrink: 0;
}

@keyframes tv-spin { to { transform: rotate(360deg); } }

/* ── Table card ── */
.tv-dt__wrap {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  overflow: hidden;
}

.tv-dt__scroll {
  overflow-x: auto;
}

/* ── Table ── */
.tv-dt__table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: var(--tv-text-sm);
}

/* ── Header ── */
.tv-dt__th {
  padding: var(--tv-space-3) var(--tv-space-4);
  text-align: left;
  font-weight: var(--tv-font-semibold);
  font-size: var(--tv-text-xs);
  color: var(--tv-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: var(--tv-bg-soft);
  border-bottom: 1px solid var(--tv-border);
  white-space: nowrap;
  user-select: none;
}

.tv-dt__th--right { text-align: right; }
.tv-dt__th--center { text-align: center; }

/* ── Sort button ── */
.tv-dt__sort-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  font: inherit;
  font-weight: var(--tv-font-semibold);
  font-size: var(--tv-text-xs);
  color: var(--tv-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  transition: color var(--tv-transition-fast);
}

.tv-dt__sort-btn:hover,
.tv-dt__sort-btn--active {
  color: var(--tv-primary);
}

.tv-dt__sort-icon {
  display: inline-flex;
  opacity: 0.4;
  transition: opacity var(--tv-transition-fast);
}

.tv-dt__sort-btn:hover .tv-dt__sort-icon,
.tv-dt__sort-btn--active .tv-dt__sort-icon {
  opacity: 1;
}

/* ── Rows ── */
.tv-dt__row {
  transition: background-color var(--tv-transition-fast);
}

.tv-dt__row--clickable {
  cursor: pointer;
}

.tv-dt__row--clickable:hover {
  background: var(--tv-bg-soft);
}

.tv-dt__row--clickable:focus-visible {
  outline: 2px solid var(--tv-primary);
  outline-offset: -2px;
}

.tv-dt__row:not(:last-child) .tv-dt__td {
  border-bottom: 1px solid var(--tv-border);
}

/* ── Cells ── */
.tv-dt__td {
  padding: var(--tv-space-3) var(--tv-space-4);
  vertical-align: middle;
  color: var(--tv-text);
  overflow: hidden;
}

.tv-dt__td--right { text-align: right; }
.tv-dt__td--center { text-align: center; }
.tv-dt__td--muted { color: var(--tv-text-muted); }

/* ── Footer ── */
.tv-dt__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--tv-space-4);
  padding: var(--tv-space-3) var(--tv-space-4);
  border-top: 1px solid var(--tv-border);
  flex-wrap: wrap;
}

.tv-dt__count {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  margin: 0;
}

.tv-dt__count strong {
  color: var(--tv-text-secondary);
  font-weight: var(--tv-font-semibold);
}

/* ── Pagination ── */
.tv-dt__pagination {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
}

.tv-dt__per-page {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
}

.tv-dt__per-page-label {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  white-space: nowrap;
}

.tv-dt__per-page-select {
  padding: var(--tv-space-1) var(--tv-space-2);
  font-size: var(--tv-text-xs);
  color: var(--tv-text);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  cursor: pointer;
  transition: border-color var(--tv-transition-fast);
}

.tv-dt__per-page-select:focus {
  outline: none;
  border-color: var(--tv-primary);
}

.tv-dt__pages {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
}

.tv-dt__page-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 30px;
  height: 30px;
  padding: 0 var(--tv-space-2);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  color: var(--tv-text-secondary);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  cursor: pointer;
  transition: border-color var(--tv-transition-fast), background-color var(--tv-transition-fast), color var(--tv-transition-fast);
}

.tv-dt__page-btn:hover:not(:disabled) {
  border-color: var(--tv-primary);
  color: var(--tv-primary);
  background: var(--tv-primary-soft);
}

.tv-dt__page-btn:disabled {
  opacity: 0.38;
  cursor: not-allowed;
}

.tv-dt__page-btn--active {
  background: var(--tv-primary);
  border-color: var(--tv-primary);
  color: var(--tv-text-inverse);
}

.tv-dt__page-btn--active:hover:not(:disabled) {
  background: var(--tv-primary-hover);
  border-color: var(--tv-primary-hover);
  color: var(--tv-text-inverse);
}

.tv-dt__page-ellipsis {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  padding: 0 var(--tv-space-1);
  line-height: 30px;
}

/* ── Empty ── */
.tv-dt__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-16) var(--tv-space-6);
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  text-align: center;
}

.tv-dt__empty-icon {
  width: 72px;
  height: 72px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.tv-dt__empty-title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.tv-dt__empty-sub {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

/* ── Responsive ── */
@media (max-width: 1100px) {
  .tv-dt__th--hide-md,
  .tv-dt__td--hide-md {
    display: none;
  }

  .tv-dt__footer {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 767px) {
  .tv-dt__th--hide-sm,
  .tv-dt__td--hide-sm {
    display: none;
  }

  .tv-dt__pagination {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--tv-space-2);
  }
}
</style>
