<script setup lang="ts">
import { computed, nextTick, shallowRef } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { CheckCircleIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../Components/AuthShell.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import TwoFactorMethodOptions from '../../Components/TwoFactorMethodOptions.vue'

type Method = 'authenticator' | 'recovery' | 'email'
type PageProps = {
  flash?: { status?: string; recoveryEmail?: string | null }
  recoveryEmail?: string | null
  recoveryPending?: boolean
}

const page = usePage<PageProps>()
const method = shallowRef<Method>(page.props.recoveryPending ? 'email' : 'authenticator')
const form = useForm({ code: '', recovery_code: '', remember: false })
const emailForm = useForm({})
const emailCodeForm = useForm({ code: '' })
const errors = computed(() => ({ ...form.errors, ...emailForm.errors, ...emailCodeForm.errors }))
const recoveryEmail = computed(() => page.props.recoveryEmail || page.props.flash?.recoveryEmail || '')

function focusSummary() {
  nextTick(() => document.querySelector<HTMLElement>('[data-error-summary]')?.focus())
}

function selectMethod(selected: Method) {
  method.value = selected
  form.clearErrors()
  emailForm.clearErrors()
  emailCodeForm.clearErrors()
}

function submit() {
  if (method.value === 'email') {
    emailCodeForm.post('/two-factor-challenge/email-code/verify', { onError: focusSummary })
    return
  }

  form.post('/two-factor-challenge', { onError: focusSummary })
}

function sendEmailCode() {
  emailForm.post('/two-factor-challenge/email-code', {
    onSuccess: () => {
      emailCodeForm.reset()
      selectMethod('email')
    },
    onError: focusSummary,
  })
}
</script>

<template>
  <AuthShell
    title="Confirme sua identidade"
    compact
    brand-placement="panel"
    :show-support="false"
    :description="method === 'authenticator' ? 'Digite o código de seis dígitos do autenticador.' : method === 'recovery' ? 'Digite um código de recuperação.' : 'Digite o código enviado ao seu e-mail.'"
  >
    <div
      v-if="page.props.flash?.status === 'two-factor-recovery-sent' || page.props.recoveryPending"
      class="status-message my-4 grid gap-1 rounded-lg border border-td-green/25 bg-td-green/5 p-3 text-sm leading-5 text-td-green-ink"
      role="status"
      aria-live="polite"
    >
      <div class="flex items-start gap-3">
        <CheckCircleIcon class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
        <p>
          Enviamos um código para <strong>{{ recoveryEmail || 'o e-mail cadastrado' }}</strong>. Ele vale por 10 minutos.
        </p>
      </div>
    </div>

    <FormErrorSummary :errors="errors" />

    <form class="auth-form mt-4 grid min-w-0 grid-cols-12 gap-4 [&>*]:col-span-12" @submit.prevent="submit">
      <div v-if="method === 'authenticator'" class="field-group grid min-w-0 content-start gap-3">
        <label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="code">Código do autenticador</label>
        <input
          id="code"
          v-model="form.code"
          class="form-input w-full min-w-0 rounded-lg border border-td-border bg-transparent px-4 py-2.5 text-base leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 sm:text-sm"
          :class="{ 'form-input--error !border-td-red !ring-td-red/15': form.errors.code }"
          type="text"
          name="code"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="6"
          pattern="[0-9]{6}"
          placeholder="000000"
          required
          :aria-invalid="Boolean(form.errors.code)"
          :aria-describedby="form.errors.code ? 'code-error' : undefined"
        />
        <p v-if="form.errors.code" id="code-error" class="field-error text-sm leading-5 text-td-red" role="alert">{{ form.errors.code }}</p>
      </div>

      <div v-else-if="method === 'recovery'" class="field-group grid min-w-0 content-start gap-3">
        <label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="recovery_code">Código de recuperação</label>
        <input
          id="recovery_code"
          v-model="form.recovery_code"
          class="form-input w-full min-w-0 rounded-lg border border-td-border bg-transparent px-4 py-2.5 text-base leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 sm:text-sm"
          :class="{ 'form-input--error !border-td-red !ring-td-red/15': form.errors.recovery_code }"
          type="text"
          name="recovery_code"
          autocomplete="off"
          required
          :aria-invalid="Boolean(form.errors.recovery_code)"
          :aria-describedby="form.errors.recovery_code ? 'recovery-code-error' : undefined" placeholder="XXXX-XXXX"
        />
        <p v-if="form.errors.recovery_code" id="recovery-code-error" class="field-error text-sm leading-5 text-td-red" role="alert">{{ form.errors.recovery_code }}</p>
      </div>

      <div v-else class="field-group grid min-w-0 content-start gap-3">
        <label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="email-recovery-code">Código enviado por e-mail</label>
        <input
          id="email-recovery-code"
          v-model="emailCodeForm.code"
          class="form-input w-full min-w-0 rounded-lg border border-td-border bg-transparent px-4 py-2.5 text-base leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 sm:text-sm"
          :class="{ 'form-input--error !border-td-red !ring-td-red/15': emailCodeForm.errors.code }"
          type="text"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="6"
          pattern="[0-9]{6}"
          placeholder="000000"
          required
          :aria-invalid="Boolean(emailCodeForm.errors.code)"
          :aria-describedby="emailCodeForm.errors.code ? 'email-code-error' : 'email-code-help'"
        />
        <p v-if="emailCodeForm.errors.code" id="email-code-error" class="field-error text-sm leading-5 text-td-red" role="alert">{{ emailCodeForm.errors.code }}</p>
        <p v-else id="email-code-help" class="form-help text-sm leading-5 text-td-body">Válido por 10 minutos. Confirmar remove o segundo fator atual e inicia uma nova configuração.</p>
      </div>

      <label v-if="method !== 'email'" class="checkbox-row flex items-start gap-3 text-sm leading-6 text-td-body [&_input]:mt-1 [&_input]:size-4 [&_input]:shrink-0 [&_input]:accent-td-primary">
        <input v-model="form.remember" id="remember-two-factor" type="checkbox" name="remember" aria-describedby="remember-two-factor-help" />
        <span>Manter sessão por mais tempo</span>
      </label>
      <p v-if="method !== 'email'" id="remember-two-factor-help" class="form-help -mt-2 text-sm leading-5 text-td-body">Somente em dispositivo pessoal. O segundo fator continua ativo.</p>

      <div class="form-actions grid gap-3">
        <button
          class="btn-primary inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm leading-5 text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0"
          type="submit"
          :disabled="method === 'email' ? emailCodeForm.processing : form.processing"
          :aria-busy="method === 'email' ? emailCodeForm.processing : form.processing"
        >
          <ShieldCheckIcon aria-hidden="true" />
          <span>{{ method === 'email' ? (emailCodeForm.processing ? 'Confirmando…' : 'Confirmar recuperação') : form.processing ? 'Verificando…' : 'Continuar' }}</span>
        </button>
      </div>
    </form>

    <TwoFactorMethodOptions :method="method" :email-processing="emailForm.processing" @select="selectMethod" @request-email="sendEmailCode" />
  </AuthShell>
</template>
