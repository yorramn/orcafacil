<script setup lang="ts">
import { ref } from 'vue'
import AuthShell from '../../Components/AuthShell.vue'

const hasPreviousPage = ref(typeof window !== 'undefined' && window.history.length > 1)

function goBack() {
  if (hasPreviousPage.value) {
    window.history.back()
    return
  }

  window.location.assign('/')
}
</script>

<template>
  <AuthShell
    title="Você não tem acesso a esta página"
    eyebrow="Acesso restrito"
    description="Sua conta não tem permissão para abrir este conteúdo. Nenhuma alteração foi feita."
  >
    <section class="grid gap-3 rounded-lg border border-td-border bg-td-soft p-4 text-sm leading-6 text-td-body" aria-labelledby="forbidden-help-title">
      <h2 id="forbidden-help-title" class="font-medium text-td-ink">Como continuar</h2>
      <p>Volte à página anterior ou peça ao responsável pela operação para verificar seu acesso.</p>
    </section>
    <div class="mt-6 grid gap-3 sm:grid-cols-2">
      <button
        class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-base text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary"
        type="button"
        @click="goBack"
      >
        Voltar
      </button>
      <a
        class="btn-secondary inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-base text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary"
        href="mailto:suporte@orcafacil.com.br?subject=Solicita%C3%A7%C3%A3o%20de%20acesso"
      >
        Pedir ajuda ao suporte
      </a>
    </div>
  </AuthShell>
</template>
