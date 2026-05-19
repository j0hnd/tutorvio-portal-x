# Tutorvio Engineering & Workflow Guidelines

To ensure the Tutorvio LMS is scalable, maintainable, and high-performance, all development should adhere to the following Vue 3 best practices and project-specific rules.

---

## 0. Design Inspiration & Vision

### Reference Sites
*   **Functional Inspiration — [TutorBird](https://www.tutorbird.com/):** Study its feature set (scheduling, student management, invoicing, student portal, multi-staff). Do **not** copy its visual design. Use it to understand what a mature tutoring platform offers, and build equivalents that feel distinctly "Tutorvio."
*   **UI Inspiration — [Tutorvio Connect Hub](https://tutorvio-connect-hub.lovable.app/):** This is the closest visual reference. Key traits to replicate and exceed:
    *   Left sidebar navigation with icon + label items
    *   Dashboard with stat cards (completed lessons, hours, ratings, credits)
    *   Lesson cards with teacher/student avatar, time, subject, and a clear CTA ("Join", "View")
    *   Compact activity feed in a secondary column
    *   Wallet/credit balance displayed prominently in the user profile area
    *   Warm, friendly tone — light emoji use in headings is acceptable (e.g., "Welcome back, Emma 👋")

### Core UI Principles
1.  **Intuitive over clever** — Every action must be discoverable without instructions. Label icons, use familiar patterns, and place primary CTAs where the eye lands first.
2.  **Mobile-first, not mobile-afterthought** — Design the mobile layout before the desktop layout. The sidebar collapses to a bottom tab bar on mobile. Cards stack vertically. Touch targets are at least 44px.
3.  **Smooth & alive** — UI should feel responsive through micro-interactions: button press feedback, skeleton loaders instead of spinners for content, smooth sidebar open/close, page transition fades. Nothing should feel abrupt or static.
4.  **Consistent density** — Desktop views use comfortable whitespace. Mobile views are compact but never cramped. Never hide critical information behind extra taps on desktop.

---

## 1. Vue 3 Best Practices

### Composition API & `<script setup>`
*   Always use `<script setup>` for a more concise and readable syntax.
*   Leverage **Composables** (`src/composables`) for reusable logic (e.g., `useCalendar`, `useAuth`, `usePermissions`).

### State Management (Pinia)
*   Use Pinia for global state (User session, Global Settings, Notification counts).
*   Avoid overusing Pinia; if state is local to a single component or page, keep it local.
*   **Modular Stores:** One file per store (e.g., `authStore.ts`, `lessonStore.ts`, `billingStore.ts`).

### Component Architecture
*   **Atomic Components:** Keep UI components (`src/components/ui`) small and generic.
*   **Business Components:** Components in `src/components/features` should handle specific domain logic (e.g., `LessonCard`, `TeacherAvailabilityGrid`).
*   **Prop Consistency:** Use descriptive prop names and define them with `defineProps<{ ... }>()` for TypeScript safety.

### Performance & Optimization
*   **Route Lazy Loading:** Use dynamic imports for routes to reduce initial bundle size.
*   **V-If vs V-Show:** Use `v-if` for expensive components that are rarely toggled, and `v-show` for frequent toggles.
*   **Clean Up:** Always clear event listeners, timers, or WebSocket connections in `onUnmounted`.

---

## 2. Directory Structure

```text
src/
├── assets/             # Static assets (images, fonts)
├── components/
│   ├── ui/             # Generic, reusable UI elements (Buttons, Inputs)
│   ├── features/       # Feature-specific components (Calendar, DashboardWidgets)
│   └── layout/         # Layout components (Sidebar, Navbar)
├── composables/        # Shared logic / hooks
├── stores/             # Pinia stores
├── views/              # Page-level components
├── styles/             # Global CSS, variables, and themes
├── types/              # TypeScript interfaces and types
├── utils/              # Helper functions (Date formatting, Currency conversion)
└── router/             # Vue Router configuration
```

---

## 3. Workflow Rules

### Rule 1: Design-First Approach
Before writing logic for a new feature, implement the **visual structure** first.
1.  Define the CSS variables and base styles.
2.  Create the HTML skeleton with mock data.
3.  Once the UI feels "premium," add the reactive logic.

### Rule 2: CSS Methodology
*   **Modern CSS:** Use Vanilla CSS with CSS Variables (`--tv-primary`, `--tv-bg-soft`).
*   **Scoped Styles:** Prefer `scoped` styles in `.vue` files to prevent global pollution.
*   **Responsive Design:** Use Flexbox/Grid. Test every view on Mobile, Tablet, and Desktop.
*   **No Magic Numbers:** Use a consistent spacing scale (e.g., `gap: 1rem`, `padding: 1.5rem`).
*   **No Inline Styles:** Never use `:style="{ ... }"` or `style="..."` in templates. All visual variations must be expressed as CSS classes (BEM modifiers or utility classes). Dynamic color/sizing driven by data belongs in named CSS classes (e.g., `icon-badge--teal`), not inline property bindings.
*   **Reusable Utility Classes:** Shared visual patterns (icon color badges, status rings, truncated text) must be defined as utility classes in `src/styles/main.css` — not duplicated per component. Before writing a new scoped style, check if a global utility already covers it.
*   **BEM Naming in Views:** View-level styles follow BEM: `.panel`, `.panel__header`, `.panel__title`, `.panel--highlighted`. Avoid generic names like `.box`, `.wrap`, `.container` that clash across views.

### Rule 3: Mobile-First Responsive Design
*   Write base styles for **mobile (≤ 640px)** first, then override with `@media (min-width: …)` for tablet and desktop.
*   **Breakpoints:**
    *   `sm`: 640px — single-column cards become two-column
    *   `md`: 768px — sidebar appears, bottom nav hides
    *   `lg`: 1024px — multi-column dashboards
    *   `xl`: 1280px — max content width cap
*   The **sidebar** must collapse to a **bottom tab bar** on mobile (max 5 items, icon + label).
*   The **header** on mobile shows only the logo, a hamburger/close toggle, and the user avatar.
*   All cards must be **full-width on mobile** and use `min-width: 0` inside flex/grid to prevent overflow.
*   Touch targets (buttons, nav items, links) must be a minimum of **44px × 44px**.
*   Avoid `hover`-only interactions — always provide a tap-equivalent or visible affordance.

### Rule 4: Smooth UI & Micro-interactions
*   **Skeleton loaders** for all async content — never show a blank card or empty state before data resolves. Use a `TVSkeleton` component with the shimmer animation.
*   **Page transitions:** Use Vue's `<Transition name="page">` with a subtle `opacity + translateY(6px)` enter/leave — already defined in `main.css`.
*   **Sidebar open/close:** Animate width with `transition: width 250ms cubic-bezier(0.4, 0, 0.2, 1)`. On mobile, animate a drawer sliding in from the left with an overlay backdrop.
*   **Button press:** All buttons must have a `transform: translateY(1px)` on `:active` — already built into `TVButton`.
*   **Form field focus:** Inputs must animate their focus ring in with a 150ms ease — already built into `TVInput`.
*   **Toast notifications:** Slide in from the top-right with `opacity + translateX(12px)`, auto-dismiss after 4 seconds.
*   **Card hover:** Hoverable cards rise with `translateY(-2px)` + a deeper shadow — already built into `TVCard`.
*   **Avoid layout shift:** Reserve space for avatars and images with fixed dimensions or aspect ratios before they load.
*   **Reduced motion:** Wrap all non-essential animations in `@media (prefers-reduced-motion: no-preference)` so users who opt out get instant transitions.

### Rule 5: Accessibility (A11y)
*   Use semantic HTML (`<header>`, `<main>`, `<section>`, `<article>`).
*   Ensure all buttons have `aria-label` if they are icon-only.
*   Maintain a high contrast ratio for text readability.
*   All interactive elements must be keyboard-accessible.

### Rule 6: TypeScript First
*   Never use `any`. Define interfaces for all API responses and component props.
*   Place global types in `src/types/index.d.ts` or specific domain files (e.g., `src/types/lesson.ts`).

### Rule 7: Error Handling & UX
*   Always provide feedback for async actions (Loading spinners, Toast notifications).
*   Implement "Graceful Failure": If an API call fails, show a helpful message, not an empty screen.

---

## 4. Feature Implementation Workflow

When starting a new feature (e.g., "Lesson Notes"), follow these steps:

1.  **Define Interface:** Create the TypeScript interface for the data.
2.  **Mock State:** Create a temporary Pinia store with hardcoded data.
3.  **Build View:** Create the page in `src/views` using UI components.
4.  **Add Logic:** Implement the interaction (editing, saving, deleting).
5.  **Refactor:** Extract reusable parts into `src/components` or `src/composables`.
6.  **Final Polish:** Add micro-animations and transitions.

---

## 5. Testing Strategy (Required — Never Re-test Manually)

Every feature built must be covered by automated tests so it never needs to be manually re-verified in future phases. Once a component or feature is tested and passing, it is considered stable.

### Stack
*   **Unit / Component tests:** [Vitest](https://vitest.dev/) + [@vue/test-utils](https://test-utils.vuejs.org/)
*   **Config file:** `vitest.config.ts` (separate from `vite.config.ts`)
*   **Test location:** Co-locate tests next to source — `src/components/ui/TVButton.test.ts`, `src/views/DashboardView.test.ts`
*   **Run command:** `npm run test` (watch mode), `npm run test:run` (CI single-pass)

### What Must Be Tested

| Layer | What to test |
|-------|-------------|
| UI Components (`src/components/ui`) | Renders correctly, prop variants change output, slots render, emits fire, aria attributes present |
| Composables (`src/composables`) | Pure logic — input/output, edge cases |
| Pinia Stores (`src/stores`) | Initial state, actions mutate state correctly, getters return correct values |
| Router guards | Unauthenticated redirect, role-based redirect |
| Views (key paths) | Renders without error with mock store data; critical user flows (submit form, click CTA) |

### Rules
*   **One test file per source file** — `TVButton.vue` → `TVButton.test.ts`.
*   **Test behavior, not implementation** — assert what the user sees and what events fire, not internal variables.
*   **No `any` in tests** — type all mocks and wrappers.
*   **Tests must pass before a feature is considered "done"** — failing tests block progression to the next phase prompt.
*   **Do not delete tests** — if a component's API changes, update the test; never remove coverage.
*   **Snapshot tests are banned** — they are brittle and mask real regressions. Use explicit assertions instead.
*   **Mock external dependencies** (API calls, `localStorage`, timers) — keep tests fast and deterministic.

### Naming Convention
```
describe('TVButton', () => {
  it('renders the default slot as label', ...)
  it('applies the correct variant class', ...)
  it('emits native click when not disabled', ...)
  it('shows spinner and disables button when loading', ...)
  it('sets aria-busy when loading', ...)
})
```

---

## 6. Intuitive UX Patterns (Required)

These patterns must be applied consistently across all views to keep the experience predictable and learnable.

### Navigation
*   **Desktop:** Persistent left sidebar (260px wide) with grouped nav items. Active item has a solid primary background pill. Sidebar can collapse to icon-only (68px) with a toggle button.
*   **Mobile:** Bottom tab bar with up to 5 primary nav items (icon + short label). Secondary items live in a "More" sheet or hamburger drawer.
*   **Breadcrumbs:** Show on detail pages (`Dashboard / Schedule / Lesson #42`) so users always know where they are.
*   **Back navigation:** Every detail/inner page must have a visible back button — do not rely solely on browser back.

### Empty States
*   Every list, table, or feed must have a meaningful empty state: an illustration (SVG), a heading explaining why it's empty, and a primary CTA where applicable (e.g., "No lessons yet — Book your first lesson").
*   Never show a blank white area.

### Loading States
*   Use `TVSkeleton` shimmer placeholders that match the shape of the content being loaded (e.g., a skeleton lesson card, not a generic spinner).
*   Show skeletons immediately — don't delay them.

### Confirmation Patterns
*   Destructive actions (cancel lesson, delete material) must trigger a confirmation modal or inline confirmation — never execute immediately on first click.
*   Non-destructive saves should use optimistic UI where possible (update the UI first, revert on error).

### Data Display
*   **Stat cards** on dashboards: large number, label, trend indicator (↑ / ↓), icon. Used for credits, completed lessons, earnings, ratings.
*   **Lesson cards:** teacher/student avatar, subject badge, time (formatted to user's timezone), duration, status badge, and a single primary action button.
*   **Tables:** On mobile, collapse to card-list view. Avoid horizontal scroll tables on small screens.
*   **Dates & Times:** Always display in the authenticated user's local timezone. Show relative time ("in 2 hours", "yesterday") where space is limited, and absolute time on hover/expand.

### Feedback & Notifications
*   Every form submission, booking, cancellation, or status change must result in a **toast notification** (success or error).
*   In-progress async actions must disable the triggering button and show the loading spinner (built into `TVButton`).
*   Never leave the user wondering if their action registered.
