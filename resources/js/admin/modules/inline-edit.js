/**
 * A value changed where it stands, in a list's row: `[data-inline-edit]`.
 *
 * When the field appears it takes the focus with its text selected, so the
 * new value is typed straight over the old. When it goes — saved with Enter,
 * or left with Escape — the focus goes back to its row, so ↓ and Enter move
 * on to the next one without reaching for the mouse.
 *
 * The component draws and removes the field; this only moves the focus,
 * watching the page for the field to come and go.
 */
export function initInlineEdit() {
    let returnTo = null

    document.addEventListener(
        'keydown',
        (event) => {
            const field = event.target.closest?.('[data-inline-edit]')

            if (field && (event.key === 'Enter' || event.key === 'Escape')) {
                returnTo = field.closest('[data-row]')?.id || null
            }
        },
        true,
    )

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    focusField(node)
                }
            }
        }

        if (returnTo && !document.querySelector('[data-inline-edit]')) {
            const row = document.getElementById(returnTo)

            returnTo = null

            if (row && !document.activeElement?.closest?.('form, .modal, .offcanvas')) {
                row.focus()
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true })
}

function focusField(node) {
    const field = node.matches('[data-inline-edit]') ? node : node.querySelector('[data-inline-edit]')

    if (field && document.activeElement !== field) {
        field.focus()
        field.select?.()
    }
}
