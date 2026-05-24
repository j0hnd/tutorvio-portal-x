<template>
  <section class="login" aria-labelledby="login-heading">
    <header class="login__header">
      <h2 id="login-heading" class="login__title">Welcome back</h2>
      <p class="login__subtitle">Sign in to your Tutorvio account</p>
    </header>

    <!-- Error banner -->
    <Transition name="banner">
      <div v-if="errorMessage" class="login__error" role="alert" aria-live="assertive">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/>
          <path d="M8 5v3.5M8 10.5h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <span>{{ errorMessage }}</span>
      </div>
    </Transition>

    <form class="login__form" novalidate @submit.prevent="handleSubmit">
      <!-- Email -->
      <div class="login__field">
        <label class="login__label" for="login-email">Email address</label>
        <TVInput
          id="login-email"
          v-model="form.email"
          type="email"
          placeholder="you@tutorvio.com"
          autocomplete="email"
          :error="fieldErrors.email"
          :disabled="loading"
          required
        />
        <span v-if="fieldErrors.email" class="login__field-error" role="alert">
          {{ fieldErrors.email }}
        </span>
      </div>

      <!-- Password -->
      <div class="login__field">
        <div class="login__label-row">
          <label class="login__label" for="login-password">Password</label>
          <router-link to="/forgot-password" class="login__forgot-link">
            Forgot password?
          </router-link>
        </div>
        <div class="login__password-wrap">
          <TVInput
            id="login-password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="••••••••"
            autocomplete="current-password"
            :error="fieldErrors.password"
            :disabled="loading"
            required
          />
          <button
            type="button"
            class="login__password-toggle"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.4"/>
              <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/>
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M2 2l12 12M6.5 6.6A2 2 0 0 0 9.4 9.5M4.2 4.3C2.6 5.3 1.5 6.8 1 8c1.2 3.1 4 5 7 5 1.3 0 2.6-.4 3.7-1.1M6.9 3.1C7.3 3 7.6 3 8 3c3 0 5.8 2 7 5-.4 1-1 1.9-1.7 2.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
        <span v-if="fieldErrors.password" class="login__field-error" role="alert">
          {{ fieldErrors.password }}
        </span>
      </div>

      <!-- Remember me -->
      <label class="login__remember">
        <input
          v-model="form.rememberMe"
          type="checkbox"
          class="login__checkbox"
          :disabled="loading"
        />
        <span class="login__remember-label">Remember me for 30 days</span>
      </label>

      <!-- Submit -->
      <TVButton
        type="submit"
        variant="primary"
        size="lg"
        :loading="loading"
        class="login__submit"
      >
        Sign in
      </TVButton>
    </form>

    <!-- Demo credentials hint -->
    <details class="login__demo">
      <summary class="login__demo-toggle">Demo credentials</summary>
      <ul class="login__demo-list" role="list">
        <li v-for="cred in demoCreds" :key="cred.role" class="login__demo-item">
          <button
            type="button"
            class="login__demo-btn"
            :aria-label="`Sign in as ${cred.role}`"
            @click="fillDemo(cred)"
          >
            <span class="login__demo-role">{{ cred.role }}</span>
            <span class="login__demo-email">{{ cred.email }}</span>
          </button>
        </li>
      </ul>
    </details>
  </section>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import TVButton from '@/components/ui/TVButton.vue'
import TVInput from '@/components/ui/TVInput.vue'
import { useAuthStore } from '@/stores/auth'
import type { AuthError } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const loading = ref(false)
const showPassword = ref(false)
const errorMessage = ref('')

const form = reactive({
  email: '',
  password: '',
  rememberMe: false,
})

const fieldErrors = reactive({
  email: '',
  password: '',
})

const demoCreds = [
  { role: 'Student',   email: 'student@tutorvio.com',  password: 'Tutorvio@2026' },
  { role: 'Teacher',   email: 'teacher@tutorvio.com',  password: 'Tutorvio@2026' },
  { role: 'Teacher 2', email: 'teacher2@tutorvio.com', password: 'Tutorvio@2026' },
  { role: 'Teacher 3', email: 'teacher3@tutorvio.com', password: 'Tutorvio@2026' },
  { role: 'Admin',     email: 'admin@tutorvio.com',    password: 'Tutorvio@2026' },
  { role: 'Staff',     email: 'staff@tutorvio.com',    password: 'Tutorvio@2026' },
]

function fillDemo(cred: { email: string; password: string }): void {
  form.email = cred.email
  form.password = cred.password
  errorMessage.value = ''
  fieldErrors.email = ''
  fieldErrors.password = ''
}

function validate(): boolean {
  fieldErrors.email = ''
  fieldErrors.password = ''

  let valid = true

  if (!form.email.trim()) {
    fieldErrors.email = 'Email is required.'
    valid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    fieldErrors.email = 'Please enter a valid email address.'
    valid = false
  }

  if (!form.password) {
    fieldErrors.password = 'Password is required.'
    valid = false
  }

  return valid
}

async function handleSubmit(): Promise<void> {
  errorMessage.value = ''
  if (!validate()) return

  loading.value = true
  try {
    await auth.login({ email: form.email, password: form.password })
    const redirect = typeof route.query.redirect === 'string'
      ? route.query.redirect
      : auth.getDashboardRoute()
    await router.push(redirect)
  } catch (err) {
    const e = err as AuthError
    errorMessage.value = e.message ?? 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login {
  width: 100%;
  max-width: 420px;
}

.login__header {
  margin-bottom: var(--tv-space-8);
}

.login__title {
  font-size: var(--tv-text-3xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin-bottom: var(--tv-space-1);
}

.login__subtitle {
  font-size: var(--tv-text-base);
  color: var(--tv-text-secondary);
}

/* Error banner */
.login__error {
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
  line-height: var(--tv-leading-snug);
}

.login__error svg { flex-shrink: 0; margin-top: 1px; }

/* Form */
.login__form {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.login__field {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.login__label {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.login__label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--tv-space-2);
}

.login__forgot-link {
  font-size: var(--tv-text-sm);
  color: var(--tv-primary);
  font-weight: var(--tv-font-medium);
  white-space: nowrap;
}

.login__forgot-link:hover { color: var(--tv-primary-hover); }

.login__password-wrap {
  position: relative;
}

.login__password-toggle {
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

.login__password-toggle:hover {
  color: var(--tv-text);
  background: var(--tv-bg-soft);
}

.login__field-error {
  font-size: var(--tv-text-xs);
  color: var(--tv-danger-fg);
  font-weight: var(--tv-font-medium);
}

/* Remember me */
.login__remember {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  cursor: pointer;
  margin-top: calc(-1 * var(--tv-space-1));
}

.login__checkbox {
  width: 16px;
  height: 16px;
  accent-color: var(--tv-primary);
  flex-shrink: 0;
  cursor: pointer;
}

.login__remember-label {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
  user-select: none;
}

.login__submit {
  width: 100%;
  margin-top: var(--tv-space-1);
}

/* Demo credentials */
.login__demo {
  margin-top: var(--tv-space-6);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  overflow: hidden;
}

.login__demo-toggle {
  padding: var(--tv-space-3) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
  cursor: pointer;
  list-style: none;
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  user-select: none;
  background: var(--tv-bg-soft);
}

.login__demo-toggle:hover { color: var(--tv-text); }

.login__demo-list {
  list-style: none;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1px;
  background: var(--tv-border);
  border-top: 1px solid var(--tv-border);
}

.login__demo-item { background: var(--tv-bg-card); }

.login__demo-btn {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: var(--tv-space-3) var(--tv-space-4);
  transition: background-color var(--tv-transition-fast);
  text-align: left;
}

.login__demo-btn:hover { background: var(--tv-bg-soft); }

.login__demo-role {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-primary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.login__demo-email {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

/* Transition */
.banner-enter-active,
.banner-leave-active { transition: opacity var(--tv-transition), transform var(--tv-transition); }
.banner-enter-from, .banner-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
