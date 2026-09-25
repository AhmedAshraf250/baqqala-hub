/**
 * Replaces the sidebar's native scrollbar with OverlayScrollbars on desktop.
 *
 * Skipped below AdminLTE's sidebar breakpoint, where the rail is off-canvas and
 * the platform's own touch scrolling is the better experience.
 */
import { OverlayScrollbars } from 'overlayscrollbars'

const DESKTOP_BREAKPOINT = 992

export const initSidebarScrollbars = () => {
    const wrapper = document.querySelector('.sidebar-wrapper')

    if (!wrapper || window.innerWidth <= DESKTOP_BREAKPOINT) {
        return
    }

    OverlayScrollbars(wrapper, {
        scrollbars: {
            theme: 'os-theme-light',
            autoHide: 'leave',
            clickScroll: true,
        },
    })
}
