import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TVCard from './TVCard.vue'

describe('TVCard', () => {
  it('renders default slot content in body', () => {
    const wrapper = mount(TVCard, { slots: { default: '<p>Card body</p>' } })
    expect(wrapper.find('.tv-card__body').html()).toContain('Card body')
  })

  it('applies the correct variant class', async () => {
    for (const variant of ['default', 'glass', 'outlined', 'elevated'] as const) {
      const wrapper = mount(TVCard, { props: { variant } })
      expect(wrapper.classes()).toContain(`tv-card--${variant}`)
    }
  })

  it('defaults to variant=default and padded=true', () => {
    const wrapper = mount(TVCard)
    expect(wrapper.classes()).toContain('tv-card--default')
    expect(wrapper.classes()).toContain('tv-card--padded')
  })

  it('adds hoverable class when hoverable=true', () => {
    const wrapper = mount(TVCard, { props: { hoverable: true } })
    expect(wrapper.classes()).toContain('tv-card--hoverable')
  })

  it('does not add padded class when padded=false', () => {
    const wrapper = mount(TVCard, { props: { padded: false } })
    expect(wrapper.classes()).not.toContain('tv-card--padded')
  })

  it('renders header slot when provided', () => {
    const wrapper = mount(TVCard, { slots: { header: '<span>Header</span>' } })
    expect(wrapper.find('.tv-card__header').exists()).toBe(true)
    expect(wrapper.find('.tv-card__header').html()).toContain('Header')
  })

  it('does not render header when header slot is absent', () => {
    const wrapper = mount(TVCard)
    expect(wrapper.find('.tv-card__header').exists()).toBe(false)
  })

  it('renders footer slot when provided', () => {
    const wrapper = mount(TVCard, { slots: { footer: '<button>Save</button>' } })
    expect(wrapper.find('.tv-card__footer').exists()).toBe(true)
  })

  it('renders as the specified tag', () => {
    const wrapper = mount(TVCard, { props: { tag: 'article' } })
    expect(wrapper.element.tagName.toLowerCase()).toBe('article')
  })

  it('defaults to div tag', () => {
    const wrapper = mount(TVCard)
    expect(wrapper.element.tagName.toLowerCase()).toBe('div')
  })
})
