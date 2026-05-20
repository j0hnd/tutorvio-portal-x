import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TVButton from './TVButton.vue'

describe('TVButton', () => {
  it('renders the default slot as label', () => {
    const wrapper = mount(TVButton, { slots: { default: 'Click me' } })
    expect(wrapper.find('.tv-btn__label').text()).toBe('Click me')
  })

  it('applies the correct variant class', async () => {
    for (const variant of ['primary', 'secondary', 'ghost', 'danger', 'success'] as const) {
      const wrapper = mount(TVButton, { props: { variant } })
      expect(wrapper.classes()).toContain(`tv-btn--${variant}`)
    }
  })

  it('applies the correct size class', async () => {
    for (const size of ['sm', 'md', 'lg'] as const) {
      const wrapper = mount(TVButton, { props: { size } })
      expect(wrapper.classes()).toContain(`tv-btn--${size}`)
    }
  })

  it('defaults to variant=primary and size=md', () => {
    const wrapper = mount(TVButton)
    expect(wrapper.classes()).toContain('tv-btn--primary')
    expect(wrapper.classes()).toContain('tv-btn--md')
  })

  it('renders spinner and adds loading class when loading=true', () => {
    const wrapper = mount(TVButton, { props: { loading: true } })
    expect(wrapper.find('.tv-btn__spinner').exists()).toBe(true)
    expect(wrapper.classes()).toContain('tv-btn--loading')
  })

  it('sets aria-busy when loading', () => {
    const wrapper = mount(TVButton, { props: { loading: true } })
    expect(wrapper.attributes('aria-busy')).toBe('true')
  })

  it('is disabled when disabled=true', () => {
    const wrapper = mount(TVButton, { props: { disabled: true } })
    expect((wrapper.element as HTMLButtonElement).disabled).toBe(true)
  })

  it('is disabled when loading=true', () => {
    const wrapper = mount(TVButton, { props: { loading: true } })
    expect((wrapper.element as HTMLButtonElement).disabled).toBe(true)
  })

  it('sets aria-label when iconOnly=true', () => {
    const wrapper = mount(TVButton, {
      props: { iconOnly: true, ariaLabel: 'Close dialog' },
      slots: { icon: '<svg />' },
    })
    expect(wrapper.attributes('aria-label')).toBe('Close dialog')
  })

  it('does not render label slot when iconOnly=true', () => {
    const wrapper = mount(TVButton, {
      props: { iconOnly: true, ariaLabel: 'Icon' },
      slots: { default: 'Label text', icon: '<svg />' },
    })
    expect(wrapper.find('.tv-btn__label').exists()).toBe(false)
  })

  it('renders the correct button type', () => {
    const wrapper = mount(TVButton, { props: { type: 'submit' } })
    expect(wrapper.attributes('type')).toBe('submit')
  })

  it('defaults to type=button', () => {
    const wrapper = mount(TVButton)
    expect(wrapper.attributes('type')).toBe('button')
  })

  it('renders icon-start slot when provided', () => {
    const wrapper = mount(TVButton, {
      slots: { default: 'Save', icon: '<svg data-testid="icon" />' },
    })
    expect(wrapper.find('.tv-btn__icon--start').exists()).toBe(true)
  })
})
