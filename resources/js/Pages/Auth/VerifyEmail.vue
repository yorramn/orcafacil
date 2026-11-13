<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { CheckCircleIcon, EnvelopeIcon, ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../Components/AuthShell.vue'
const props = defineProps<{ email?: string | null }>()
const page = usePage<{ props: { status?: string } }>()
const resent = shallowRef(false)
const resendForm = useForm({})
const logoutForm = useForm({})
const maskedEmail = computed(() => {
  const [local, domain] = (props.email ?? '').split('@')
  if (!local || !domain) return ''
  return `${local.slice(0, 1)}${'*'.repeat(Math.max(3, local.length - 1))}@${domain}`
})
function resend() { resendForm.post('/email/verification-notification', { onSuccess: () => { resent.value = true } }) }
function logout() { logoutForm.post('/logout') }
</script>
<template>
  <AuthShell title="Confirme seu e-mail" eyebrow="Só falta um passo" description="Enviamos um link de confirmação para o endereço usado no cadastro.">
    <div class="empty-state grid justify-items-start gap-3 py-6 [&>svg]:size-6 [&>svg]:text-td-primary [&_strong]:font-medium [&_strong]:text-td-ink [&_p]:text-sm [&_p]:leading-6"><div class="empty-state__icon flex size-11 items-center justify-center rounded-full bg-td-icon text-td-primary [&_svg]:size-5"><EnvelopeIcon aria-hidden="true" /></div><strong>Confira sua caixa de entrada</strong><p>Abra o link no e-mail para liberar a próxima etapa de segurança. Se não encontrar, verifique o spam.</p><p v-if="maskedEmail" class="font-medium text-td-ink">Enviado para {{ maskedEmail }}</p></div>
    <div v-if="resent || page.props.status === 'verification-link-sent'" class="status-message my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-green/25 bg-td-green/5 p-4 text-sm leading-6 text-td-green-ink [&_svg]:size-5 [&_svg]:shrink-0" role="status" aria-live="polite"><CheckCircleIcon aria-hidden="true" /><span>Novo link enviado. Se não chegar, confira também a pasta de spam.</span></div>
    <div class="form-actions mt-6 grid gap-3"><button class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm leading-5 text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0" type="button" :disabled="resendForm.processing" @click="resend">{{ resendForm.processing ? 'Enviando…' : 'Enviar novo link' }}</button><button class="btn-secondary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0" type="button" @click="logout"><ArrowRightOnRectangleIcon aria-hidden="true" /> Sair</button></div>
    <div class="link-row mt-6 flex flex-wrap items-center justify-between gap-3 text-sm"><Link class="text-link inline-flex min-h-11 items-center rounded text-td-primary hover:underline focus-visible:outline-2 focus-visible:outline-td-primary" href="/login">Voltar para o login</Link></div>
  </AuthShell>
</template>
