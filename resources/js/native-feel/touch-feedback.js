/**
 * Touch Feedback Module
 *
 * Provides instant visual feedback on touch, not on release.
 * Makes interactions feel snappier and more native.
 */

const TOUCH_ACTIVE_CLASS = 'touch-active'
const MOVEMENT_THRESHOLD = 10 // px - if finger moves more than this, it's a scroll

/**
 * Interactive elements that should receive touch feedback
 */
const TOUCH_SELECTORS = [
  'button',
  'a[href]',
  '[role="button"]',
  '[wire\\:click]',
  '[x-on\\:click]',
  '.fi-btn',
  '.fi-icon-btn',
  '.fi-dropdown-trigger',
  '.fi-sidebar-item',
  '.fi-tabs-item',
  '.fi-ta-action',
  '.cursor-pointer',
].join(', ')

/**
 * Elements to exclude from touch feedback
 */
const EXCLUDE_SELECTORS = [
  'input',
  'textarea',
  'select',
  '[contenteditable]',
].join(', ')

let activeElement = null
let touchStartPos = { x: 0, y: 0 }
let isScrolling = false
let isInitialized = false

function handleTouchStart(e) {
  const target = e.target.closest(TOUCH_SELECTORS)
  if (!target || target.matches(EXCLUDE_SELECTORS)) return
  if (target.disabled || target.getAttribute('aria-disabled') === 'true') return

  activeElement = target
  touchStartPos = {
    x: e.touches[0].clientX,
    y: e.touches[0].clientY,
  }
  isScrolling = false

  // Add active class immediately for instant feedback
  target.classList.add(TOUCH_ACTIVE_CLASS)
}

function handleTouchMove(e) {
  if (!activeElement) return

  const touch = e.touches[0]
  const deltaX = Math.abs(touch.clientX - touchStartPos.x)
  const deltaY = Math.abs(touch.clientY - touchStartPos.y)

  // If finger has moved significantly, this is a scroll, not a tap
  if (deltaX > MOVEMENT_THRESHOLD || deltaY > MOVEMENT_THRESHOLD) {
    isScrolling = true
    activeElement.classList.remove(TOUCH_ACTIVE_CLASS)
    activeElement = null
  }
}

function handleTouchEnd() {
  if (!activeElement) return

  // Small delay before removing class for visual feedback
  const element = activeElement
  setTimeout(() => {
    element.classList.remove(TOUCH_ACTIVE_CLASS)
  }, 100)

  activeElement = null
  isScrolling = false
}

function handleTouchCancel() {
  if (activeElement) {
    activeElement.classList.remove(TOUCH_ACTIVE_CLASS)
    activeElement = null
  }
  isScrolling = false
}

/**
 * Initialize touch feedback
 */
export function initTouchFeedback() {
  if (isInitialized) return

  // Only enable on touch devices
  if (!('ontouchstart' in window)) return

  document.addEventListener('touchstart', handleTouchStart, { passive: true })
  document.addEventListener('touchmove', handleTouchMove, { passive: true })
  document.addEventListener('touchend', handleTouchEnd, { passive: true })
  document.addEventListener('touchcancel', handleTouchCancel, { passive: true })

  isInitialized = true
}

/**
 * Cleanup (for hot reloading)
 */
export function destroyTouchFeedback() {
  document.removeEventListener('touchstart', handleTouchStart)
  document.removeEventListener('touchmove', handleTouchMove)
  document.removeEventListener('touchend', handleTouchEnd)
  document.removeEventListener('touchcancel', handleTouchCancel)
  isInitialized = false
}
