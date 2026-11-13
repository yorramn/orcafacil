<script setup lang="ts">
import { computed, nextTick } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import UserIcon from '../../TailAdminSource/icons/layout/UserIcon.vue'
import EnvelopeIcon from '../../TailAdminSource/icons/layout/EnvelopeIcon.vue'
import AuthShell from '../../Components/AuthShell.vue'
import PasswordField from '../../Components/PasswordField.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })
const passwordConfirmationHint = computed(() => {
  if (!form.password_confirmation) return undefined
  return form.password_confirmation === form.password ? 'As senhas coincidem.' : 'As senhas não coincidem.'
})
const passwordConfirmationHintTone = computed(() => {
  if (!form.password_confirmation) return 'default'
  return form.password_confirmation === form.password ? 'success' : 'warning'
})
function submit() { form.post('/register', { onError: () => nextTick(() => document.querySelector<HTMLElement>('[data-error-summary]')?.focus()) }) }
</script>
<template>
  <AuthShell title="Criar sua conta" compact brand-placement="panel" appearance="reference">
    <FormErrorSummary :errors="form.errors" />
    <form class="auth-form mt-4 grid min-w-0 grid-cols-12 gap-4 [&>*]:col-span-12" novalidate @submit.prevent="submit">
      <div class="field-group grid min-w-0 content-start gap-2"><label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="name">Nome <span aria-hidden="true" class="text-error-500">*</span></label><div class="field-with-icon relative min-w-0"><UserIcon class="field-icon pointer-events-none absolute left-3.5 top-3 size-4 text-gray-400" aria-hidden="true" /><input id="name" v-model="form.name" class="form-input h-11 w-full min-w-0 scroll-mt-28 rounded-[10px] border border-gray-300 bg-white px-4 py-2.5 pl-10 pr-12 text-sm text-gray-800 shadow-theme-xs outline-none transition placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" :class="{ 'form-input--error !border-error-500 !ring-error-500/10': form.errors.name }" name="name" autocomplete="name" required :aria-describedby="form.errors.name ? 'name-error' : undefined" placeholder="Ex.: Ana Souza" /></div><p v-if="form.errors.name" id="name-error" class="field-error text-sm leading-5 text-error-500" role="alert">{{ form.errors.name }}</p></div>
      <div class="field-group grid min-w-0 content-start gap-2"><label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="email">E-mail <span aria-hidden="true" class="text-error-500">*</span></label><div class="field-with-icon relative min-w-0"><EnvelopeIcon class="field-icon pointer-events-none absolute left-3.5 top-3 size-4 text-gray-400" aria-hidden="true" /><input id="email" v-model="form.email" class="form-input h-11 w-full min-w-0 scroll-mt-28 rounded-[10px] border border-gray-300 bg-white px-4 py-2.5 pl-10 pr-12 text-sm text-gray-800 shadow-theme-xs outline-none transition placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" :class="{ 'form-input--error !border-error-500 !ring-error-500/10': form.errors.email }" type="email" name="email" autocomplete="email" inputmode="email" required :aria-describedby="form.errors.email ? 'email-error' : undefined" placeholder="nome@empresa.com.br" /></div><p v-if="form.errors.email" id="email-error" class="field-error text-sm leading-5 text-error-500" role="alert">{{ form.errors.email }}</p></div>
      <PasswordField id="password" v-model="form.password" label="Senha" hint="Mínimo de 8 caracteres." :error="form.errors.password" autocomplete="new-password" placeholder="Crie uma senha" required />
      <PasswordField id="password_confirmation" v-model="form.password_confirmation" label="Confirme sua senha" :hint="passwordConfirmationHint" :hint-tone="passwordConfirmationHintTone" :error="form.errors.password_confirmation" autocomplete="new-password" placeholder="Repita a senha" required />
      <div class="form-actions mt-1 grid gap-2"><button class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-sm font-medium leading-5 text-white transition-colors hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50" type="submit" :disabled="form.processing">{{ form.processing ? 'Criando conta…' : 'Criar conta' }}</button></div>
    </form>
    <div class="link-row mt-3 flex flex-wrap items-center justify-between gap-3 text-sm"><span>Já tem acesso?</span><Link class="text-link inline-flex min-h-11 items-center rounded text-td-primary hover:underline focus-visible:outline-2 focus-visible:outline-td-primary" href="/login">Entrar</Link></div>
  </AuthShell>
</template>
