<template>
  <section class="forgot" aria-labelledby="forgot-heading">

    <!-- Success state -->
    <Transition name="page" mode="out-in">
      <div v-if="sent" class="forgot__success" role="status">
        <div class="forgot__success-icon" aria-hidden="true">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
            <circle cx="16" cy="16" r="15" stroke="var(--tv-success)" stroke-width="2"/>
            <path d="M10 16l4 4 8-8" stroke="var(--tv-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h2 class="forgot__success-title">Check your email</h2>
        <p class="forgot__success-text">
          If <strong>{{ sentEmail }}</strong> is registered, you'll receive a password reset link shortly.
          Check your spam folder if you don't see it.
        </p>
        <div class="forgot__success-actions">
          <TVButton variant="primary" size="lg" class="forgot__back-btn" @click="reset">
            Send again
          </TVButton>
          <router-link to="/login" class="forgot__login-link">
            Back to sign in
          </router-link>
        </div>
      </div>

      <!-- Form state -->
      <div v-else class="forgot__form-wrap">
        <header class="forgot__header">
          <router-link to="/login" class="forgot__back" aria-label="Back to sign in">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back
          </router-link>
          <h2 id="forgot-heading" class="forgot__title">Forgot password?</h2>
          <p class="forgot__subtitle">
            Enter your email and we'll send you instructions to reset your password.
          </p>
        </header>

        <!-- Error banner -->
        <Transition name="banner">
          <div v-if="errorMessage" class="forgot__error" role="alert">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/>
              <path d="M8 5v3.5M8 10.5h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span>{{ errorMessage }}</span>
          </div>
        </Transition>

        <form class="forgot__form" novalidate @submit.prevent="handleSubmit">
          <div class="forgot__field">
            <label class="forgot__label" for="forgot-email">Email address</label>
            <TVInput
              id="forgot-email"
              v-model="email"
              type="email"
              placeholder="you@tutorvio.com"
              autocomplete="email"
              :error="fieldError"
              :disabled="loading"
              required
            />
            <span v-if="fieldError" class="forgot__field-error" role="alert">
              {{ fieldError }}
            </span>
          </div>

          <TVButton
            type="submit"
            variant="primary"
            size="lg"
            :loading="loading"
            class="forgot__submit"
          >
            Send reset instructions
          </TVButton>
        </form>
      </div>
    </Transition>

  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import TVButton from '@/components/ui/TVButton.vue'
import TVInput from '@/components/ui/TVInput.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const email = ref('')
const fieldError = ref('')
const errorMessage = ref('')
const loading = ref(false)
const sent = ref(false)
const sentEmail = ref('')

function validate(): boolean {
  fieldError.value = ''
  if (!email.value.trim()) {
    fieldError.value = 'Email is required.'
    return false
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    fieldError.value = 'Please enter a valid email address.'
    return false
  }
  return true
}

async function handleSubmit(): Promise<void> {
  errorMessage.value = ''
  if (!validate()) return

  loading.value = true
  try {
    await auth.requestPasswordReset(email.value.trim())
    sentEmail.value = email.value.trim()
    sent.value = true
  } catch {
    errorMessage.value = 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}

function reset(): void {
  sent.value = false
  email.value = ''
  sentEmail.value = ''
}
</script>

<style scoped>
.forgot {
  width: 100%;
  max-width: 420px;
}

/* Back link */
.forgot__back {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
  margin-bottom: var(--tv-space-6);
  transition: color var(--tv-transition-fast);
}

.forgot__back:hover { color: var(--tv-text); }

.forgot__header { margin-bottom: var(--tv-space-8); }

.forgot__title {
  font-size: var(--tv-text-3xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin-bottom: var(--tv-space-2);
}

.forgot__subtitle {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
  line-height: var(--tv-leading-relaxed);
}

/* Error */
.forgot__error {
  display: flex;
  align-items: flex-start;
  gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-4);
  background: var(--tv-danger-soft);
  border: 1px solid var(--tv-danger-border);
  border-radius: var(--tv-radius);
  color: var(--tv-danger-fg);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  margin-bottom: var(--tv-space-5);
}

.forgot__error svg { flex-shrink: 0; margin-top: 1px; }

/* Form */
.forgot__form {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.forgot__field {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.forgot__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.forgot__field-error {
  font-size: var(--tv-text-xs);
  color: var(--tv-danger-fg);
  font-weight: var(--tv-font-medium);
}

.forgot__submit { width: 100%; }

/* Success state */
.forgot__success {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--tv-space-4);
  width: 100%;
  max-width: 420px;
}

.forgot__success-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--tv-success-soft);
  display: flex;
  align-items: center;
  justify-content: center;
}

.forgot__success-title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.02em;
}

.forgot__success-text {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
  line-height: var(--tv-leading-relaxed);
  max-width: 340px;
}

.forgot__success-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--tv-space-3);
  width: 100%;
  margin-top: var(--tv-space-2);
}

.forgot__back-btn { width: 100%; max-width: 280px; }

.forgot__login-link {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
}

.forgot__login-link:hover { color: var(--tv-primary); }

/* Transitions */
.banner-enter-active,
.banner-leave-active { transition: opacity var(--tv-transition), transform var(--tv-transition); }
.banner-enter-from, .banner-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
