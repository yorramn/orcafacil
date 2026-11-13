<script setup lang="ts">
defineProps<{ visible: boolean }>()
</script>

<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="global-loading-overlay fixed inset-0 z-[99999] grid place-items-center bg-white/85 px-6 text-gray-700 backdrop-blur-sm dark:bg-gray-950/85 dark:text-gray-200"
      role="status"
      aria-live="polite"
      aria-atomic="true"
    >
      <div class="flex flex-col items-center gap-4 text-center">
        <span
          aria-hidden="true"
          class="size-10 rounded-full border-[3px] border-[#3056D3]/20 border-t-[#3056D3] motion-safe:animate-spin"
        />
        <span class="text-sm font-medium leading-5">Carregando...</span>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.global-loading-overlay {
  font-family: Inter, var(--font-sans), sans-serif;
}
</style>
