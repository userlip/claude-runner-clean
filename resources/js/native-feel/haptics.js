/**
 * Haptic Feedback Module
 *
 * Provides native-like vibration feedback for touch interactions.
 * Gracefully degrades on devices that don't support the Vibration API.
 */

const supportsHaptics = 'vibrate' in navigator
const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches

function vibrate(pattern) {
  if (!supportsHaptics || prefersReducedMotion()) return false

  try {
    return navigator.vibrate(pattern)
  } catch (e) {
    return false
  }
}

export const haptics = {
  /**
   * Light tap - for button presses, toggles, selections
   */
  light: () => vibrate(10),

  /**
   * Medium tap - for confirmations, important selections
   */
  medium: () => vibrate(20),

  /**
   * Heavy tap - for errors, warnings, destructive actions
   */
  heavy: () => vibrate(30),

  /**
   * Success pattern - for successful operations
   */
  success: () => vibrate([10, 50, 10]),

  /**
   * Error pattern - for failed operations
   */
  error: () => vibrate([30, 50, 30, 50, 30]),

  /**
   * Notification pattern - for alerts
   */
  notify: () => vibrate([10, 100, 10, 100, 10]),

  /**
   * Check if haptics are supported
   */
  isSupported: () => supportsHaptics && !prefersReducedMotion(),
}

/**
 * Interactive elements that should receive haptic feedback
 */
const HAPTIC_SELECTORS = [
  'button',
  'a[href]',
  '[role="button"]',
  '[wire\\:click]',
  '[x-on\\:click]',
  'input[type="checkbox"]',
  'input[type="radio"]',
  'input[type="submit"]',
  '.fi-btn',
  '.fi-icon-btn',
  '.fi-dropdown-trigger',
  '.fi-modal-close-btn',
  '.fi-sidebar-item',
  '.fi-tabs-item',
].join(', ')

/**
 * Elements that should have heavy haptic feedback
 */
const HEAVY_HAPTIC_SELECTORS = [
  '[wire\\:confirm]',
  '[data-haptic="heavy"]',
  '.fi-modal-close-btn',
].join(', ')

let isInitialized = false

/**
 * Initialize haptic feedback on interactive elements
 */
export function initHaptics() {
  if (isInitialized || !supportsHaptics) return

  document.addEventListener('click', (e) => {
    const target = e.target.closest(HAPTIC_SELECTORS)
    if (!target) return

    // Check for heavy haptic elements
    if (target.matches(HEAVY_HAPTIC_SELECTORS)) {
      haptics.medium()
    } else {
      haptics.light()
    }
  }, { passive: true })

  // Livewire action feedback
  if (typeof Livewire !== 'undefined') {
    document.addEventListener('livewire:commit', () => {
      // Slight delay to feel like response to action
      setTimeout(() => haptics.light(), 50)
    })
  }

  isInitialized = true
}

export default haptics
