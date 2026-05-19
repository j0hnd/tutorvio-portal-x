import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TVBadge from './TVBadge.vue'
import type { BadgeVariant } from '@/types'

const ALL_VARIANTS: BadgeVariant[] = [
  'scheduled', 'completed', 'cancelled', 'missed', 'trial',
  'active', 'pending', 'warning', 'info', 'neutral',
]

describe('TVBadge', () => {
  it('renders the default slot text', () => {
    const wrapper = mount(TVBadge, { slots: { default: 'Scheduled' } })
    expect(wrapper.text()).toContain('Scheduled')
  })

  it('renders the label prop when no slot provided', () => {
    const wrapper = mount(TVBadge, { props: { label: 'Completed' } })
    expect(wrapper.text()).toContain('Completed')
  })

  it('applies the correct variant class for all variants', () => {
    for (const variant of ALL_VARIANTS) {
      const wrapper = mount(TVBadge, { props: { variant } })
      expect(wrapper.classes()).toContain(`tv-badge--${variant}`)
    }
  })

  it('defaults to variant=neutral', () => {
    const wrapper = mount(TVBadge)
    expect(wrapper.classes()).toContain('tv-badge--neutral')
  })

  it('renders dot indicator when dot=true', () => {
    const wrapper = mount(TVBadge, { props: { dot: true } })
    expect(wrapper.find('.tv-badge__dot').exists()).toBe(true)
  })

  it('does not render dot when dot=false', () => {
    const wrapper = mount(TVBadge, { props: { dot: false } })
    expect(wrapper.find('.tv-badge__dot').exists()).toBe(false)
  })

  it('sets aria-label when provided', () => {
    const wrapper = mount(TVBadge, { props: { ariaLabel: 'Status: completed' } })
    expect(wrapper.attributes('aria-label')).toBe('Status: completed')
  })

  it('adds the dot class when dot=true', () => {
    const wrapper = mount(TVBadge, { props: { dot: true } })
    expect(wrapper.classes()).toContain('tv-badge--dot')
  })
})
