import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TVSelect from './TVSelect.vue'
import type { SelectOption } from '@/types'

const OPTIONS: SelectOption[] = [
  { value: 'en', label: 'English' },
  { value: 'es', label: 'Spanish' },
  { value: 'fr', label: 'French', disabled: true },
]

describe('TVSelect', () => {
  it('renders the trigger button', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    expect(wrapper.find('.tv-select__trigger').exists()).toBe(true)
  })

  it('shows placeholder text when no value selected', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, placeholder: 'Pick one' } })
    expect(wrapper.find('.tv-select__value').text()).toBe('Pick one')
    expect(wrapper.find('.tv-select__value').classes()).toContain('tv-select__value--placeholder')
  })

  it('shows selected option label when modelValue matches', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, modelValue: 'es' } })
    expect(wrapper.find('.tv-select__value').text()).toBe('Spanish')
    expect(wrapper.find('.tv-select__value').classes()).not.toContain('tv-select__value--placeholder')
  })

  it('dropdown is closed by default', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    expect(wrapper.find('.tv-select__dropdown').exists()).toBe(false)
  })

  it('opens dropdown on trigger click', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    expect(wrapper.find('.tv-select__dropdown').exists()).toBe(true)
  })

  it('renders all options when open', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    const items = wrapper.findAll('.tv-select__option')
    expect(items).toHaveLength(3)
    expect(items[0].text()).toContain('English')
    expect(items[1].text()).toContain('Spanish')
    expect(items[2].text()).toContain('French')
  })

  it('marks disabled options with disabled class', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    const opts = wrapper.findAll('.tv-select__option')
    expect(opts[2].classes()).toContain('tv-select__option--disabled')
  })

  it('emits update:modelValue when a non-disabled option is clicked', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    const opts = wrapper.findAll('.tv-select__option')
    await opts[1].trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['es'])
  })

  it('does not emit when a disabled option is clicked', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    const opts = wrapper.findAll('.tv-select__option')
    await opts[2].trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
  })

  it('closes dropdown after selecting an option', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    await wrapper.findAll('.tv-select__option')[0].trigger('click')
    expect(wrapper.find('.tv-select__dropdown').exists()).toBe(false)
  })

  it('shows checkmark on selected option', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, modelValue: 'en' } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    const first = wrapper.findAll('.tv-select__option')[0]
    expect(first.find('.tv-select__option-check').exists()).toBe(true)
    expect(first.classes()).toContain('tv-select__option--selected')
  })

  it('renders the label', () => {
    const wrapper = mount(TVSelect, { props: { options: [], label: 'Language' } })
    expect(wrapper.find('.tv-select__label').text()).toContain('Language')
  })

  it('shows required asterisk when required=true', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, label: 'Lang', required: true } })
    expect(wrapper.find('.tv-select__required').exists()).toBe(true)
  })

  it('shows error message when error is set', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, error: 'Required field' } })
    expect(wrapper.find('.tv-select__message--error').text()).toBe('Required field')
  })

  it('shows hint when no error', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, hint: 'Choose your subject' } })
    expect(wrapper.find('.tv-select__message--hint').text()).toBe('Choose your subject')
  })

  it('sets aria-invalid on trigger when error is present', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, error: 'Oops' } })
    expect(wrapper.find('.tv-select__trigger').attributes('aria-invalid')).toBe('true')
  })

  it('disables the trigger button when disabled=true', () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, disabled: true } })
    expect((wrapper.find('.tv-select__trigger').element as HTMLButtonElement).disabled).toBe(true)
  })

  it('does not open when disabled', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS, disabled: true } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    expect(wrapper.find('.tv-select__dropdown').exists()).toBe(false)
  })

  it('uses the provided id on the trigger and label', () => {
    const wrapper = mount(TVSelect, {
      props: { options: OPTIONS, id: 'lang-select', label: 'Language' },
    })
    expect(wrapper.find('.tv-select__trigger').attributes('id')).toBe('lang-select')
    expect(wrapper.find('label').attributes('id')).toBe('lang-select-label')
  })

  it('closes dropdown on Escape key', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    expect(wrapper.find('.tv-select__dropdown').exists()).toBe(true)
    await wrapper.find('.tv-select__trigger').trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('.tv-select__dropdown').exists()).toBe(false)
  })

  it('opens dropdown on Enter key', async () => {
    const wrapper = mount(TVSelect, { props: { options: OPTIONS } })
    await wrapper.find('.tv-select__trigger').trigger('keydown', { key: 'Enter' })
    expect(wrapper.find('.tv-select__dropdown').exists()).toBe(true)
  })

  it('shows empty state when options array is empty', async () => {
    const wrapper = mount(TVSelect, { props: { options: [] } })
    await wrapper.find('.tv-select__trigger').trigger('click')
    expect(wrapper.find('.tv-select__empty').exists()).toBe(true)
  })
})
