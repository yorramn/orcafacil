<script setup lang="ts">
import { ArrowPathIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
defineProps<{ kind: 'empty' | 'filtered' | 'error' | 'loading'; title?: string; description?: string; retry?: boolean }>()
defineEmits<{ retry: [] }>()
</script>
<template>
  <div v-if="kind === 'loading'" class="state-panel grid justify-items-start gap-3 py-10 text-td-body" aria-busy="true"><div class="skeleton skeleton--large w-full rounded-md bg-td-icon motion-safe:animate-pulse min-h-[120px]"  /><div class="skeleton w-[45%] min-h-[18px] rounded-md bg-td-icon motion-safe:animate-pulse"  /></div>
  <div v-else class="state-panel grid gap-3 py-10 text-td-body" :class="kind === 'error' ? 'justify-items-start' : 'state-panel--center justify-items-center text-center'" :role="kind === 'error' ? 'alert' : undefined">
    <div class="state-panel__icon grid size-[46px] place-items-center rounded-full bg-td-icon text-td-primary [&_svg]:size-6"><ArrowPathIcon v-if="kind === 'error'" aria-hidden="true" /><InformationCircleIcon v-else aria-hidden="true" /></div>
    <strong class="text-base font-semibold text-td-ink">{{ title || (kind === 'error' ? 'Não foi possível carregar estes dados' : kind === 'filtered' ? 'Nenhum resultado encontrado' : 'Ainda não há registros') }}</strong>
    <p class="max-w-[560px] text-sm leading-6">{{ description || (kind === 'error' ? 'Os resultados anteriores foram mantidos. Tente novamente.' : kind === 'filtered' ? 'Ajuste ou limpe os filtros para continuar.' : 'Quando houver dados, eles aparecerão aqui.') }}</p>
    <button v-if="kind === 'error' && retry" class="btn-secondary inline-flex min-h-[50px] cursor-pointer items-center justify-center gap-2 rounded-md border border-td-border bg-white px-6 py-3 text-base leading-6 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0" type="button" @click="$emit('retry')"><ArrowPathIcon aria-hidden="true" /> Tentar novamente</button>
    <slot />
  </div>
</template>
