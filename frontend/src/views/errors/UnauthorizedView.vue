<template>
  <div class="unauth">
    <div class="unauth__card">
      <!-- Icon -->
      <div class="unauth__icon" aria-hidden="true">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
          <circle cx="20" cy="20" r="19" stroke="var(--tv-danger)" stroke-width="2"/>
          <path d="M20 12v10M20 25h.01" stroke="var(--tv-danger)" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>

      <div class="unauth__body">
        <p class="unauth__code">403</p>
        <h1 class="unauth__title">Access denied</h1>
        <p class="unauth__text">
          You don't have permission to view this page.
          This area is restricted to specific roles.
        </p>
      </div>

      <div class="unauth__actions">
        <TVButton variant="primary" size="lg" @click="router.push(dashboardRoute)">
          Go to my dashboard
        </TVButton>
        <TVButton variant="ghost" size="md" @click="router.back()">
          Go back
        </TVButton>
      </div>

      <p class="unauth__help">
        Need access?
        <a href="mailto:support@tutorvio.com" class="unauth__help-link">Contact support</a>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import TVButton from '@/components/ui/TVButton.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const dashboardRoute = computed(() =>
  auth.isAuthenticated ? auth.getDashboardRoute() : '/login',
)
</script>

<style scoped>
.unauth {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100dvh - var(--tv-header-height));
  padding: var(--tv-space-8) var(--tv-space-6);
}

.unauth__card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--tv-space-6);
  max-width: 440px;
  width: 100%;
}

.unauth__icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--tv-danger-soft);
  display: flex;
  align-items: center;
  justify-content: center;
}

.unauth__body {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.unauth__code {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-bold);
  color: var(--tv-danger-fg);
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.unauth__title {
  font-size: var(--tv-text-3xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
}

.unauth__text {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
  line-height: var(--tv-leading-relaxed);
}

.unauth__actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--tv-space-2);
  width: 100%;
}

.unauth__actions .tv-btn { min-width: 220px; }

.unauth__help {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
}

.unauth__help-link {
  color: var(--tv-primary);
  font-weight: var(--tv-font-medium);
}

.unauth__help-link:hover { color: var(--tv-primary-hover); }
</style>
