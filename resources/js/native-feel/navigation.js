/**
 * Navigation Module
 *
 * Proper back button handling for modals, slideovers, and overlays.
 * Prevents accidental app exit when user meant to close an overlay.
 */

import { haptics } from './haptics'

let isInitialized = false
let overlayStack = []

/**
 * Modal/overlay selectors to track
 */
const OVERLAY_SELECTORS = [
  '.fi-modal',
  '.fi-slideover',
  '[role="dialog"]',
  '[x-show][x-transition]',
]

/**
 * Generate a unique ID for an overlay
 */
function getOverlayId(element) {
  if (element.id) return element.id
  if (element.getAttribute('wire:id')) return `wire-${element.getAttribute('wire:id')}`

  // Generate a random ID
  const id = `overlay-${Math.random().toString(36).substr(2, 9)}`
  element.dataset.overlayId = id
  return id
}

/**
 * Push an overlay onto the history stack
 */
function pushOverlay(element) {
  const id = getOverlayId(element)

  // Don't push if already in stack
  if (overlayStack.includes(id)) return

  overlayStack.push(id)

  history.pushState(
    { overlay: id, stackIndex: overlayStack.length },
    '',
    window.location.href
  )
}

/**
 * Remove an overlay from the stack (when closed manually)
 */
function removeOverlay(element) {
  const id = getOverlayId(element)
  const index = overlayStack.indexOf(id)

  if (index > -1) {
    overlayStack.splice(index, 1)
    // Go back in history to match our stack
    // But only if this overlay's state is current
    if (history.state?.overlay === id) {
      history.back()
    }
  }
}

/**
 * Find and close an overlay by ID
 */
function closeOverlayById(id) {
  // Try various methods to find and close the overlay
  const selectors = [
    `#${id}`,
    `[data-overlay-id="${id}"]`,
    `[wire\\:id="${id.replace('wire-', '')}"]`,
  ]

  for (const selector of selectors) {
    try {
      const element = document.querySelector(selector)
      if (element) {
        closeOverlay(element)
        return true
      }
    } catch (e) {
      // Invalid selector, continue
    }
  }

  // If we can't find the specific overlay, try to close the topmost one
  for (const selector of OVERLAY_SELECTORS) {
    const overlays = document.querySelectorAll(selector)
    for (const overlay of overlays) {
      if (isVisible(overlay)) {
        closeOverlay(overlay)
        return true
      }
    }
  }

  return false
}

/**
 * Check if an element is visible
 */
function isVisible(element) {
  if (!element) return false

  const style = window.getComputedStyle(element)
  return style.display !== 'none' &&
         style.visibility !== 'hidden' &&
         style.opacity !== '0'
}

/**
 * Close an overlay element
 */
function closeOverlay(element) {
  haptics.light()

  // Try clicking close button
  const closeBtn = element.querySelector(
    '.fi-modal-close-btn, .fi-slideover-close-btn, [x-on\\:click*="close"], button[aria-label="Close"]'
  )
  if (closeBtn) {
    closeBtn.click()
    return
  }

  // Try Livewire method
  if (element.hasAttribute('wire:id') && typeof Livewire !== 'undefined') {
    const componentId = element.getAttribute('wire:id')
    const component = Livewire.find(componentId)
    if (component && typeof component.close === 'function') {
      component.close()
      return
    }
  }

  // Try clicking backdrop
  const backdrop = element.previousElementSibling
  if (backdrop?.classList.contains('fi-modal-backdrop') ||
      backdrop?.classList.contains('fi-slideover-backdrop')) {
    backdrop.click()
  }
}

/**
 * Handle popstate (back button pressed)
 */
function handlePopstate(e) {
  const state = e.state

  // If we have an overlay in the previous state, close the current one
  if (state?.overlay) {
    const id = overlayStack[overlayStack.length - 1]
    if (id && id !== state.overlay) {
      overlayStack.pop()
      closeOverlayById(id)
    }
  } else if (overlayStack.length > 0) {
    // No overlay state but we have overlays open - close the topmost
    const id = overlayStack.pop()
    closeOverlayById(id)
  }
}

/**
 * Observe DOM for new modals/overlays
 */
function observeOverlays() {
  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      // Check added nodes
      for (const node of mutation.addedNodes) {
        if (node.nodeType !== Node.ELEMENT_NODE) continue

        for (const selector of OVERLAY_SELECTORS) {
          if (node.matches?.(selector) && isVisible(node)) {
            pushOverlay(node)
          }
          // Also check children
          const children = node.querySelectorAll?.(selector)
          children?.forEach(child => {
            if (isVisible(child)) pushOverlay(child)
          })
        }
      }

      // Check removed nodes
      for (const node of mutation.removedNodes) {
        if (node.nodeType !== Node.ELEMENT_NODE) continue

        for (const selector of OVERLAY_SELECTORS) {
          if (node.matches?.(selector)) {
            removeOverlay(node)
          }
          const children = node.querySelectorAll?.(selector)
          children?.forEach(child => removeOverlay(child))
        }
      }

      // Check attribute changes (for x-show toggling)
      if (mutation.type === 'attributes') {
        const node = mutation.target
        for (const selector of OVERLAY_SELECTORS) {
          if (node.matches?.(selector)) {
            if (isVisible(node)) {
              pushOverlay(node)
            } else {
              removeOverlay(node)
            }
          }
        }
      }
    }
  })

  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['style', 'class', 'hidden'],
  })

  return observer
}

let observer = null

/**
 * Initialize navigation handling
 */
export function initNavigation() {
  if (isInitialized) return

  window.addEventListener('popstate', handlePopstate)
  observer = observeOverlays()

  // Track any currently open overlays
  for (const selector of OVERLAY_SELECTORS) {
    document.querySelectorAll(selector).forEach(overlay => {
      if (isVisible(overlay)) {
        pushOverlay(overlay)
      }
    })
  }

  isInitialized = true
}

/**
 * Cleanup
 */
export function destroyNavigation() {
  window.removeEventListener('popstate', handlePopstate)
  if (observer) {
    observer.disconnect()
    observer = null
  }
  overlayStack = []
  isInitialized = false
}
