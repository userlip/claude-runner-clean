/**
 * Pull-to-Refresh Module
 *
 * Native-style pull-to-refresh gesture for scrollable content areas.
 */

import { haptics } from './haptics'

const DEFAULT_OPTIONS = {
  threshold: 80,        // px to pull before triggering
  maxPull: 140,         // maximum pull distance
  resistance: 0.4,      // resistance factor (0-1)
  refreshTimeout: 10000, // max time to wait for refresh
}

let indicator = null
let isRefreshing = false
let isPulling = false
let startY = 0
let currentY = 0
let isInitialized = false

/**
 * Create the pull-to-refresh indicator element
 */
function createIndicator() {
  if (indicator) return indicator

  indicator = document.createElement('div')
  indicator.className = 'ptr-indicator'
  indicator.innerHTML = `
    <div class="ptr-content">
      <div class="ptr-spinner"></div>
      <div class="ptr-text">Pull to refresh</div>
    </div>
  `
  document.body.appendChild(indicator)
  return indicator
}

/**
 * Check if we're at the top of a scrollable area
 */
function isAtTop(element) {
  // Check the element and its parents
  let current = element
  while (current && current !== document.body) {
    if (current.scrollTop > 0) return false
    current = current.parentElement
  }
  return window.scrollY <= 0
}

/**
 * Find the Livewire component to refresh
 */
function findLivewireComponent(element) {
  let current = element
  while (current && current !== document.body) {
    if (current.hasAttribute('wire:id')) {
      return current
    }
    current = current.parentElement
  }
  return null
}

/**
 * Handle touch start
 */
function handleTouchStart(e) {
  if (isRefreshing) return
  if (!isAtTop(e.target)) return

  startY = e.touches[0].clientY
  currentY = startY
  isPulling = true
}

/**
 * Handle touch move
 */
function handleTouchMove(e) {
  if (!isPulling || isRefreshing) return

  currentY = e.touches[0].clientY
  const pullDistance = currentY - startY

  // Only activate on downward pull
  if (pullDistance <= 0) {
    isPulling = false
    hideIndicator()
    return
  }

  // Check we're still at top (user might have scrolled)
  if (!isAtTop(e.target)) {
    isPulling = false
    hideIndicator()
    return
  }

  // Apply resistance curve
  const resistedPull = Math.min(
    pullDistance * DEFAULT_OPTIONS.resistance,
    DEFAULT_OPTIONS.maxPull
  )

  updateIndicator(resistedPull)

  // Prevent scroll when pulling
  if (resistedPull > 10) {
    e.preventDefault()
  }
}

/**
 * Handle touch end
 */
function handleTouchEnd(e) {
  if (!isPulling) return

  const pullDistance = (currentY - startY) * DEFAULT_OPTIONS.resistance

  if (pullDistance >= DEFAULT_OPTIONS.threshold && !isRefreshing) {
    // Trigger refresh
    triggerRefresh(e.target)
  } else {
    // Spring back
    hideIndicator()
  }

  isPulling = false
  startY = 0
  currentY = 0
}

/**
 * Update the indicator position and state
 */
function updateIndicator(pullDistance) {
  if (!indicator) createIndicator()

  const progress = Math.min(pullDistance / DEFAULT_OPTIONS.threshold, 1)
  const rotation = progress * 360

  indicator.classList.add('pulling')
  indicator.classList.remove('refreshing')
  indicator.style.setProperty('--ptr-offset', `${pullDistance}px`)
  indicator.style.setProperty('--ptr-rotation', `${rotation}deg`)
  indicator.style.setProperty('--ptr-opacity', Math.min(progress, 1))

  const text = indicator.querySelector('.ptr-text')
  if (text) {
    text.textContent = progress >= 1 ? 'Release to refresh' : 'Pull to refresh'
  }

  // Haptic feedback when threshold is reached
  if (progress >= 1 && !indicator.dataset.hapticFired) {
    haptics.medium()
    indicator.dataset.hapticFired = 'true'
  } else if (progress < 1) {
    delete indicator.dataset.hapticFired
  }
}

/**
 * Hide the indicator with animation
 */
function hideIndicator() {
  if (!indicator) return

  indicator.classList.remove('pulling', 'refreshing')
  indicator.style.setProperty('--ptr-offset', '0px')
  delete indicator.dataset.hapticFired
}

/**
 * Trigger the refresh action
 */
async function triggerRefresh(targetElement) {
  if (isRefreshing) return

  isRefreshing = true

  if (!indicator) createIndicator()
  indicator.classList.remove('pulling')
  indicator.classList.add('refreshing')
  indicator.style.setProperty('--ptr-offset', '60px')

  const text = indicator.querySelector('.ptr-text')
  if (text) text.textContent = 'Refreshing...'

  haptics.success()

  try {
    // Try to refresh Livewire component first
    const lwElement = findLivewireComponent(targetElement)
    if (lwElement && typeof Livewire !== 'undefined') {
      const componentId = lwElement.getAttribute('wire:id')
      const component = Livewire.find(componentId)
      if (component) {
        await component.$refresh()
        haptics.light()
      }
    } else {
      // Fallback to page reload
      window.location.reload()
      return // Don't hide indicator, page is reloading
    }
  } catch (error) {
    console.error('Pull-to-refresh error:', error)
    haptics.error()
  }

  // Hide indicator after short delay
  setTimeout(() => {
    hideIndicator()
    isRefreshing = false
  }, 300)
}

/**
 * Initialize pull-to-refresh
 */
export function initPullToRefresh() {
  if (isInitialized) return

  // Only enable on touch devices
  if (!('ontouchstart' in window)) return

  createIndicator()

  document.addEventListener('touchstart', handleTouchStart, { passive: true })
  document.addEventListener('touchmove', handleTouchMove, { passive: false })
  document.addEventListener('touchend', handleTouchEnd, { passive: true })
  document.addEventListener('touchcancel', handleTouchEnd, { passive: true })

  isInitialized = true
}

/**
 * Cleanup
 */
export function destroyPullToRefresh() {
  document.removeEventListener('touchstart', handleTouchStart)
  document.removeEventListener('touchmove', handleTouchMove)
  document.removeEventListener('touchend', handleTouchEnd)
  document.removeEventListener('touchcancel', handleTouchEnd)

  if (indicator && indicator.parentNode) {
    indicator.parentNode.removeChild(indicator)
    indicator = null
  }

  isInitialized = false
}
