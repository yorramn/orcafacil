<script setup lang="ts">
import { computed, nextTick } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { EnvelopeIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../Components/AuthShell.vue'
import PasswordField from '../../Components/PasswordField.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
const props = defineProps<{ email?: string; token?: string }>()
const form = useForm({ token: props.token ?? '', email: props.email ?? '', password: '', password_confirmation: '' })
const requiredIncomplete = computed(() => !form.email.trim() || !form.token || form.password.length < 8 || !form.password_confirmation)
function submit() { form.post('/reset-password', { onError: () => nextTick(() => document.querySelector<HTMLElement>('[data-error-summary]')?.focus()) }) }
</script>
<template>
  <AuthShell title="Criar nova senha" eyebrow="Quase lá" description="Escolha uma senha nova para voltar à sua operação.">
    <FormErrorSummary :errors="form.errors" />
    <form class="auth-form mt-6 grid min-w-0 grid-cols-12 gap-6 [&>*]:col-span-12" novalidate @submit.prevent="submit">
      <div class="field-group grid min-w-0 content-start gap-3"><label class="field-label block text-sm font-medium text-gray-700 dark:text-gray-400" for="email">E-mail</label><div class="field-with-icon relative min-w-0"><EnvelopeIcon class="field-icon pointer-events-none absolute left-[14px] top-[15px] size-5 text-td-body" aria-hidden="true" /><input id="email" v-model="form.email" class="form-input form-input--with-leading w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-transparent px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body file:mr-3 file:rounded file:border-0 file:bg-td-soft file:px-3 file:py-1 file:text-sm file:text-td-primary pl-11 pr-12" type="email" name="email" autocomplete="email" readonly :aria-describedby="form.errors.email ? 'email-error' : undefined" placeholder="nome@empresa.com.br" /></div><p v-if="form.errors.email" id="email-error" class="field-error text-sm leading-5 text-td-red" role="alert">{{ form.errors.email }}</p></div>
      <PasswordField id="password" v-model="form.password" label="Nova senha" hint="Use pelo menos 8 caracteres." :error="form.errors.password" autocomplete="new-password" placeholder="Crie uma senha" />
      <PasswordField id="password_confirmation" v-model="form.password_confirmation" label="Confirme a nova senha" :error="form.errors.password_confirmation" autocomplete="new-password" placeholder="Repita a senha" />
      <div class="form-actions mt-6 grid gap-3"><SaveActionButton label="Salvar nova senha" :required-incomplete="requiredIncomplete" :processing="form.processing" /></div>
    </form>
    <div class="link-row mt-6 flex flex-wrap items-center justify-between gap-3 text-sm"><Link class="text-link inline-flex min-h-11 items-center rounded text-td-primary hover:underline focus-visible:outline-2 focus-visible:outline-td-primary" href="/login">Voltar para o login</Link></div>
  </AuthShell>
</template>
