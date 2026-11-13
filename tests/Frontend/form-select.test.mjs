import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { stripTypeScriptTypes } from 'node:module'
import { pathToFileURL } from 'node:url'
import test from 'node:test'
import { createSSRApp, Fragment, h } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { parse } from '@vue/compiler-sfc'

const { descriptor } = parse(readFileSync('resources/js/Components/FormSelect.vue', 'utf8'))
let captured
globalThis.__captureFormSelect = props => { captured = props }
const vueUrl = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href
const source = descriptor.script.content
  .replace("from 'vue'", `from ${JSON.stringify(vueUrl)}`)
  .replace(/import CustomSelectControl from '[^']+'/,
    `const CustomSelectControl = defineComponent({ inheritAttrs: false, setup(_, { attrs }) { globalThis.__captureFormSelect(attrs); return () => h('button', { id: attrs.id, role: 'combobox' }, String(attrs.modelValue)) } })`)
const { default: FormSelect } = await import(`data:text/javascript;base64,${Buffer.from(stripTypeScriptTypes(source)).toString('base64')}`)

async function render(props, slot) {
  return renderToString(createSSRApp({ render: () => h(FormSelect, props, { default: slot }) }))
}

test('reads reactive-style Fragment options while preserving numeric values and disabled state', async () => {
  let emitted
  await render({ id: 'catalog', modelValue: 12, 'onUpdate:modelValue': value => { emitted = value } }, () => [
    h('option', { value: '' }, 'Selecione'),
    h(Fragment, null, [h('option', { value: 12 }, 'Visita'), h('option', { value: 13, disabled: true }, 'Indisponível')]),
  ])
  assert.equal(captured.modelValue, '12')
  assert.deepEqual(captured.options.map(({ original, label, disabled }) => ({ original, label, disabled })), [
    { original: '', label: 'Selecione', disabled: false },
    { original: 12, label: 'Visita', disabled: false },
    { original: 13, label: 'Indisponível', disabled: true },
  ])
  captured['onUpdate:modelValue']('12')
  assert.equal(emitted, 12)
  captured['onUpdate:modelValue']('13')
  assert.equal(emitted, 12)
})

test('preserves zero, empty options, optgroup disabled state and native form submission fields', async () => {
  const html = await render({ id: 'status', name: 'status', modelValue: 0, required: true, class: 'form-input border duplicate-native-appearance' }, () => [
    h('option', { value: 0 }, 'Zero'),
    h('optgroup', { disabled: true }, [h('option', { value: 'archived' }, 'Arquivado')]),
  ])
  assert.equal(captured.modelValue, '0')
  assert.equal(captured.options.find(option => option.value === 'archived').disabled, true)
  assert.match(html, /type="hidden" name="status" value="0"/)
  assert.match(html, /required/)
  assert.doesNotMatch(html, /duplicate-native-appearance/)
})

test('slot changes are read on each render and disabled controls cannot emit changes', async () => {
  let emitted = false
  await render({ id: 'item', disabled: true, modelValue: '', 'onUpdate:modelValue': () => { emitted = true } }, () => [h('option', { value: 'new' }, 'Novo')])
  assert.equal(captured.options[0].label, 'Novo')
  captured['onUpdate:modelValue']('new')
  assert.equal(emitted, false)
  await render({ id: 'item', modelValue: 'updated' }, () => [h('option', { value: 'updated' }, 'Atualizado')])
  assert.equal(captured.options[0].label, 'Atualizado')
})

test('supports searchable multiple selection and submits one hidden field per selected value', async () => {
  const html = await render({ id: 'members', name: 'members[]', modelValue: ['ana', 12], searchable: true, multiple: true, 'aria-describedby': 'members-help' }, () => [
    h('option', { value: 'ana' }, 'Ana'),
    h('option', { value: 12 }, 'Bruno'),
    h('option', { value: 'disabled', disabled: true }, 'Indisponível'),
  ])

  assert.equal(captured.searchable, true)
  assert.equal(captured.multiple, true)
  assert.deepEqual(captured.modelValue, ['ana', '12'])
  assert.equal(captured.controlAttrs['aria-describedby'], 'members-help')
  assert.match(html, /name="members\[\]" value="ana"/)
  assert.match(html, /name="members\[\]" value="12"/)
})

test('required form selects start on a disabled contextual prompt instead of the first real option', async () => {
  await render({ id: 'client', label: 'Cliente', required: true, modelValue: '' }, () => [
    h('option', { value: '12' }, 'Ana'),
  ])

  assert.equal(captured.modelValue, '')
  assert.equal(captured.emptyOptionSelected, true)
  assert.deepEqual(captured.options.map(({ value, label, disabled }) => ({ value, label, disabled })), [
    { value: '', label: 'Selecione um(a) cliente', disabled: true },
    { value: '12', label: 'Ana', disabled: false },
  ])
})

test('optional empty choices are not preselected, while listing all-options remains selected', async () => {
  await render({ id: 'catalog', modelValue: '' }, () => [
    h('option', { value: '' }, 'Personalizado'),
    h('option', { value: '12' }, 'Ana'),
  ])
  assert.equal(captured.modelValue, '')
  assert.equal(captured.emptyOptionSelected, false)

  await render({ id: 'status', context: 'filter', modelValue: '' }, () => [
    h('option', { value: '' }, 'Todas as situações'),
    h('option', { value: 'active' }, 'Ativos'),
  ])
  assert.equal(captured.emptyOptionSelected, true)
  assert.equal(captured.options[0].label, 'Todas as situações')
})
