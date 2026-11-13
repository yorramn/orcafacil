<script setup lang="ts">
import TdBrand from '../../Taildash/components/TdBrand.vue'
import { computed, nextTick, onMounted, shallowRef } from 'vue'
import { Head } from '@inertiajs/vue3'
import { CheckCircleIcon, XCircleIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = withDefaults(defineProps<{ status?: 'approved' | 'rejected' }>(), { status: 'approved' })
const returnLink = shallowRef<HTMLAnchorElement | null>(null)
const isApproved = computed(() => props.status === 'approved')
const returnUrl = computed(() => {
  const referrer = document.referrer
  try {
    return referrer && new URL(referrer).origin === window.location.origin ? referrer : '/'
  } catch { return '/' }
})
const returnLabel = computed(() => returnUrl.value === '/' ? 'Ir para o início' : 'Voltar ao orçamento')
onMounted(() => nextTick(() => returnLink.value?.focus()))
</script>
<template>
  <Head :title="isApproved ? 'Orçamento aprovado' : 'Orçamento recusado'" />
  <main class="public-shell public-result-shell min-h-screen bg-td-bg px-4 py-8 font-sans text-base leading-6 text-td-body sm:px-6 sm:py-12" aria-labelledby="quote-result-title">
    <div class="public-content mx-auto w-full max-w-4xl space-y-6 [overflow-wrap:anywhere]">
      <div class="brand-lockup public-brand flex items-center gap-3 text-xl font-semibold text-td-ink mb-8" aria-label="OrçaFácil"><TdBrand /></div>
      <section class="quote-document public-result min-w-0 space-y-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm shadow-td-card sm:p-8 text-center [&>a]:mx-auto" role="status" aria-live="polite">
        <div class="state-panel state-panel--center flex min-w-0 flex-col gap-4 py-8 [&>strong]:text-xl [&>strong]:font-semibold [&>strong]:text-td-ink [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:leading-[30px] [&_h1]:text-td-ink [&_p]:max-w-prose items-center text-center"><div class="state-panel__icon flex size-14 items-center justify-center rounded-full bg-td-icon text-td-primary [&_svg]:size-7"><CheckCircleIcon class="size-5 shrink-0" v-if="isApproved" aria-hidden="true" /><XCircleIcon class="size-5 shrink-0" v-else aria-hidden="true" /></div><h1 id="quote-result-title">{{ isApproved ? 'Orçamento aprovado' : 'Orçamento recusado' }}</h1><p>{{ isApproved ? 'Sua aprovação foi registrada com sucesso.' : 'Sua recusa foi registrada.' }}</p></div>
        <p class="form-help block text-sm leading-5 text-td-body">Esta página confirma o resultado enviado ao OrçaFácil. Nenhum código ou segredo é exibido aqui.</p>
        <a ref="returnLink" class="btn-secondary inline-flex min-h-11 max-w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-center text-sm font-medium leading-5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none [&_svg]:size-5 [&_svg]:shrink-0 border border-td-border bg-white text-td-ink hover:border-td-primary hover:text-td-primary" :href="returnUrl"><ArrowLeftIcon class="size-5 shrink-0" aria-hidden="true" /> {{ returnLabel }}</a>
      </section>
    </div>
  </main>
</template>
