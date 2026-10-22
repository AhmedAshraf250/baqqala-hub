/**
 * Keyboard shortcuts a screen declares in its markup.
 *
 * - `data-shortcut-focus="Slash"` focuses the element; `data-shortcut-click="KeyN"`
 *   clicks it. Keys are named by `event.code` — where the key is, not what it
 *   types — so they work on an Arabic layout, where the slash key types «ظ».
 *   `data-shortcut-key="+"` clicks it on a character both layouts share: + and -.
 * - A list, `[data-row-list]`, of `[data-row]` rows: the arrow keys move between
 *   rows, and from a field that names the list in `data-row-list-input` into
 *   it. On a row, `+` and `-` press its `[data-row-key="+"]` and
 *   `[data-row-key="-"]`, Enter its `[data-row-key="Enter"]`.
 *
 * Nothing fires while a person types in a field — except the arrows from a
 * field that names a list — or while a dialog is open over the page.
 *
 * A person who does not want them turns them off in Settings → Appearance
 * (`[data-shortcuts-toggle]`): this browser remembers it, the head script
 * applies it as `data-shortcuts="off"` on the page before it is painted, the
 * hints that name keys (`.kbd-hint`) are hidden, and only the arrows and
 * Enter in a list still answer — the keys every list answers anyway.
 */
export function initShortcuts() {
    initToggle()

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
            return
        }

        const active = document.activeElement
        const row = active?.closest?.('[data-row]')

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            const list = row?.closest('[data-row-list]') ?? listNamedBy(active)

            if (list) {
                event.preventDefault()
                move(list, row, event.key === 'ArrowDown' ? 1 : -1)
            }

            return
        }

        if (isTyping(active) || overlayOpen()) {
            return
        }

        const enabled = document.documentElement.dataset.shortcuts !== 'off'

        if (row && (enabled || event.key === 'Enter')) {
            const target = row.querySelector(`[data-row-key="${rowKey(event)}"]`)

            if (target) {
                event.preventDefault()
                target.click()

                return
            }
        }

        if (!enabled) {
            return
        }

        // + takes Shift on most keyboards, so a character is matched first.
        const characterTarget = rowKey(event) === '' ? null : document.querySelector(`[data-shortcut-key="${rowKey(event)}"]`)

        if (characterTarget) {
            event.preventDefault()
            characterTarget.click()

            return
        }

        if (event.shiftKey) {
            return
        }

        const focusTarget = document.querySelector(`[data-shortcut-focus="${event.code}"]`)
        const clickTarget = document.querySelector(`[data-shortcut-click="${event.code}"]`)

        if (focusTarget) {
            event.preventDefault()
            focusTarget.focus()
            focusTarget.select?.()
        } else if (clickTarget) {
            event.preventDefault()
            clickTarget.click()
        }
    })
}

/**
 * The switch in Settings: on, or off, for this browser, from the moment it
 * is flipped.
 */
function initToggle() {
    const root = document.documentElement
    const key = () => root.dataset.shortcutsStorageKey ?? 'admin.shortcuts'

    const sync = () => {
        document.querySelectorAll('[data-shortcuts-toggle]').forEach((toggle) => {
            toggle.checked = root.dataset.shortcuts !== 'off'
        })
    }

    document.addEventListener('change', (event) => {
        if (!event.target.matches?.('[data-shortcuts-toggle]')) {
            return
        }

        const state = event.target.checked ? 'on' : 'off'

        root.dataset.shortcuts = state

        try {
            localStorage.setItem(key(), state)
        } catch {
            // Blocked storage: it holds for this page only.
        }

        sync()
    })

    // The switch reflects what is stored, on load and whenever Livewire
    // draws the tab again.
    document.addEventListener('DOMContentLoaded', sync)
    document.addEventListener('livewire:navigated', sync)
    document.addEventListener('livewire:morphed', sync)
    sync()
}

function isTyping(element) {
    return Boolean(element && (element.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(element.tagName)))
}

function overlayOpen() {
    return document.querySelector('.modal.show, .offcanvas.show') !== null
}

function listNamedBy(element) {
    const id = element?.dataset?.rowListInput

    return id ? document.getElementById(id) : null
}

function rowKey(event) {
    if (event.key === '+' || event.code === 'NumpadAdd') {
        return '+'
    }

    if (event.key === '-' || event.code === 'NumpadSubtract') {
        return '-'
    }

    return event.key === 'Enter' ? 'Enter' : ''
}

/**
 * Focus the next or previous row; past the first row, back to the field the
 * list is searched from.
 */
function move(list, row, step) {
    const rows = [...list.querySelectorAll('[data-row]')]
    const index = row ? rows.indexOf(row) + step : step > 0 ? 0 : rows.length - 1

    if (index < 0) {
        document.querySelector(`[data-row-list-input="${list.id}"]`)?.focus()

        return
    }

    rows[Math.min(index, rows.length - 1)]?.focus()
}
