<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'

defineProps<{ title: string; description?: string; backHref?: string; backLabel?: string }>()
</script>
<template><header class="page-header mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div class="min-w-0"><Link v-if="backHref" class="mb-3 inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-medium text-brand-500 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:hover:bg-brand-500/10" :href="backHref"><ArrowLeftIcon class="size-4 shrink-0" aria-hidden="true" />{{ backLabel || 'Voltar' }}</Link><h1 class="text-title-md font-semibold text-gray-800 dark:text-white/90 sm:text-title-lg">{{ title }}</h1><p v-if="description" class="mt-2 max-w-prose text-sm leading-6 text-gray-500 dark:text-gray-400">{{ description }}</p></div><div class="page-header__actions flex flex-wrap items-center gap-3 [&>*]:grow sm:[&>*]:grow-0"><slot name="actions" /></div></header></template>
