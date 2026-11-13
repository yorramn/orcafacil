<script setup lang="ts">
import { ArrowTopRightOnSquareIcon, PhotoIcon } from '@heroicons/vue/24/outline'

type Attachment = { id: number; original_name: string; mime_type?: string | null }
defineProps<{ attachments: Attachment[] }>()
const isImage = (attachment: Attachment) => attachment.mime_type?.startsWith('image/') ?? false
const url = (attachment: Attachment) => `/attachments/${attachment.id}/view`
</script>

<template>
  <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-images-heading">
    <div class="mb-5 flex items-center gap-2">
      <PhotoIcon class="size-5 text-td-primary" aria-hidden="true" />
      <h2 id="service-images-heading" class="text-lg font-semibold text-td-ink">Imagens do serviço</h2>
    </div>
    <p v-if="!attachments.length" class="text-sm text-td-body">Nenhuma imagem adicionada.</p>
    <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-2 2xl:grid-cols-3">
      <li v-for="attachment in attachments" :key="attachment.id" class="min-w-0">
        <a :href="url(attachment)" target="_blank" rel="noopener noreferrer" class="group block overflow-hidden rounded-lg border border-td-border bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" :aria-label="`Abrir ${attachment.original_name} em nova aba`">
          <img v-if="isImage(attachment)" :src="url(attachment)" :alt="attachment.original_name" loading="lazy" class="aspect-square w-full object-cover transition-transform group-hover:scale-105 motion-reduce:transition-none" />
          <span v-else class="flex aspect-square flex-col items-center justify-center gap-2 p-3 text-center text-sm text-td-body"><PhotoIcon class="size-7" aria-hidden="true" />Arquivo sem prévia</span>
          <span class="flex min-h-11 items-center justify-between gap-2 border-t border-td-border bg-white px-3 py-2 text-sm text-td-ink"><span class="truncate">{{ attachment.original_name }}</span><ArrowTopRightOnSquareIcon class="size-4 shrink-0" aria-hidden="true" /></span>
        </a>
      </li>
    </ul>
  </section>
</template>
