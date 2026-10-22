/**
 * Admin area bundle.
 *
 * Loads Bootstrap's behaviours and AdminLTE's plugins, then wires the few
 * things the data API does not cover. AdminLTE's own plugins (PushMenu,
 * Treeview, CardWidget, FullScreen) bind themselves from `data-lte-*`
 * attributes, so no page should ever need its own script tag for them.
 */
import 'bootstrap'
import 'admin-lte'

import { initAreaHeader } from './modules/area-header.js'
import { initColorMode } from './modules/color-mode.js'
import { initConfirm } from './modules/confirm.js'
import { initDeviceTimezone } from './modules/device-timezone.js'
import { initInlineEdit } from './modules/inline-edit.js'
import { initNumericInputs } from './modules/numeric-input.js'
import { initOverlays } from './modules/overlays.js'
import { initPrint } from './modules/print.js'
import { initSearchSelects } from './modules/search-select.js'
import { initSearchPalette } from './modules/search-palette.js'
import { initSearchPickers } from './modules/search-picker.js'
import { initSearchText } from './modules/search-text.js'
import { initShortcuts } from './modules/shortcuts.js'
import { initSidebarScrollbars } from './modules/sidebar.js'
import { initVoiceRecorders } from './modules/voice-recorder.js'

// Before the first Livewire request, not on DOMContentLoaded.
initAreaHeader()

// Listeners on the document and window, which outlive any one page's markup.
initConfirm()
initInlineEdit()
initNumericInputs()
initOverlays()
initPrint()
initSearchPalette()
initSearchPickers()
initSearchSelects()
initSearchText()
initShortcuts()
initVoiceRecorders()

document.addEventListener('DOMContentLoaded', () => {
    initColorMode()
    initDeviceTimezone()
    initSidebarScrollbars()
})
