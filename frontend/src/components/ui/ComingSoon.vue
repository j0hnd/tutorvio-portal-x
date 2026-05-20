<template>
  <div class="coming-soon" role="status">
    <div class="coming-soon__icon" aria-hidden="true">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <circle cx="20" cy="20" r="18" stroke="var(--tv-primary-muted)" stroke-width="2"/>
        <path d="M20 11v9l5 5" stroke="var(--tv-primary)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="20" cy="20" r="2" fill="var(--tv-primary)"/>
      </svg>
    </div>
    <p class="coming-soon__phase">Phase {{ phase }}</p>
    <h1 class="coming-soon__title">{{ title }}</h1>
    <p class="coming-soon__desc">This section is coming in an upcoming phase.</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  phase: number | string
}>()
</script>

<style scoped>
.coming-soon {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: var(--tv-space-3);
  min-height: calc(100dvh - var(--tv-header-height) - 2px);
  padding: var(--tv-space-8) var(--tv-space-6);
}

.coming-soon__icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--tv-primary-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: var(--tv-space-2);
}

.coming-soon__phase {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--tv-primary);
}

.coming-soon__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.02em;
}

.coming-soon__desc {
  font-size: var(--tv-text-base);
  color: var(--tv-text-muted);
  max-width: 320px;
  line-height: var(--tv-leading-relaxed);
}
</style>
