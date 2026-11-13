<script setup lang="ts">
import { nextTick, onBeforeUnmount, shallowRef, useId, useTemplateRef, watch, type Component } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowDownTrayIcon, ClipboardDocumentIcon, EllipsisVerticalIcon, EyeIcon, LinkIcon, PencilSquareIcon, TrashIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'

type Action = { key: 'view' | 'edit' | 'delete' | 'versions' | 'copy' | 'download' | 'service'; label: string; href?: string; danger?: boolean; disabled?: boolean; download?: boolean }

const props = defineProps<{ label: string; actions: Action[] }>()
const emit = defineEmits<{ select: [key: Action['key']] }>()
const trigger = useTemplateRef<HTMLButtonElement>('trigger')
const menu = useTemplateRef<HTMLElement>('menu')
const menuId = useId()
const open = shallowRef(false)
const positioned = shallowRef(false)
const position = shallowRef({ top: 0, left: 0 })
const icons: Record<Action['key'], Component> = { view: EyeIcon, edit: PencilSquareIcon, delete: TrashIcon, versions: LinkIcon, copy: ClipboardDocumentIcon, download: ArrowDownTrayIcon, service: WrenchScrewdriverIcon }

function menuItems(): HTMLElement[] {
  return Array.from(menu.value?.querySelectorAll<HTMLElement>('[role="menuitem"]:not([disabled])') || [])
}

function placeMenu() {
  if (!trigger.value || !menu.value) return
  const anchor = trigger.value.getBoundingClientRect()
  const panel = menu.value.getBoundingClientRect()
  const left = Math.max(8, Math.min(anchor.right - panel.width, window.innerWidth - panel.width - 8))
  const below = anchor.bottom + 4
  const above = anchor.top - panel.height - 4
  const top = below + panel.height <= window.innerHeight - 8 || above < 8
    ? Math.max(8, Math.min(below, window.innerHeight - panel.height - 8))
    : above
  position.value = { top, left }
  positioned.value = true
}

async function showMenu(focusLast = false) {
  if (!props.actions.length) return
  open.value = true
  positioned.value = false
  await nextTick()
  placeMenu()
  const items = menuItems()
  ;(focusLast ? items.at(-1) : items[0])?.focus()
}

function closeMenu(restoreFocus = false) {
  open.value = false
  positioned.value = false
  if (restoreFocus) nextTick(() => trigger.value?.focus())
}

function toggleMenu() {
  if (open.value) closeMenu(true)
  else showMenu()
}

function selectAction(action: Action) {
  closeMenu()
  if (!action.href) emit('select', action.key)
}

function onOutsidePointer(event: PointerEvent) {
  const target = event.target as Node
  if (!trigger.value?.contains(target) && !menu.value?.contains(target)) closeMenu()
}

function focusAfterTrigger(backwards: boolean) {
  const controls = Array.from(document.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
    .filter((element) => element.getClientRects().length > 0 && !menu.value?.contains(element))
  const index = controls.indexOf(trigger.value as HTMLElement)
  controls[index + (backwards ? -1 : 1)]?.focus()
}

function onKeydown(event: KeyboardEvent) {
  if (!open.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    closeMenu(true)
    return
  }
  if (!menu.value?.contains(document.activeElement)) return
  if (event.key === 'Tab') {
    event.preventDefault()
    const backwards = event.shiftKey
    closeMenu()
    nextTick(() => focusAfterTrigger(backwards))
    return
  }
  const items = menuItems()
  const index = items.indexOf(document.activeElement as HTMLElement)
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    items[(index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus()
  } else if (event.key === 'Home' || event.key === 'End') {
    event.preventDefault()
    ;(event.key === 'Home' ? items[0] : items.at(-1))?.focus()
  }
}

watch(open, (isOpen) => {
  if (isOpen) {
    document.addEventListener('pointerdown', onOutsidePointer, true)
    document.addEventListener('keydown', onKeydown)
    window.addEventListener('scroll', onViewportChange, true)
    window.addEventListener('resize', onViewportChange)
  } else {
    removeListeners()
  }
})

function onViewportChange() { closeMenu() }
function removeListeners() {
  document.removeEventListener('pointerdown', onOutsidePointer, true)
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('scroll', onViewportChange, true)
  window.removeEventListener('resize', onViewportChange)
}
onBeforeUnmount(removeListeners)
</script>

<template>
  <button ref="trigger" class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-gray-300 dark:hover:bg-gray-800" type="button" :aria-label="label" aria-haspopup="menu" :aria-expanded="open" :aria-controls="open ? menuId : undefined" @click="toggleMenu" @keydown.down.prevent="showMenu()" @keydown.up.prevent="showMenu(true)">
    <EllipsisVerticalIcon class="size-5" aria-hidden="true" />
  </button>
  <Teleport to="body">
    <div v-if="open" :id="menuId" ref="menu" class="fixed z-[80] w-56 rounded-xl border border-gray-200 bg-white p-1.5 text-sm shadow-theme-lg dark:border-gray-700 dark:bg-gray-900" :class="{ invisible: !positioned }" :style="{ top: `${position.top}px`, left: `${position.left}px` }" role="menu" :aria-label="label">
      <template v-for="action in actions" :key="action.key">
        <a v-if="action.href && action.download" :href="action.href" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-gray-300 dark:hover:bg-gray-800" role="menuitem" tabindex="-1" @click="closeMenu()"><component :is="icons[action.key]" class="size-5 shrink-0" aria-hidden="true" />{{ action.label }}</a>
        <Link v-else-if="action.href" :href="action.href" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 focus-visible:outline-2 focus-visible:outline-brand-500" :class="action.danger ? 'text-error-700 hover:bg-error-50 dark:text-error-400 dark:hover:bg-error-500/10' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800'" role="menuitem" tabindex="-1" @click="closeMenu()"><component :is="icons[action.key]" class="size-5 shrink-0" aria-hidden="true" />{{ action.label }}</Link>
        <button v-else class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2 text-left focus-visible:outline-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50" :class="action.danger ? 'text-error-700 hover:bg-error-50 dark:text-error-400 dark:hover:bg-error-500/10' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800'" type="button" role="menuitem" tabindex="-1" :disabled="action.disabled" @click="selectAction(action)"><component :is="icons[action.key]" class="size-5 shrink-0" aria-hidden="true" />{{ action.label }}</button>
      </template>
    </div>
  </Teleport>
</template>
