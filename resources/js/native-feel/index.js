/**
 * Native Feel Enhancement Layer
 *
 * Makes the PWA feel more native with:
 * - Haptic feedback on interactions
 * - Instant touch visual feedback
 * - Pull-to-refresh gesture
 * - Swipe back navigation
 * - Swipe-to-dismiss modals
 * - Proper back button handling
 */

import { initHaptics, haptics } from './haptics'
import { initTouchFeedback, destroyTouchFeedback } from './touch-feedback'
import { initPullToRefresh, destroyPullToRefresh } from './pull-to-refresh'
import { initSwipeGestures, destroySwipeGestures } from './swipe-gestures'
import { initNavigation, destroyNavigation } from './navigation'

let isInitialized = false

/**
 * Check if we should enable native feel features
 */
function shouldEnable() {
  // Only on touch devices
  if (!('ontouchstart' in window)) return false

  // Respect reduced motion preference for animations
  // But still allow haptics
  return true
}

/**
 * Initialize all native feel modules
 */
export function initNativeFeel() {
  if (isInitialized) return
  if (!shouldEnable()) return

  initHaptics()
  initTouchFeedback()
  initPullToRefresh()
  initSwipeGestures()
  initNavigation()

  isInitialized = true

  // Log for debugging
  if (process.env.NODE_ENV === 'development') {
    console.log('[Native Feel] Initialized')
    console.log('[Native Feel] Haptics supported:', haptics.isSupported())
  }
}

/**
 * Destroy all native feel modules (for cleanup/hot reload)
 */
export function destroyNativeFeel() {
  destroyTouchFeedback()
  destroyPullToRefresh()
  destroySwipeGestures()
  destroyNavigation()

  isInitialized = false
}

/**
 * Reinitialize (for Livewire navigation)
 */
export function reinitNativeFeel() {
  // Most modules auto-handle this, but we can add specific reinit logic here if needed
}

// Export haptics for manual use
export { haptics }

// Auto-initialize on DOMContentLoaded
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNativeFeel)
  } else {
    initNativeFeel()
  }

  // Re-initialize after Livewire navigations
  document.addEventListener('livewire:navigated', reinitNativeFeel)
}
