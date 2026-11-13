<script setup lang="ts">
defineProps<{ light?: boolean }>()
</script>
<template><span class="inline-flex items-center gap-3 text-xl font-semibold tracking-tight" :class="light ? 'text-white' : 'text-gray-900 dark:text-white'"><span class="grid size-10 place-items-center rounded-xl bg-brand-500 text-white">O</span>Orça<span class="text-brand-500">Fácil</span></span></template>
