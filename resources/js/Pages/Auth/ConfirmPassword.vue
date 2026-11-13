<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../Components/AuthShell.vue'
import PasswordField from '../../Components/PasswordField.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
const form = useForm({ password: '' })
const confirmed = ref(false)
function submit() {
  form.post('/user/confirm-password', {
    onSuccess: () => { confirmed.value = true },
    onError: () => nextTick(() => document.querySelector<HTMLElement>('[data-error-summary]')?.focus()),
  })
}
</script>
<template>
  <AuthShell title="Confirme sua senha" eyebrow="Área protegida" description="Por segurança, confirme sua senha antes de continuar.">
    <div v-if="confirmed" class="status-message my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-green/25 bg-td-green/5 p-4 text-sm leading-6 text-td-green-ink [&_svg]:size-5 [&_svg]:shrink-0" aria-live="polite"><ShieldCheckIcon aria-hidden="true" /><span>Senha confirmada. Você pode continuar.</span></div>
    <FormErrorSummary :errors="form.errors" />
    <form class="auth-form mt-6 grid min-w-0 grid-cols-12 gap-6 [&>*]:col-span-12" @submit.prevent="submit">
      <PasswordField id="password" v-model="form.password" label="Senha atual" :error="form.errors.password" placeholder="Digite sua senha atual" />
      <div class="form-actions mt-6 grid gap-3"><button class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm leading-5 text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0" type="submit" :disabled="form.processing"><ShieldCheckIcon aria-hidden="true" /><span>{{ form.processing ? 'Confirmando…' : 'Confirmar senha' }}</span></button></div>
    </form>
    <div class="link-row mt-6 flex flex-wrap items-center justify-between gap-3 text-sm"><Link class="text-link inline-flex min-h-11 items-center rounded text-td-primary hover:underline focus-visible:outline-2 focus-visible:outline-td-primary" href="/dashboard">Voltar</Link></div>
  </AuthShell>
</template>
