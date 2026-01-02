/**
 * Swipe Gestures Module
 *
 * Native-style swipe gestures:
 * - Edge swipe from left to go back
 * - Swipe down on modals to dismiss
 */

import { haptics } from './haptics'

const EDGE_WIDTH = 25 // px from edge to detect edge swipe
const SWIPE_THRESHOLD = 0.35 // % of screen width to trigger back
const VELOCITY_THRESHOLD = 500 // px/s - fast swipe triggers even below threshold
const MODAL_DISMISS_THRESHOLD = 0.25 // % of modal height to dismiss

let isInitialized = false
let swipeState = null
let overlay = null

/**
 * Create the swipe overlay element
 */
function createOverlay() {
  if (overlay) return overlay

  overlay = document.createElement('div')
  overlay.className = 'swipe-back-overlay'
  document.body.appendChild(overlay)
  return overlay
}

/**
 * Detect if touch started at left edge
 */
function isEdgeSwipe(touchX) {
  return touchX <= EDGE_WIDTH
}

/**
 * Find the closest modal/slideover element
 */
function findModal(element) {
  return element.closest('[role="dialog"], .fi-modal, .fi-slideover, [x-show]')
}

/**
 * Check if element is a modal header/drag handle
 */
function isModalDragArea(element) {
  return element.closest('.fi-modal-header, .fi-slideover-header, [data-drag-handle]')
}

/**
 * Handle touch start for swipe gestures
 */
function handleTouchStart(e) {
  const touch = e.touches[0]
  const startX = touch.clientX
  const startY = touch.clientY
  const startTime = Date.now()

  // Check for edge swipe (back navigation)
  if (isEdgeSwipe(startX)) {
    swipeState = {
      type: 'edge-back',
      startX,
      startY,
      startTime,
      currentX: startX,
      currentY: startY,
    }
    return
  }

  // Check for modal swipe-to-dismiss
  const modal = findModal(e.target)
  if (modal && isModalDragArea(e.target)) {
    swipeState = {
      type: 'modal-dismiss',
      modal,
      startX,
      startY,
      startTime,
      currentX: startX,
      currentY: startY,
      modalHeight: modal.offsetHeight,
    }
    modal.classList.add('dragging')
    return
  }

  swipeState = null
}

/**
 * Handle touch move for swipe gestures
 */
function handleTouchMove(e) {
  if (!swipeState) return

  const touch = e.touches[0]
  swipeState.currentX = touch.clientX
  swipeState.currentY = touch.clientY

  if (swipeState.type === 'edge-back') {
    handleEdgeSwipeMove(e)
  } else if (swipeState.type === 'modal-dismiss') {
    handleModalSwipeMove(e)
  }
}

/**
 * Handle edge swipe movement
 */
function handleEdgeSwipeMove(e) {
  const deltaX = swipeState.currentX - swipeState.startX
  const deltaY = Math.abs(swipeState.currentY - swipeState.startY)

  // Cancel if swiping vertically (scrolling)
  if (deltaY > Math.abs(deltaX) * 1.5) {
    swipeState = null
    hideOverlay()
    return
  }

  // Only track rightward swipes
  if (deltaX <= 0) {
    hideOverlay()
    return
  }

  const progress = Math.min(deltaX / (window.innerWidth * SWIPE_THRESHOLD), 1)
  showOverlay(progress)

  // Prevent scrolling while swiping
  if (deltaX > 10) {
    e.preventDefault()
  }
}

/**
 * Handle modal swipe movement
 */
function handleModalSwipeMove(e) {
  const deltaY = swipeState.currentY - swipeState.startY

  // Only track downward swipes
  if (deltaY <= 0) return

  const progress = deltaY / swipeState.modalHeight
  const opacity = 1 - (progress * 0.5)

  swipeState.modal.style.transform = `translateY(${deltaY}px)`
  swipeState.modal.style.opacity = opacity

  // Prevent scrolling
  e.preventDefault()
}

/**
 * Handle touch end for swipe gestures
 */
function handleTouchEnd(e) {
  if (!swipeState) return

  const deltaTime = Date.now() - swipeState.startTime

  if (swipeState.type === 'edge-back') {
    handleEdgeSwipeEnd(deltaTime)
  } else if (swipeState.type === 'modal-dismiss') {
    handleModalSwipeEnd(deltaTime)
  }

  swipeState = null
}

/**
 * Handle edge swipe end
 */
function handleEdgeSwipeEnd(deltaTime) {
  const deltaX = swipeState.currentX - swipeState.startX
  const velocity = deltaX / deltaTime * 1000 // px/s

  const thresholdMet = deltaX >= window.innerWidth * SWIPE_THRESHOLD
  const fastSwipe = velocity >= VELOCITY_THRESHOLD && deltaX > 50

  if (thresholdMet || fastSwipe) {
    haptics.medium()
    // Navigate back
    if (window.history.length > 1) {
      window.history.back()
    }
  }

  hideOverlay()
}

/**
 * Handle modal swipe end
 */
function handleModalSwipeEnd(deltaTime) {
  const { modal, modalHeight, startY, currentY } = swipeState
  const deltaY = currentY - startY
  const velocity = deltaY / deltaTime * 1000

  modal.classList.remove('dragging')

  const thresholdMet = deltaY >= modalHeight * MODAL_DISMISS_THRESHOLD
  const fastSwipe = velocity >= VELOCITY_THRESHOLD && deltaY > 30

  if (thresholdMet || fastSwipe) {
    haptics.medium()
    // Dismiss modal
    dismissModal(modal)
  } else {
    // Spring back
    modal.style.transform = ''
    modal.style.opacity = ''
  }
}

/**
 * Dismiss a modal by clicking its close button or triggering Alpine
 */
function dismissModal(modal) {
  // Animate out
  modal.style.transition = 'transform 0.2s ease-out, opacity 0.2s ease-out'
  modal.style.transform = 'translateY(100%)'
  modal.style.opacity = '0'

  // Try to find and click close button
  const closeBtn = modal.querySelector('.fi-modal-close-btn, [x-on\\:click*="close"], button[aria-label="Close"]')
  if (closeBtn) {
    setTimeout(() => closeBtn.click(), 150)
    return
  }

  // Try Alpine x-show toggle
  const backdrop = modal.closest('[x-data]')
  if (backdrop && backdrop.__x) {
    setTimeout(() => {
      // Reset styles in case modal is reopened
      modal.style.transform = ''
      modal.style.opacity = ''
      modal.style.transition = ''
    }, 200)
  }
}

/**
 * Show the swipe overlay
 */
function showOverlay(progress) {
  if (!overlay) createOverlay()

  overlay.classList.add('active')
  overlay.style.setProperty('--swipe-progress', progress)
}

/**
 * Hide the swipe overlay
 */
function hideOverlay() {
  if (!overlay) return
  overlay.classList.remove('active')
}

/**
 * Initialize swipe gestures
 */
export function initSwipeGestures() {
  if (isInitialized) return

  // Only enable on touch devices
  if (!('ontouchstart' in window)) return

  createOverlay()

  document.addEventListener('touchstart', handleTouchStart, { passive: true })
  document.addEventListener('touchmove', handleTouchMove, { passive: false })
  document.addEventListener('touchend', handleTouchEnd, { passive: true })
  document.addEventListener('touchcancel', handleTouchEnd, { passive: true })

  isInitialized = true
}

/**
 * Cleanup
 */
export function destroySwipeGestures() {
  document.removeEventListener('touchstart', handleTouchStart)
  document.removeEventListener('touchmove', handleTouchMove)
  document.removeEventListener('touchend', handleTouchEnd)
  document.removeEventListener('touchcancel', handleTouchEnd)

  if (overlay && overlay.parentNode) {
    overlay.parentNode.removeChild(overlay)
    overlay = null
  }

  isInitialized = false
}
