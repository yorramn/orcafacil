<script setup lang="ts">
type Method = 'authenticator' | 'recovery' | 'email'

const props = defineProps<{
  method: Method
  emailProcessing: boolean
}>()

const emit = defineEmits<{
  select: [method: Method]
  requestEmail: []
}>()
</script>

<template>
  <details class="mt-4 border-t border-td-border pt-3">
    <summary class="min-h-11 cursor-pointer rounded text-sm font-medium text-td-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary">
      Mais opções de confirmação
    </summary>
    <div class="mt-2 grid gap-2" role="group" aria-label="Métodos alternativos de confirmação">
      <button
        class="inline-flex min-h-11 w-full items-center rounded-lg border px-4 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary"
        :class="props.method === 'authenticator' ? 'border-td-primary bg-td-soft font-medium text-td-ink ring-1 ring-inset ring-td-primary/20' : 'border-td-border bg-white text-td-ink hover:border-td-primary/40 hover:bg-td-soft'"
        type="button"
        :aria-pressed="props.method === 'authenticator'"
        @click="emit('select', 'authenticator')"
      >
        Usar código do autenticador
      </button>
      <button
        class="inline-flex min-h-11 w-full items-center rounded-lg border px-4 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary"
        :class="props.method === 'recovery' ? 'border-td-primary bg-td-soft font-medium text-td-ink ring-1 ring-inset ring-td-primary/20' : 'border-td-border bg-white text-td-ink hover:border-td-primary/40 hover:bg-td-soft'"
        type="button"
        :aria-pressed="props.method === 'recovery'"
        @click="emit('select', 'recovery')"
      >
        Usar código de recuperação
      </button>
      <button
        class="inline-flex min-h-11 w-full items-center rounded-lg border px-4 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50"
        :class="props.method === 'email' ? 'border-td-primary bg-td-soft font-medium text-td-ink ring-1 ring-inset ring-td-primary/20' : 'border-td-border bg-white text-td-ink hover:border-td-primary/40 hover:bg-td-soft'"
        type="button"
        :disabled="props.emailProcessing"
        :aria-pressed="props.method === 'email'"
        @click="emit('requestEmail')"
      >
        {{ props.emailProcessing ? 'Enviando código…' : props.method === 'email' ? 'Enviar outro código por e-mail' : 'Recuperar acesso por e-mail' }}
      </button>
    </div>
  </details>
</template>
