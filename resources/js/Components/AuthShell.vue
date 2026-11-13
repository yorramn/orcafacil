<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import CommonGridShape from '../TailAdminSource/components/common/CommonGridShape.vue'

withDefaults(defineProps<{
  title: string
  eyebrow?: string
  description?: string
  compact?: boolean
  brandPlacement?: 'header' | 'panel'
  appearance?: 'light' | 'reference'
  showSupport?: boolean
}>(), {
  compact: false,
  brandPlacement: 'header',
  appearance: 'light',
  showSupport: true,
})
</script>

<template>
  <Head :title="title" />
  <a class="skip-link absolute left-4 top-2 z-[100] -translate-y-[160%] rounded-md bg-td-ink px-4 py-3 text-white focus:translate-y-0 focus-visible:outline-2 focus-visible:outline-td-primary" href="#main-content">Pular para o conteúdo</a>
  <div class="relative z-1 min-h-screen p-6 font-outfit text-gray-500 sm:p-0 dark:bg-gray-900" :class="appearance === 'reference' ? 'dark bg-gray-900' : 'bg-white'">
    <main id="main-content" class="relative flex min-h-[calc(100vh-3rem)] w-full flex-col justify-center outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 sm:min-h-screen lg:flex-row" tabindex="-1">
        <section class="grid min-h-[calc(100vh-3rem)] w-full grid-rows-[1fr_auto] bg-white dark:bg-gray-900 sm:min-h-screen lg:w-1/2" :class="compact ? 'py-6 sm:py-5' : 'py-10'" aria-labelledby="page-title">
          <div class="mx-auto flex w-full max-w-md flex-col justify-center px-2 sm:px-0">
            <a v-if="brandPlacement === 'header'" href="/" class="inline-flex items-center gap-3 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white" :class="compact ? 'mb-5' : 'mb-8'" aria-label="OrçaFácil">
              <span class="flex size-10 items-center justify-center rounded-xl bg-brand-500 text-lg font-bold text-white">O</span>OrçaFácil
            </a>
            <p v-if="eyebrow" class="mb-2 text-sm font-medium text-brand-500">{{ eyebrow }}</p>
            <h1 id="page-title" class="mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">{{ title }}</h1>
            <p v-if="description" class="text-sm text-gray-500 dark:text-gray-400" :class="compact ? 'mb-4 leading-5' : 'mb-6 leading-6'">{{ description }}</p>
            <slot />
          </div>
          <p v-if="showSupport" class="mx-auto w-full max-w-md px-2 text-xs leading-5 text-gray-400 sm:px-0 lg:pb-2" :class="compact ? 'pt-5' : 'pt-8'">Precisa de ajuda? <a class="font-medium text-gray-500 hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-gray-400" href="mailto:suporte@orcafacil.com.br">Fale com o suporte</a></p>
        </section>
        <section class="relative hidden w-full items-center justify-center overflow-hidden px-10 lg:flex lg:w-1/2" :class="appearance === 'reference' ? 'bg-gray-800' : 'bg-brand-950'" aria-label="OrçaFácil">
          <CommonGridShape />
          <div class="relative z-10 max-w-md text-center">
            <div v-if="brandPlacement === 'panel'" class="mb-5 flex items-center justify-center gap-4">
              <span class="flex size-14 items-center justify-center rounded-xl bg-brand-500 text-2xl font-bold text-white" aria-hidden="true">O</span>
              <span class="text-4xl font-semibold tracking-tight text-white">OrçaFácil</span>
            </div>
            <h2 :class="brandPlacement === 'panel' ? 'text-lg font-normal leading-7 text-gray-300' : 'text-2xl font-semibold leading-tight text-white'">Orçamentos e serviços, em ordem.</h2>
          </div>
        </section>
    </main>
  </div>
</template>
