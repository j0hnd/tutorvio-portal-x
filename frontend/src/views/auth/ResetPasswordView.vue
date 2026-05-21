<template>
  <section class="reset" aria-labelledby="reset-heading">

    <!-- Invalid / missing token — shown immediately -->
    <div v-if="tokenState === 'missing'" class="reset__error-state" role="alert">
      <div class="reset__error-icon" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="15" stroke="var(--tv-danger)" stroke-width="2"/>
          <path d="M16 10v7M16 20h.01" stroke="var(--tv-danger)" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="reset__error-title">Invalid reset link</h2>
      <p class="reset__error-text">
        This password reset link is missing or malformed.
        Please request a new one.
      </p>
      <router-link to="/forgot-password" class="reset__error-cta">
        <TVButton variant="primary" size="lg">Request new link</TVButton>
      </router-link>
    </div>

    <!-- Expired token — shown after submit failure -->
    <div v-else-if="tokenState === 'expired'" class="reset__error-state" role="alert">
      <div class="reset__error-icon" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="15" stroke="var(--tv-warning)" stroke-width="2"/>
          <path d="M16 9v7l4 4" stroke="var(--tv-warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="reset__error-title">Link expired</h2>
      <p class="reset__error-text">
        This reset link has expired. Reset links are valid for 1 hour.
        Please request a new one.
      </p>
      <router-link to="/forgot-password" class="reset__error-cta">
        <TVButton variant="primary" size="lg">Request new link</TVButton>
      </router-link>
    </div>

    <!-- Success state -->
    <div v-else-if="tokenState === 'done'" class="reset__success" role="status">
      <div class="reset__success-icon" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="15" stroke="var(--tv-success)" stroke-width="2"/>
          <path d="M10 16l4 4 8-8" stroke="var(--tv-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="reset__success-title">Password updated</h2>
      <p class="reset__success-text">
        Your password has been reset successfully. You can now sign in with your new password.
      </p>
      <router-link to="/login" class="reset__success-cta">
        <TVButton variant="primary" size="lg" class="reset__success-btn">Go to sign in</TVButton>
      </router-link>
    </div>

    <!-- Form state -->
    <div v-else class="reset__form-wrap">
      <header class="reset__header">
        <h2 id="reset-heading" class="reset__title">Reset your password</h2>
        <p class="reset__subtitle">Choose a strong new password for your account.</p>
      </header>

      <!-- Error banner -->
      <Transition name="banner">
        <div v-if="errorMessage" class="reset__error-banner" role="alert">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/>
            <path d="M8 5v3.5M8 10.5h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <span>{{ errorMessage }}</span>
        </div>
      </Transition>

      <form class="reset__form" novalidate @submit.prevent="handleSubmit">
        <!-- New password -->
        <div class="reset__field">
          <label class="reset__label" for="reset-password">New password</label>
          <div class="reset__password-wrap">
            <TVInput
              id="reset-password"
              v-model="form.password"
              :type="showPass ? 'text' : 'password'"
              placeholder="Min. 8 characters"
              autocomplete="new-password"
              :error="fieldErrors.password"
              :disabled="loading"
              required
            />
            <button
              type="button"
              class="reset__eye"
              :aria-label="showPass ? 'Hide password' : 'Show password'"
              :aria-pressed="showPass"
              @click="showPass = !showPass"
            >
              <svg v-if="!showPass" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.4"/>
                <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/>
              </svg>
              <svg v-else width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M2 2l12 12M6.5 6.6A2 2 0 0 0 9.4 9.5M4.2 4.3C2.6 5.3 1.5 6.8 1 8c1.2 3.1 4 5 7 5 1.3 0 2.6-.4 3.7-1.1M6.9 3.1C7.3 3 7.6 3 8 3c3 0 5.8 2 7 5-.4 1-1 1.9-1.7 2.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
          <!-- Strength bar -->
          <div class="reset__strength" aria-label="Password strength" aria-live="polite">
            <div
              v-for="n in 4"
              :key="n"
              :class="['reset__strength-bar', strength >= n && `reset__strength-bar--${strengthLabel}`]"
            />
            <span v-if="form.password" class="reset__strength-label">{{ strengthLabel }}</span>
          </div>
          <span v-if="fieldErrors.password" class="reset__field-error" role="alert">
            {{ fieldErrors.password }}
          </span>
        </div>

        <!-- Confirm password -->
        <div class="reset__field">
          <label class="reset__label" for="reset-confirm">Confirm password</label>
          <TVInput
            id="reset-confirm"
            v-model="form.confirm"
            :type="showPass ? 'text' : 'password'"
            placeholder="Repeat your password"
            autocomplete="new-password"
            :error="fieldErrors.confirm"
            :disabled="loading"
            required
          />
          <span v-if="fieldErrors.confirm" class="reset__field-error" role="alert">
            {{ fieldErrors.confirm }}
          </span>
        </div>

        <TVButton type="submit" variant="primary" size="lg" :loading="loading" class="reset__submit">
          Set new password
        </TVButton>
      </form>
    </div>

  </section>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import TVButton from '@/components/ui/TVButton.vue'
import TVInput from '@/components/ui/TVInput.vue'
import { useAuthStore } from '@/stores/auth'
import type { AuthError } from '@/stores/auth'

type TokenState = 'valid' | 'missing' | 'expired' | 'done'

const route = useRoute()
const auth = useAuthStore()

const loading = ref(false)
const showPass = ref(false)
const errorMessage = ref('')
const tokenState = ref<TokenState>('valid')

const form = reactive({ password: '', confirm: '' })
const fieldErrors = reactive({ password: '', confirm: '' })

const resetToken = typeof route.query.token === 'string' ? route.query.token : ''

onMounted(() => {
  if (!resetToken || resetToken.length < 6) tokenState.value = 'missing'
})

/* Password strength 1–4 */
const strength = computed((): number => {
  const p = form.password
  if (!p) return 0
  let s = 0
  if (p.length >= 8) s++
  if (/[A-Z]/.test(p)) s++
  if (/[0-9]/.test(p)) s++
  if (/[^A-Za-z0-9]/.test(p)) s++
  return s
})

const strengthLabel = computed((): 'weak' | 'fair' | 'good' | 'strong' => {
  const labels: Array<'weak' | 'fair' | 'good' | 'strong'> = ['weak', 'weak', 'fair', 'good', 'strong']
  return labels[strength.value] ?? 'weak'
})

function validate(): boolean {
  fieldErrors.password = ''
  fieldErrors.confirm = ''
  let valid = true

  if (!form.password) {
    fieldErrors.password = 'Password is required.'
    valid = false
  } else if (form.password.length < 8) {
    fieldErrors.password = 'Password must be at least 8 characters.'
    valid = false
  }

  if (!form.confirm) {
    fieldErrors.confirm = 'Please confirm your password.'
    valid = false
  } else if (form.password !== form.confirm) {
    fieldErrors.confirm = 'Passwords do not match.'
    valid = false
  }

  return valid
}

async function handleSubmit(): Promise<void> {
  errorMessage.value = ''
  if (!validate()) return

  loading.value = true
  try {
    await auth.resetPassword(resetToken, form.password)
    tokenState.value = 'done'
  } catch (err) {
    const e = err as AuthError
    if (e.code === 'EXPIRED_TOKEN') {
      tokenState.value = 'expired'
    } else {
      errorMessage.value = e.message ?? 'Something went wrong. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.reset {
  width: 100%;
  max-width: 420px;
}

/* Error / success states */
.reset__error-state,
.reset__success {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--tv-space-4);
}

.reset__error-icon,
.reset__success-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.reset__error-icon { background: var(--tv-danger-soft); }
.reset__success-icon { background: var(--tv-success-soft); }

.reset__error-title,
.reset__success-title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.02em;
}

.reset__error-text,
.reset__success-text {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
  line-height: var(--tv-leading-relaxed);
  max-width: 340px;
}

.reset__error-cta,
.reset__success-cta { margin-top: var(--tv-space-2); }
.reset__success-btn { min-width: 200px; }

/* Form */
.reset__header { margin-bottom: var(--tv-space-8); }

.reset__title {
  font-size: var(--tv-text-3xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin-bottom: var(--tv-space-2);
}

.reset__subtitle {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
}

.reset__error-banner {
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

.reset__error-banner svg { flex-shrink: 0; margin-top: 1px; }

.reset__form {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.reset__field {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.reset__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.reset__password-wrap { position: relative; }

.reset__eye {
  position: absolute;
  right: var(--tv-space-3);
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  transition: color var(--tv-transition-fast), background-color var(--tv-transition-fast);
}

.reset__eye:hover { color: var(--tv-text); background: var(--tv-bg-soft); }

/* Strength bars */
.reset__strength {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
  margin-top: var(--tv-space-1);
}

.reset__strength-bar {
  flex: 1;
  height: 3px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-border);
  transition: background-color var(--tv-transition);
}

.reset__strength-bar--weak   { background: var(--tv-danger); }
.reset__strength-bar--fair   { background: var(--tv-warning); }
.reset__strength-bar--good   { background: var(--tv-info); }
.reset__strength-bar--strong { background: var(--tv-success); }

.reset__strength-label {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-muted);
  white-space: nowrap;
  text-transform: capitalize;
  margin-left: var(--tv-space-1);
}

.reset__field-error {
  font-size: var(--tv-text-xs);
  color: var(--tv-danger-fg);
  font-weight: var(--tv-font-medium);
}

.reset__submit { width: 100%; }

/* Transitions */
.banner-enter-active,
.banner-leave-active { transition: opacity var(--tv-transition), transform var(--tv-transition); }
.banner-enter-from, .banner-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
