# Native PWA Feel Enhancement

## Overview

Enhance the PWA to feel more native by adding haptic feedback, instant touch responses, smoother animations, pull-to-refresh, swipe gestures, and proper back button handling.

## Goals

1. **Interactions feel immediate** - haptic feedback, instant visual response on touch
2. **Navigation feels native** - pull-to-refresh, swipe gestures, proper back button behavior
3. **Non-invasive** - progressive enhancement layer that works with existing Livewire/Filament

## Architecture

### New Files

```
resources/js/native-feel/
├── index.js           # Main entry, initializes all modules
├── haptics.js         # Vibration API wrapper
├── touch-feedback.js  # Instant touch visual feedback
├── pull-to-refresh.js # Pull-to-refresh gesture
├── swipe-gestures.js  # Edge swipe back, swipe-to-dismiss
└── navigation.js      # History state management for back button
```

### CSS Additions

Add to `resources/css/filament/chat.css`:
- Touch feedback states (scale transforms)
- Pull-to-refresh indicator styles
- Swipe gesture transition styles

---

## Module Specifications

### 1. Haptics (`haptics.js`)

Wrapper around the Vibration API with sensible defaults.

```js
const haptics = {
  light: () => vibrate(10),      // Button taps, toggles
  medium: () => vibrate(20),     // Confirmations, selections
  heavy: () => vibrate(30),      // Errors, warnings
  success: () => vibrate([10, 50, 10]), // Success pattern
  error: () => vibrate([30, 50, 30]),   // Error pattern
}
```

**Auto-attachment:**
- Listen for `click` events on buttons, links, interactive elements
- Listen for Livewire events to trigger on actions
- Respect `prefers-reduced-motion` media query

**Feature detection:**
```js
const supportsHaptics = 'vibrate' in navigator
```

### 2. Touch Feedback (`touch-feedback.js`)

Instant visual feedback on touch, not on release.

**Behavior:**
- On `touchstart`: add `.touch-active` class, apply subtle scale (0.97)
- On `touchend`/`touchcancel`: remove class after 100ms
- Prevent feedback on scroll (detect movement threshold of 10px)

**Target elements:**
- `button`, `a`, `[role="button"]`
- `[wire:click]`, `[x-on:click]`
- `.btn`, `.fi-btn`, other Filament button classes

**CSS:**
```css
.touch-active {
  transform: scale(0.97);
  transition: transform 0.1s ease-out;
}
```

### 3. Pull-to-Refresh (`pull-to-refresh.js`)

Native-style pull-to-refresh for scrollable content.

**Configuration:**
```js
{
  threshold: 80,           // px to pull before triggering
  maxPull: 120,            // maximum pull distance
  resistance: 0.4,         // resistance factor (0-1)
  refreshSelector: null,   // optional: specific element to refresh
}
```

**Visual indicator:**
- Spinner element that appears as user pulls
- Opacity and rotation tied to pull distance
- Snaps to loading state when threshold reached

**Behavior:**
1. Detect touchstart at top of scrollable area (scrollTop <= 0)
2. Track touchmove, apply resistance curve
3. Show pull indicator with progress
4. On threshold: haptic pulse, trigger refresh
5. On release below threshold: spring back animation

**Refresh action:**
- If Livewire component: `$wire.$refresh()`
- Otherwise: `window.location.reload()`

**Target areas:**
- Main content container
- Chat message list
- Session/task lists

### 4. Swipe Gestures (`swipe-gestures.js`)

#### Edge Swipe Back

Swipe from left edge to navigate back.

**Detection:**
- Touch starts within 20px of left edge
- Horizontal movement > vertical movement
- Minimum swipe distance: 40% of screen width

**Visual feedback:**
- Shadow/overlay appears as user swipes
- Previous page preview slides in from left (if using view transitions)
- Current page slides right following finger

**Completion:**
- If threshold reached: navigate back with haptic
- If not: spring back animation

#### Swipe to Dismiss Modals

Swipe down on modals/slideovers to dismiss.

**Detection:**
- Touch starts on modal header or drag handle
- Vertical downward movement

**Behavior:**
- Modal follows finger position
- Opacity decreases as modal moves down
- Velocity-aware: fast swipe dismisses even below threshold
- Threshold: 30% of modal height or velocity > 500px/s

### 5. Navigation (`navigation.js`)

Proper back button handling for modals and overlays.

**History state management:**
```js
// When modal opens
history.pushState({ modal: 'settings' }, '')

// Listen for popstate
window.addEventListener('popstate', (e) => {
  if (e.state?.modal) {
    closeModal(e.state.modal)
  }
})
```

**Tracked UI states:**
- Modals (`[x-show]` with modal role)
- Slideovers
- Dropdown menus
- Mobile sidebar

**Behavior:**
- Opening overlay pushes state
- Back button/gesture closes overlay instead of navigating
- Closing overlay manually also updates history

---

## Integration

### Initialization

In `resources/js/app.js`:
```js
import { initNativeFeel } from './native-feel'

document.addEventListener('DOMContentLoaded', () => {
  initNativeFeel()
})

// Re-initialize after Livewire updates
document.addEventListener('livewire:navigated', () => {
  initNativeFeel()
})
```

### Livewire Integration

Listen for Livewire lifecycle events:
```js
Livewire.hook('commit', ({ succeed }) => {
  succeed(() => {
    haptics.light()
  })
})
```

### Feature Detection

All features should gracefully degrade:
```js
const isTouchDevice = 'ontouchstart' in window
const supportsHaptics = 'vibrate' in navigator
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
```

---

## CSS Requirements

### Touch Feedback States

```css
/* Instant touch feedback */
.native-touch-target {
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
  user-select: none;
}

.native-touch-target.touch-active {
  transform: scale(0.97);
  opacity: 0.9;
}

/* Smooth transitions */
.native-touch-target {
  transition: transform 0.1s ease-out, opacity 0.1s ease-out;
  will-change: transform;
}
```

### Pull-to-Refresh Indicator

```css
.ptr-indicator {
  position: fixed;
  top: 0;
  left: 50%;
  transform: translateX(-50%) translateY(-100%);
  z-index: 9999;
  pointer-events: none;
}

.ptr-indicator.pulling {
  transform: translateX(-50%) translateY(var(--ptr-offset, 0));
}

.ptr-indicator.refreshing {
  transform: translateX(-50%) translateY(20px);
}

.ptr-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
}

.ptr-indicator.refreshing .ptr-spinner {
  animation: spin 0.8s linear infinite;
}
```

### Swipe Gesture Overlays

```css
.swipe-back-overlay {
  position: fixed;
  inset: 0;
  background: linear-gradient(to right, rgba(0,0,0,0.1), transparent);
  opacity: 0;
  pointer-events: none;
  z-index: 9998;
}

.swipe-back-overlay.active {
  opacity: var(--swipe-progress, 0);
}

.modal-swipe-dismiss {
  transition: transform 0.2s ease-out, opacity 0.2s ease-out;
}

.modal-swipe-dismiss.dragging {
  transition: none;
}
```

---

## Testing Considerations

- Test on real iOS and Android devices (haptics don't work in simulators)
- Test with `prefers-reduced-motion` enabled
- Test scroll vs swipe differentiation
- Test Livewire component updates don't break gesture handlers
- Test memory leaks (event listener cleanup)

---

## Rollout

1. Implement haptics and touch feedback first (lowest risk, highest impact)
2. Add pull-to-refresh for main content areas
3. Add swipe-to-dismiss for modals
4. Add edge swipe back navigation last (most complex)

Each can be feature-flagged if needed.
