<script lang="ts">
import { Comment, Fragment, Text, defineComponent, h, isVNode, onMounted, shallowRef, type PropType, type VNode } from 'vue'
import CustomSelectControl from './CustomSelectControl.vue'

type Value = string | number
type ModelValue = Value | Value[]
type Option = { value: string; original: Value; label: string; disabled: boolean }

function optionText(value: unknown): string {
  if (Array.isArray(value)) return value.map(optionText).join('')
  if (isVNode(value)) return value.type === Comment ? '' : optionText(value.children)
  return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}

/** Native option slots remain the source of labels, values and disabled state. */
function readOptions(nodes: readonly VNode[], inheritedDisabled = false): Option[] {
  return nodes.flatMap(node => {
    if (!isVNode(node) || node.type === Comment || node.type === Text) return []
    const disabled = inheritedDisabled || node.props?.disabled === '' || Boolean(node.props?.disabled)
    if (node.type === 'option') {
      const label = String(node.props?.label ?? optionText(node.children)).trim()
      const original = node.props?.value ?? label
      return [{ value: String(original), original, label, disabled }]
    }
    if ((node.type === Fragment || node.type === 'optgroup') && Array.isArray(node.children)) {
      return readOptions(node.children as VNode[], disabled)
    }
    return []
  })
}

export default defineComponent({
  name: 'FormSelect',
  inheritAttrs: false,
  props: {
    modelValue: { type: [String, Number, Array] as PropType<ModelValue>, default: '' },
    id: { type: String, required: true },
    name: String,
    label: String,
    placeholder: String,
    context: { type: String as PropType<'form' | 'filter'>, default: 'form' },
    searchPlaceholder: String,
    searchable: Boolean,
    multiple: Boolean,
    disabled: Boolean,
    required: Boolean,
  },
  emits: {
    'update:modelValue': (_value: ModelValue) => true,
    change: (_event: Event) => true,
  },
  setup(props, { attrs, slots, emit, expose }) {
    const control = shallowRef<{ focus: () => void } | null>(null)
    const validation = shallowRef<HTMLInputElement | null>(null)
    const externalLabel = shallowRef('')
    const invalid = shallowRef(false)
    const emptyOptionChosen = shallowRef(false)
    function focus() { control.value?.focus() }
    expose({ focus })
    onMounted(() => {
      const label = document.querySelector<HTMLLabelElement>(`label[for="${CSS.escape(props.id)}"]`)
      externalLabel.value = label?.textContent?.trim() || ''
    })

    return () => {
      // Read slots inside render so v-for updates stay tracked by Vue.
      const fieldLabel = props.label || String(attrs['aria-label'] || externalLabel.value || 'campo')
      const prompt = props.placeholder || (props.required
        ? `Selecione um(a) ${fieldLabel.toLocaleLowerCase()}`
        : `Selecione uma opção (opcional)`)
      let options = readOptions(slots.default?.() ?? [])
      if (props.required && !props.multiple) {
        const hasEmptyOption = options.some(option => option.value === '')
        options = hasEmptyOption
          ? options.map(option => option.value === '' ? { ...option, disabled: true } : option)
          : [{ value: '', original: '', label: prompt, disabled: true }, ...options]
      }
      const emptyPlaceholder = options.some(option => option.value === '' && option.disabled)
      const emptyOptionSelected = props.context === 'filter' || props.required || emptyPlaceholder || emptyOptionChosen.value
      const rawSelected = Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue]
      const selectedOptions = props.multiple
        ? rawSelected.flatMap(value => {
          const option = options.find(candidate => candidate.original === value || candidate.value === String(value))
          return option ? [option] : []
        })
        : (rawSelected[0] === '' && !emptyOptionSelected
          ? []
          : options.filter(option => option.original === rawSelected[0] || option.value === String(rawSelected[0])).slice(0, 1))
      const value = props.multiple ? selectedOptions.map(option => option.value) : selectedOptions[0]?.value ?? ''
      const controlAttrs = Object.fromEntries(Object.entries(attrs).filter(([key]) => key !== 'class' && key !== 'style' && key !== 'form'))

      const update = (next: string | string[]) => {
        if (props.disabled || (Array.isArray(next) && !props.multiple) || (!Array.isArray(next) && props.multiple)) return
        const nextValues = Array.isArray(next) ? next : [next]
        const nextOptions = [...new Set(nextValues)].map(value => options.find(option => option.value === value))
          .filter((option): option is Option => Boolean(option && !option.disabled))
        if (nextOptions.length !== nextValues.length) return
        emptyOptionChosen.value = props.context === 'form' && !props.required && nextOptions.some(option => option.value === '')
        invalid.value = false
        emit('update:modelValue', props.multiple ? nextOptions.map(option => option.original) : nextOptions[0].original)
        // Keep the established change event and native required validation bridge.
        if (validation.value) {
          validation.value.value = nextValues[0] ?? ''
          validation.value.dispatchEvent(new Event('change', { bubbles: true }))
        }
      }

      const formFields = props.name
        ? (props.multiple
          ? selectedOptions.map((option, index) => h('input', { key: index, type: 'hidden', name: props.name, value: option.value, disabled: props.disabled, form: attrs.form }))
          : h('input', { type: 'hidden', name: props.name, value: selectedOptions[0]?.value ?? '', disabled: props.disabled, form: attrs.form }))
        : null

      return h('div', { class: 'relative min-w-0' }, [
        h(CustomSelectControl, {
          ref: control,
          id: props.id,
          label: fieldLabel,
          placeholder: prompt,
          searchPlaceholder: props.searchPlaceholder,
          options,
          modelValue: value,
          emptyOptionSelected,
          multiple: props.multiple,
          searchable: props.searchable,
          'onUpdate:modelValue': update,
          disabled: props.disabled,
          required: props.required,
          error: invalid.value ? `O campo ${fieldLabel.toLocaleLowerCase()} é obrigatório.` : undefined,
          externalLabel: true,
          controlAttrs,
        }),
        h('input', {
          ref: validation,
          class: 'sr-only',
          type: 'text',
          value: props.multiple ? (Array.isArray(value) ? value[0] ?? '' : '') : value,
          required: props.required,
          disabled: props.disabled,
          form: attrs.form,
          tabindex: -1,
          'aria-hidden': 'true',
          onInvalid: (event: Event) => { event.preventDefault(); invalid.value = true; focus() },
          onChange: (event: Event) => emit('change', event),
        }),
        formFields,
      ])
    }
  },
})
</script>
