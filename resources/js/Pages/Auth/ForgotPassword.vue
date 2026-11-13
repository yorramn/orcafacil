<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { CheckCircleIcon, EnvelopeIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../Components/AuthShell.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
const page = usePage<{ props: { status?: string } }>()
const sent = ref(false)
const submitted = ref(false)
const form = useForm({ email: '' })
function focusSummary() { nextTick(() => document.querySelector<HTMLElement>('[data-error-summary]')?.focus()) }
function submit() { submitted.value = true; form.post('/forgot-password', { onSuccess: () => { sent.value = true }, onError: focusSummary }) }
</script>
<template>
  <AuthShell title="Recuperar senha" eyebrow="Volte a ter acesso" description="Informe seu e-mail. Enviaremos um link para criar uma nova senha.">
    <div v-if="sent || page.props.status" class="status-message my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-green/25 bg-td-green/5 p-4 text-sm leading-6 text-td-green-ink [&_svg]:size-5 [&_svg]:shrink-0" aria-live="polite"><CheckCircleIcon aria-hidden="true" /><span>Se existir uma conta com esse e-mail, o link de recuperação foi enviado. Confira sua caixa de entrada.</span></div>
    <div v-if="submitted && !form.processing && !form.hasErrors && !sent" class="status-message my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-green/25 bg-td-green/5 p-4 text-sm leading-6 text-td-green-ink [&_svg]:size-5 [&_svg]:shrink-0" aria-live="polite"><CheckCircleIcon aria-hidden="true" /><span>Solicitação processada.</span></div>
    <FormErrorSummary :errors="form.errors" />
    <form class="auth-form mt-6 grid min-w-0 grid-cols-12 gap-6 [&>*]:col-span-12" novalidate @submit.prevent="submit">
      <div class="field-group grid min-w-0 content-start gap-3"><label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="email">E-mail</label><div class="field-with-icon relative min-w-0"><EnvelopeIcon class="field-icon pointer-events-none absolute left-[14px] top-[15px] size-5 text-td-body" aria-hidden="true" /><input id="email" v-model="form.email" class="form-input form-input--with-leading w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-transparent px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body file:mr-3 file:rounded file:border-0 file:bg-td-soft file:px-3 file:py-1 file:text-sm file:text-td-primary pl-11 pr-12" :class="{ 'form-input--error !border-td-red !ring-td-red/15': form.errors.email }" type="email" name="email" autocomplete="email" inputmode="email" required :aria-describedby="form.errors.email ? 'email-error' : undefined" placeholder="nome@empresa.com.br" /></div><p v-if="form.errors.email" id="email-error" class="field-error text-sm leading-5 text-td-red" role="alert">{{ form.errors.email }}</p></div>
      <div class="form-actions mt-6 grid gap-3"><button class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm leading-5 text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0" type="submit" :disabled="form.processing">{{ form.processing ? 'Enviando link…' : 'Enviar link de recuperação' }}</button></div>
    </form>
    <div class="link-row mt-6 flex flex-wrap items-center justify-between gap-3 text-sm"><Link class="text-link inline-flex min-h-11 items-center rounded text-td-primary hover:underline focus-visible:outline-2 focus-visible:outline-td-primary" href="/login">Voltar para o login</Link></div>
  </AuthShell>
</template>
