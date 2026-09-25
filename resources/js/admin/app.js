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

import { initColorMode } from './modules/color-mode.js'
import { initSidebarScrollbars } from './modules/sidebar.js'

document.addEventListener('DOMContentLoaded', () => {
    initColorMode()
    initSidebarScrollbars()
})
