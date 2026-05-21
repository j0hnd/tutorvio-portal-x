import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TVInput from './TVInput.vue'

describe('TVInput', () => {
  it('renders the label when provided', () => {
    const wrapper = mount(TVInput, { props: { label: 'Email' } })
    expect(wrapper.find('.tv-input__label').text()).toContain('Email')
  })

  it('does not render label when not provided', () => {
    const wrapper = mount(TVInput)
    expect(wrapper.find('.tv-input__label').exists()).toBe(false)
  })

  it('shows required asterisk when required=true', () => {
    const wrapper = mount(TVInput, { props: { label: 'Email', required: true } })
    expect(wrapper.find('.tv-input__required').exists()).toBe(true)
  })

  it('sets the input value from modelValue', () => {
    const wrapper = mount(TVInput, { props: { modelValue: 'hello@test.com' } })
    const input = wrapper.find('input')
    expect((input.element as HTMLInputElement).value).toBe('hello@test.com')
  })

  it('emits update:modelValue on input', async () => {
    const wrapper = mount(TVInput)
    const input = wrapper.find('input')
    await input.setValue('new value')
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['new value'])
  })

  it('emits blur event on blur', async () => {
    const wrapper = mount(TVInput)
    await wrapper.find('input').trigger('blur')
    expect(wrapper.emitted('blur')).toBeTruthy()
  })

  it('shows error message when error prop is set', () => {
    const wrapper = mount(TVInput, { props: { error: 'Email is required' } })
    expect(wrapper.find('.tv-input__message--error').text()).toBe('Email is required')
  })

  it('shows hint message when hint prop is set and no error', () => {
    const wrapper = mount(TVInput, { props: { hint: 'We will never spam you' } })
    expect(wrapper.find('.tv-input__message--hint').text()).toBe('We will never spam you')
  })

  it('error takes precedence over hint', () => {
    const wrapper = mount(TVInput, {
      props: { hint: 'Hint text', error: 'Error text' },
    })
    expect(wrapper.find('.tv-input__message--error').exists()).toBe(true)
    expect(wrapper.find('.tv-input__message--hint').exists()).toBe(false)
  })

  it('sets aria-invalid when error is present', () => {
    const wrapper = mount(TVInput, { props: { error: 'Required' } })
    expect(wrapper.find('input').attributes('aria-invalid')).toBe('true')
  })

  it('disables the input when disabled=true', () => {
    const wrapper = mount(TVInput, { props: { disabled: true } })
    expect((wrapper.find('input').element as HTMLInputElement).disabled).toBe(true)
  })

  it('applies error wrapper class when error is set', () => {
    const wrapper = mount(TVInput, { props: { error: 'Oops' } })
    expect(wrapper.classes()).toContain('tv-input-wrapper--error')
  })

  it('uses the provided id on the input and label', () => {
    const wrapper = mount(TVInput, { props: { id: 'email-field', label: 'Email' } })
    expect(wrapper.find('input').attributes('id')).toBe('email-field')
    expect(wrapper.find('label').attributes('for')).toBe('email-field')
  })

  it('sets the input type from the type prop', () => {
    const wrapper = mount(TVInput, { props: { type: 'password' } })
    expect(wrapper.find('input').attributes('type')).toBe('password')
  })
})
