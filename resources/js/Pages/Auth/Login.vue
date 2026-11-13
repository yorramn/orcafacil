<script setup lang="ts">
import { computed, nextTick } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { InformationCircleIcon } from '@heroicons/vue/24/outline'
import EnvelopeIcon from '../../TailAdminSource/icons/layout/EnvelopeIcon.vue'
import AuthShell from '../../Components/AuthShell.vue'
import PasswordField from '../../Components/PasswordField.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
const page = usePage<{ props: { status?: string } }>()
const form = useForm({ email: '', password: '', remember: false })
const statusText = computed(() => page.props.status === 'email-changed' ? 'E-mail atualizado. Entre novamente com seu novo endereço.' : page.props.status === 'account-deleted' ? 'Sua conta foi excluída.' : '')
function submit() { form.post('/login', { onError: () => nextTick(() => document.querySelector<HTMLElement>('[data-error-summary]')?.focus()) }) }
</script>
<template>
  <AuthShell title="Entrar">
    <div v-if="statusText" class="status-message my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-green/25 bg-td-green/5 p-4 text-sm leading-6 text-td-green-ink [&_svg]:size-5 [&_svg]:shrink-0" aria-live="polite"><InformationCircleIcon aria-hidden="true" /><span>{{ statusText }}</span></div>
    <FormErrorSummary :errors="form.errors" />
    <form class="auth-form mt-6 grid min-w-0 grid-cols-12 gap-5 [&>*]:col-span-12" @submit.prevent="submit">
      <div class="field-group grid min-w-0 content-start gap-2"><label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="email">E-mail</label><div class="field-with-icon relative min-w-0"><EnvelopeIcon class="field-icon pointer-events-none absolute left-3.5 top-3 size-4 text-gray-400" aria-hidden="true" /><input id="email" v-model="form.email" class="form-input form-input--with-leading h-11 w-full min-w-0 scroll-mt-28 rounded-[10px] border border-gray-300 bg-white px-4 py-2.5 pl-10 pr-12 text-sm text-gray-800 shadow-theme-xs outline-none transition focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" :class="{ 'form-input--error !border-error-500 !ring-error-500/10': form.errors.email }" type="email" name="email" autocomplete="email" inputmode="email" required :aria-invalid="Boolean(form.errors.email)" :aria-describedby="form.errors.email ? 'email-error' : undefined" placeholder="nome@empresa.com.br" /></div><p v-if="form.errors.email" id="email-error" class="field-error text-sm leading-5 text-error-500" role="alert">{{ form.errors.email }}</p></div>
      <PasswordField id="password" v-model="form.password" label="Senha" :error="form.errors.password" required placeholder="Digite sua senha" />
      <div class="flex min-h-11 flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
        <label class="checkbox-row inline-flex min-h-11 cursor-pointer items-center gap-2.5 text-gray-600 dark:text-gray-300 [&_input]:size-4 [&_input]:shrink-0 [&_input]:accent-brand-500">
          <input id="remember-login" v-model="form.remember" type="checkbox" name="remember" />
          <span>Manter acesso</span>
        </label>
        <Link class="text-link inline-flex min-h-11 items-center rounded text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500" href="/forgot-password">Esqueci minha senha</Link>
      </div>
      <div class="form-actions grid gap-3"><button class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-sm font-medium leading-5 text-white transition-colors hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50" type="submit" :disabled="form.processing">{{ form.processing ? 'Entrando…' : 'Entrar' }}</button></div>
    </form>
    <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">Ainda não tem uma conta? <Link class="font-medium text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500" href="/register">Criar conta</Link></p>
  </AuthShell>
</template>
