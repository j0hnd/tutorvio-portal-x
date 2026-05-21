<template>
  <section class="activate" aria-labelledby="activate-heading">

    <!-- Invalid / missing token -->
    <div v-if="tokenState === 'missing'" class="activate__state-wrap" role="alert">
      <div class="activate__state-icon activate__state-icon--danger" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="15" stroke="var(--tv-danger)" stroke-width="2"/>
          <path d="M16 10v7M16 20h.01" stroke="var(--tv-danger)" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="activate__state-title">Invalid invitation link</h2>
      <p class="activate__state-text">
        This link is missing or malformed. Please contact your administrator to receive a new invitation.
      </p>
    </div>

    <!-- Expired token -->
    <div v-else-if="tokenState === 'expired'" class="activate__state-wrap" role="alert">
      <div class="activate__state-icon activate__state-icon--warning" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="15" stroke="var(--tv-warning)" stroke-width="2"/>
          <path d="M16 9v7l4 4" stroke="var(--tv-warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="activate__state-title">Invitation expired</h2>
      <p class="activate__state-text">
        This invitation link has expired. Please contact your administrator to receive a new one.
      </p>
    </div>

    <!-- Success state -->
    <div v-else-if="tokenState === 'done'" class="activate__state-wrap" role="status">
      <div class="activate__state-icon activate__state-icon--success" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="15" stroke="var(--tv-success)" stroke-width="2"/>
          <path d="M10 16l4 4 8-8" stroke="var(--tv-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="activate__state-title">Account activated!</h2>
      <p class="activate__state-text">
        Welcome to Tutorvio! Your account is ready. Sign in to get started.
      </p>
      <router-link to="/login">
        <TVButton variant="primary" size="lg" class="activate__state-cta">Go to sign in</TVButton>
      </router-link>
    </div>

    <!-- Activation form -->
    <div v-else class="activate__form-wrap">
      <header class="activate__header">
        <div class="activate__header-icon" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect width="24" height="24" rx="6" fill="var(--tv-primary-soft)"/>
            <path d="M7 7h10M12 7v10" stroke="var(--tv-primary)" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <h2 id="activate-heading" class="activate__title">You're invited!</h2>
        <p class="activate__subtitle">
          Set up your Tutorvio account to get started.
        </p>
      </header>

      <!-- Error banner -->
      <Transition name="banner">
        <div v-if="errorMessage" class="activate__error" role="alert">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/>
            <path d="M8 5v3.5M8 10.5h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <span>{{ errorMessage }}</span>
        </div>
      </Transition>

      <form class="activate__form" novalidate @submit.prevent="handleSubmit">
        <!-- Email (read-only, pre-filled from URL) -->
        <div v-if="emailFromUrl" class="activate__field">
          <label class="activate__label" for="activate-email">Email address</label>
          <TVInput
            id="activate-email"
            :model-value="emailFromUrl"
            type="email"
            readonly
            disabled
            aria-readonly="true"
          />
          <span class="activate__hint">This email is tied to your invitation and cannot be changed.</span>
        </div>

        <!-- Name row -->
        <div class="activate__name-row">
          <div class="activate__field">
            <label class="activate__label" for="activate-first">First name</label>
            <TVInput
              id="activate-first"
              v-model="form.firstName"
              type="text"
              placeholder="Emma"
              autocomplete="given-name"
              :error="fieldErrors.firstName"
              :disabled="loading"
              required
            />
            <span v-if="fieldErrors.firstName" class="activate__field-error" role="alert">
              {{ fieldErrors.firstName }}
            </span>
          </div>
          <div class="activate__field">
            <label class="activate__label" for="activate-last">Last name</label>
            <TVInput
              id="activate-last"
              v-model="form.lastName"
              type="text"
              placeholder="Santos"
              autocomplete="family-name"
              :error="fieldErrors.lastName"
              :disabled="loading"
              required
            />
            <span v-if="fieldErrors.lastName" class="activate__field-error" role="alert">
              {{ fieldErrors.lastName }}
            </span>
          </div>
        </div>

        <!-- Password -->
        <div class="activate__field">
          <label class="activate__label" for="activate-password">Create a password</label>
          <div class="activate__password-wrap">
            <TVInput
              id="activate-password"
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
              class="activate__eye"
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
          <span v-if="fieldErrors.password" class="activate__field-error" role="alert">
            {{ fieldErrors.password }}
          </span>
        </div>

        <!-- Confirm -->
        <div class="activate__field">
          <label class="activate__label" for="activate-confirm">Confirm password</label>
          <TVInput
            id="activate-confirm"
            v-model="form.confirm"
            :type="showPass ? 'text' : 'password'"
            placeholder="Repeat your password"
            autocomplete="new-password"
            :error="fieldErrors.confirm"
            :disabled="loading"
            required
          />
          <span v-if="fieldErrors.confirm" class="activate__field-error" role="alert">
            {{ fieldErrors.confirm }}
          </span>
        </div>

        <TVButton
          type="submit"
          variant="primary"
          size="lg"
          :loading="loading"
          class="activate__submit"
        >
          Activate my account
        </TVButton>
      </form>
    </div>

  </section>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
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

const activationToken = typeof route.query.token === 'string' ? route.query.token : ''
const emailFromUrl = typeof route.query.email === 'string' ? decodeURIComponent(route.query.email) : ''

const form = reactive({ firstName: '', lastName: '', password: '', confirm: '' })
const fieldErrors = reactive({ firstName: '', lastName: '', password: '', confirm: '' })

onMounted(() => {
  if (!activationToken || activationToken.length < 6) tokenState.value = 'missing'
})

function validate(): boolean {
  Object.keys(fieldErrors).forEach(k => ((fieldErrors as Record<string, string>)[k] = ''))
  let valid = true

  if (!form.firstName.trim()) { fieldErrors.firstName = 'First name is required.'; valid = false }
  if (!form.lastName.trim()) { fieldErrors.lastName = 'Last name is required.'; valid = false }

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
    await auth.activateAccount(activationToken, {
      firstName: form.firstName.trim(),
      lastName: form.lastName.trim(),
      password: form.password,
    })
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
.activate {
  width: 100%;
  max-width: 440px;
}

/* States */
.activate__state-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--tv-space-4);
}

.activate__state-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.activate__state-icon--success { background: var(--tv-success-soft); }
.activate__state-icon--danger  { background: var(--tv-danger-soft); }
.activate__state-icon--warning { background: var(--tv-warning-soft); }

.activate__state-title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.02em;
}

.activate__state-text {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
  line-height: var(--tv-leading-relaxed);
  max-width: 340px;
}

.activate__state-cta { min-width: 200px; margin-top: var(--tv-space-2); }

/* Form */
.activate__header {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
  margin-bottom: var(--tv-space-8);
}

.activate__header-icon {
  display: flex;
  width: 44px;
  height: 44px;
}

.activate__title {
  font-size: var(--tv-text-3xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
}

.activate__subtitle {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
}

.activate__error {
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

.activate__error svg { flex-shrink: 0; margin-top: 1px; }

.activate__form {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.activate__name-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-4);
}

.activate__field {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.activate__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.activate__hint {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

.activate__password-wrap { position: relative; }

.activate__eye {
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

.activate__eye:hover { color: var(--tv-text); background: var(--tv-bg-soft); }

.activate__field-error {
  font-size: var(--tv-text-xs);
  color: var(--tv-danger-fg);
  font-weight: var(--tv-font-medium);
}

.activate__submit { width: 100%; }

/* Transitions */
.banner-enter-active,
.banner-leave-active { transition: opacity var(--tv-transition), transform var(--tv-transition); }
.banner-enter-from, .banner-leave-to { opacity: 0; transform: translateY(-6px); }

@media (max-width: 480px) {
  .activate__name-row { grid-template-columns: 1fr; }
}
</style>
