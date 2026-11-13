<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, shallowRef, markRaw, useTemplateRef, watch, type Component } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LayoutGridIcon from '../TailAdminSource/icons/layout/LayoutGridIcon.vue'
import UsersIcon from '../TailAdminSource/icons/layout/UsersIcon.vue'
import FileIcon from '../TailAdminSource/icons/layout/FileIcon.vue'
import BoxIcon from '../TailAdminSource/icons/layout/BoxIcon.vue'
import GearIcon from '../TailAdminSource/icons/layout/GearIcon.vue'
import DollarIcon from '../TailAdminSource/icons/layout/DollarIcon.vue'
import BarChartIcon from '../TailAdminSource/icons/layout/BarChartIcon.vue'
import BellIcon from '../TailAdminSource/icons/layout/BellIcon.vue'
import UserCircleIcon from '../TailAdminSource/icons/layout/UserCircleIcon.vue'
import LockIcon from '../TailAdminSource/icons/layout/LockIcon.vue'
import LogOutIcon from '../TailAdminSource/icons/layout/LogOutIcon.vue'
import MenuIcon from '../TailAdminSource/icons/layout/MenuIcon.vue'
import XIcon from '../TailAdminSource/icons/layout/XIcon.vue'
import FlashToast from './FlashToast.vue'

const props = defineProps<{ title: string; active?: string }>()

type Tenant = { id?: string | number; name?: string }
type SharedPage = { tenant?: Tenant | null; auth?: { tenant?: Tenant | null; user?: { tenant?: Tenant | null } }; props?: { tenant?: Tenant | null } }
const page = usePage<SharedPage>()
const activeTenant = computed(() => page.props.tenant || page.props.auth?.tenant || page.props.auth?.user?.tenant || page.props.props?.tenant || null)
const tenantName = computed(() => activeTenant.value?.name?.trim() || 'Operação atual não informada')
const toastVisible = shallowRef(false)
const currentNavKey = computed(() => {
  const path = page.url.split('?')[0]
  if (path.startsWith('/settings/team')) return 'team'
  if (path.startsWith('/billing')) return 'billing'
  if (path.startsWith('/profile')) return 'profile'
  if (path.startsWith('/settings/security')) return 'security'
  return props.active
})

const menuOpen = shallowRef(false)
const menuButtonRef = useTemplateRef<HTMLButtonElement>('menuButton')
const menuTrigger = shallowRef<HTMLButtonElement | null>(null)
const drawerRef = useTemplateRef<HTMLElement>('mobileDrawer')
const logoutForm = useForm({})
const navItems: Array<{ key: string; label: string; href: string; icon: Component }> = [
  { key: 'dashboard', label: 'Dashboard', href: '/dashboard', icon: markRaw(LayoutGridIcon) },
  { key: 'clients', label: 'Clientes', href: '/clients', icon: markRaw(UsersIcon) },
  { key: 'quotes', label: 'Orçamentos', href: '/quotes', icon: markRaw(FileIcon) },
  { key: 'catalog', label: 'Catálogo', href: '/catalog', icon: markRaw(BoxIcon) },
  { key: 'services', label: 'Serviços', href: '/services', icon: markRaw(GearIcon) },
  { key: 'receivables', label: 'Recebimentos', href: '/receivables', icon: markRaw(DollarIcon) },
  { key: 'reports', label: 'Relatórios', href: '/reports', icon: markRaw(BarChartIcon) },
  { key: 'activity', label: 'Atividade', href: '/activity', icon: markRaw(BellIcon) },
  { key: 'profile', label: 'Perfil', href: '/profile', icon: markRaw(UserCircleIcon) },
  { key: 'team', label: 'Equipe', href: '/settings/team', icon: markRaw(UsersIcon) },
  { key: 'billing', label: 'Plano e cobrança', href: '/billing', icon: markRaw(DollarIcon) },
  { key: 'security', label: 'Segurança', href: '/settings/security/two-factor', icon: markRaw(LockIcon) },
]

function openMenu(event: MouseEvent) {
  menuTrigger.value = event.currentTarget as HTMLButtonElement
  menuOpen.value = true
}
function closeMenu() { menuOpen.value = false }
function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') closeMenu()
}
function getDrawerFocusableElements() {
  return Array.from(drawerRef.value?.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [])
}
function handleDrawerKeydown(event: KeyboardEvent) {
  if (event.key !== 'Tab') return
  const focusable = getDrawerFocusableElements()
  if (!focusable.length) {
    event.preventDefault()
    drawerRef.value?.focus()
    return
  }
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && (document.activeElement === drawerRef.value || document.activeElement === first)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
function handleDocumentFocus(event: FocusEvent) {
  if (menuOpen.value && drawerRef.value && !drawerRef.value.contains(event.target as Node)) drawerRef.value.focus()
}
function logout() { logoutForm.post('/logout') }

onMounted(() => window.addEventListener('keydown', handleKeydown))
onMounted(() => document.addEventListener('focusin', handleDocumentFocus))
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('focusin', handleDocumentFocus)
})
watch(menuOpen, async (isOpen) => {
  await nextTick()
  if (isOpen) drawerRef.value?.focus()
  else {
    const trigger = menuTrigger.value || menuButtonRef.value
    trigger?.focus()
    menuTrigger.value = null
  }
})
</script>

<template>
  <Head :title="title" />
  <a class="skip-link absolute left-4 top-2 z-[100] -translate-y-[160%] rounded-md bg-td-ink px-4 py-3 text-white focus:translate-y-0 focus-visible:outline-2 focus-visible:outline-td-primary" href="#main-content">Pular para o conteúdo</a>
  <div class="app-shell min-h-screen min-w-0 bg-gray-50 font-outfit text-sm text-gray-500 dark:bg-gray-900 dark:text-gray-400">
    <aside class="app-sidebar fixed inset-y-0 left-0 z-40 hidden w-[290px] flex-col overflow-y-auto border-r border-gray-200 bg-white px-5 pb-6 dark:border-gray-800 dark:bg-gray-900 lg:flex" aria-label="Navegação principal">
      <div class="flex h-[80px] shrink-0 items-center gap-3 text-xl font-semibold tracking-tight text-gray-900 dark:text-white"><span class="flex size-9 items-center justify-center rounded-xl bg-brand-500 text-base text-white">O</span>OrçaFácil</div>
      <div class="my-5 min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs dark:border-gray-800 dark:bg-white/[0.03]" aria-live="polite"><span class="block text-gray-400">Operação atual</span><strong class="mt-1 block truncate font-medium text-gray-800 dark:text-white/90">{{ tenantName }}</strong></div>
      <p class="mb-3 px-3 text-xs font-medium uppercase tracking-wider text-gray-400">Menu</p>
      <nav class="grid gap-1 [&_a]:group [&_a]:flex [&_a]:min-h-11 [&_a]:items-center [&_a]:gap-3 [&_a]:rounded-lg [&_a]:px-3 [&_a]:py-2.5 [&_a]:text-sm [&_a]:font-medium [&_a]:text-gray-700 [&_a:hover]:bg-gray-100 [&_a:hover]:text-gray-900 [&_a[aria-current=page]]:bg-brand-50 [&_a[aria-current=page]]:text-brand-500 [&_svg]:size-5 [&_svg]:shrink-0 [&_a:focus-visible]:outline-2 [&_a:focus-visible]:outline-brand-500 dark:[&_a]:text-gray-300 dark:[&_a:hover]:bg-white/[0.03] dark:[&_a[aria-current=page]]:bg-brand-500/10 dark:[&_a[aria-current=page]]:text-brand-400">
        <Link v-for="item in navItems.slice(0, 6)" :key="item.key" :href="item.href" :aria-current="currentNavKey === item.key ? 'page' : undefined"><component :is="item.icon" aria-hidden="true" /> {{ item.label }}</Link>
      </nav>
      <p class="mb-3 mt-8 px-3 text-xs font-medium uppercase tracking-wider text-gray-400">Outros</p>
      <nav class="grid gap-1 [&_a]:flex [&_a]:min-h-11 [&_a]:items-center [&_a]:gap-3 [&_a]:rounded-lg [&_a]:px-3 [&_a]:py-2.5 [&_a]:text-sm [&_a]:font-medium [&_a]:text-gray-700 [&_a:hover]:bg-gray-100 [&_a[aria-current=page]]:bg-brand-50 [&_a[aria-current=page]]:text-brand-500 [&_svg]:size-5 dark:[&_a]:text-gray-300 dark:[&_a:hover]:bg-white/[0.03] dark:[&_a[aria-current=page]]:bg-brand-500/10 dark:[&_a[aria-current=page]]:text-brand-400">
        <Link v-for="item in navItems.slice(6)" :key="item.key" :href="item.href" :aria-current="currentNavKey === item.key ? 'page' : undefined"><component :is="item.icon" aria-hidden="true" /> {{ item.label }}</Link>
      </nav>
      <div class="mt-auto pt-8 text-sm"><button class="flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-gray-600 hover:bg-gray-100 hover:text-brand-500 focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-gray-300 dark:hover:bg-white/[0.03]" type="button" @click="logout"><LogOutIcon class="size-5" aria-hidden="true" /> Sair</button></div>
    </aside>
    <main id="main-content" class="app-main min-w-0 pb-6 pt-[72px] outline-none lg:ml-[290px]" tabindex="-1">
      <header class="app-topbar fixed inset-x-0 top-0 z-30 flex h-[72px] min-w-0 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 sm:px-6 lg:left-[290px] dark:border-gray-800 dark:bg-gray-900">
        <div class="app-topbar__mobile-actions flex min-w-0 items-center gap-3 lg:hidden"><button ref="menuButton" class="inline-flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-brand-500 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-white/[0.03] [&_svg]:size-5" type="button" aria-label="Abrir menu de navegação" :aria-expanded="menuOpen" aria-controls="mobile-drawer" @click="openMenu"><MenuIcon aria-hidden="true" /></button><p class="truncate font-medium text-gray-800 dark:text-white/90">{{ title }}</p></div>
        <p class="hidden truncate font-medium text-gray-800 dark:text-white/90 lg:block">{{ title }}</p>
        <span v-if="!toastVisible" class="hidden max-w-[50%] truncate text-sm sm:block">{{ tenantName }}</span>
      </header>
      <FlashToast @visibility-change="toastVisible = $event" />
      <slot />
    </main>
  </div>
  <div v-if="menuOpen" class="mobile-drawer-backdrop fixed inset-0 z-40 bg-td-ink/40 lg:hidden" aria-hidden="true" @click="closeMenu"></div>
  <aside v-if="menuOpen" ref="mobileDrawer" id="mobile-drawer" class="mobile-drawer fixed inset-y-0 left-0 z-50 flex w-[min(86vw,18rem)] flex-col overflow-y-auto border-r border-gray-200 bg-white p-5 text-gray-500 shadow-theme-lg outline-none lg:hidden dark:border-gray-800 dark:bg-gray-900" aria-labelledby="mobile-drawer-title" aria-modal="true" role="dialog" tabindex="-1" @keydown="handleDrawerKeydown">
    <div class="mobile-drawer__header mb-6 flex items-center justify-between gap-2"><div id="mobile-drawer-title" class="inline-flex items-center gap-3 text-lg font-semibold text-gray-900 dark:text-white"><span class="flex size-9 items-center justify-center rounded-xl bg-brand-500 text-white">O</span>OrçaFácil</div><button class="inline-flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-white/[0.03] [&_svg]:size-5" type="button" aria-label="Fechar menu de navegação" @click="closeMenu"><XIcon aria-hidden="true" /></button></div>
    <nav class="mobile-drawer__nav grid gap-1 [&_a]:flex [&_a]:min-h-12 [&_a]:items-center [&_a]:gap-3 [&_a]:rounded-md [&_a]:px-4 [&_a]:py-3 [&_a:hover]:bg-td-soft [&_a:hover]:text-td-primary [&_a[aria-current=page]]:bg-td-primary [&_a[aria-current=page]]:text-white [&_svg]:size-5 [&_svg]:shrink-0 [&_a:focus-visible]:outline-2 [&_a:focus-visible]:outline-td-primary">
      <Link v-for="item in navItems" :key="item.key" :href="item.href" :aria-current="currentNavKey === item.key ? 'page' : undefined" @click="closeMenu"><component :is="item.icon" aria-hidden="true" />{{ item.label }}</Link>
    </nav>
    <button class="btn-secondary mobile-drawer__logout inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0 mt-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" type="button" @click="logout"><LogOutIcon aria-hidden="true" /> Sair</button>
  </aside>
</template>
