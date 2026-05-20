<template>
  <div class="auth-shell">
    <!-- Left branding panel (hidden on mobile) -->
    <aside class="auth-panel" aria-hidden="true">
      <div class="auth-panel__inner">
        <!-- Logo mark -->
        <div class="auth-panel__logo">
          <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
            <rect width="40" height="40" rx="10" fill="rgba(255,255,255,0.18)"/>
            <path d="M12 12h16M20 12v16" stroke="white" stroke-width="2.8" stroke-linecap="round"/>
          </svg>
          <span class="auth-panel__wordmark">Tutorvio</span>
        </div>

        <!-- Tagline -->
        <div class="auth-panel__hero">
          <h1 class="auth-panel__headline">Empower learning,<br>one session at a time.</h1>
          <p class="auth-panel__sub">
            A premium learning platform connecting students with expert teachers — built for growth.
          </p>
        </div>

        <!-- Feature highlights -->
        <ul class="auth-panel__features" role="list">
          <li v-for="f in features" :key="f.label" class="auth-panel__feature">
            <span class="auth-panel__feature-dot" aria-hidden="true" />
            {{ f.label }}
          </li>
        </ul>

        <!-- Decorative blobs -->
        <div class="auth-panel__blob auth-panel__blob--1" aria-hidden="true" />
        <div class="auth-panel__blob auth-panel__blob--2" aria-hidden="true" />
      </div>
    </aside>

    <!-- Right form panel -->
    <main class="auth-form-panel" id="main-content">
      <!-- Mobile logo (only visible on mobile) -->
      <div class="auth-form-panel__mobile-logo" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
          <rect width="28" height="28" rx="7" fill="var(--tv-primary)"/>
          <path d="M8 8h12M14 8v12" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
        <span class="auth-form-panel__mobile-wordmark">Tutorvio</span>
      </div>

      <div class="auth-form-panel__content">
        <router-view v-slot="{ Component }">
          <Transition name="page" mode="out-in">
            <component :is="Component" />
          </Transition>
        </router-view>
      </div>

      <footer class="auth-form-panel__footer">
        <p>&copy; {{ year }} Tutorvio. All rights reserved.</p>
      </footer>
    </main>
  </div>
</template>

<script setup lang="ts">
const year = new Date().getFullYear()

const features = [
  { label: 'Smart scheduling with timezone sync' },
  { label: 'Real-time lesson tracking & attendance' },
  { label: 'Materials library & homework management' },
  { label: 'Transparent billing & teacher payroll' },
]
</script>

<style scoped>
.auth-shell {
  display: flex;
  min-height: 100dvh;
  background: var(--tv-bg);
}

/* ── Left branding panel ── */
.auth-panel {
  display: none;
  flex-direction: column;
  background: linear-gradient(
    145deg,
    hsl(var(--tv-primary-h), var(--tv-primary-s), 30%) 0%,
    hsl(var(--tv-primary-h), var(--tv-primary-s), 20%) 100%
  );
  width: 420px;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}

.auth-panel__inner {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: var(--tv-space-10) var(--tv-space-10);
}

.auth-panel__logo {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  margin-bottom: auto;
}

.auth-panel__wordmark {
  font-size: var(--tv-text-xl);
  font-weight: var(--tv-font-bold);
  color: white;
  letter-spacing: -0.02em;
}

.auth-panel__hero {
  margin-bottom: var(--tv-space-10);
}

.auth-panel__headline {
  font-size: 2rem;
  font-weight: var(--tv-font-bold);
  color: white;
  line-height: 1.2;
  margin-bottom: var(--tv-space-4);
  letter-spacing: -0.025em;
}

.auth-panel__sub {
  font-size: var(--tv-text-base);
  color: hsla(0, 0%, 100%, 0.72);
  line-height: var(--tv-leading-relaxed);
}

.auth-panel__features {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
  margin-bottom: var(--tv-space-12);
}

.auth-panel__feature {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  font-size: var(--tv-text-sm);
  color: hsla(0, 0%, 100%, 0.82);
  font-weight: var(--tv-font-medium);
}

.auth-panel__feature-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: hsla(0, 0%, 100%, 0.6);
  flex-shrink: 0;
}

/* Decorative blobs */
.auth-panel__blob {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}

.auth-panel__blob--1 {
  width: 300px;
  height: 300px;
  background: hsla(0, 0%, 100%, 0.06);
  top: -80px;
  right: -80px;
}

.auth-panel__blob--2 {
  width: 200px;
  height: 200px;
  background: hsla(0, 0%, 100%, 0.05);
  bottom: 60px;
  left: -60px;
}

/* ── Right form panel ── */
.auth-form-panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.auth-form-panel__mobile-logo {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-5) var(--tv-space-6);
  border-bottom: 1px solid var(--tv-border);
}

.auth-form-panel__mobile-wordmark {
  font-size: var(--tv-text-lg);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.02em;
}

.auth-form-panel__content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--tv-space-8) var(--tv-space-6);
}

.auth-form-panel__footer {
  padding: var(--tv-space-5) var(--tv-space-6);
  text-align: center;
}

.auth-form-panel__footer p {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

/* Desktop: show left panel */
@media (min-width: 768px) {
  .auth-panel { display: flex; }
  .auth-form-panel__mobile-logo { display: none; }
}

@media (min-width: 1024px) {
  .auth-panel { width: 480px; }
}
</style>
