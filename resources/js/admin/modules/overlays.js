import { Modal, Offcanvas, Toast } from 'bootstrap'

/**
 * Lets a Livewire component open and close Bootstrap's dialogs, drawers, and
 * toasts: `$this->dispatch('overlay-show', id: 'new-customer')`, and
 * `overlay-hide` to close. Bootstrap keeps what it is good at — focus held
 * inside, Escape, focus back to whatever opened it.
 *
 * Once one is open, its `[data-autofocus]` field takes the focus, so the
 * person types straight away. Inside a `[data-ctrl-enter]` region, Ctrl+Enter
 * presses its `[data-ctrl-enter-submit]` button from any field. A drawer whose
 * content carries `data-confirm-close` asks that question before Escape or a
 * click closes it; a close the component asks for itself never asks.
 *
 * Bootstrap puts a drawer's backdrop beside the drawer — inside the Livewire
 * component that draws it, whose next redraw removes or reshapes it, leaving
 * the page undimmed or black. Each one is moved to the body as it appears,
 * where it is Bootstrap's alone.
 */
export function initOverlays() {
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.classList?.contains('offcanvas-backdrop') && node.parentElement !== document.body) {
                    document.body.append(node)
                }
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true })

    window.addEventListener('overlay-show', (event) => instanceFor(event.detail?.id)?.show())

    window.addEventListener('overlay-hide', (event) => {
        const element = elementFor(event.detail?.id)

        if (element) {
            element.dataset.closing = 'asked'
            instanceFor(event.detail.id)?.hide()
        }
    })

    const focusFirst = (event) => {
        const field = event.target.querySelector('[data-autofocus]')

        field?.focus()
        field?.select?.()
    }

    document.addEventListener('shown.bs.modal', focusFirst)
    document.addEventListener('shown.bs.offcanvas', focusFirst)

    document.addEventListener('hide.bs.offcanvas', (event) => {
        if (event.target.dataset.closing === 'asked') {
            delete event.target.dataset.closing

            return
        }

        const question = event.target.querySelector('[data-confirm-close]')?.dataset.confirmClose

        if (question && !window.confirm(question)) {
            event.preventDefault()
        }
    })

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !(event.ctrlKey || event.metaKey)) {
            return
        }

        const submit = document.activeElement?.closest('[data-ctrl-enter]')?.querySelector('[data-ctrl-enter-submit]')

        if (submit && !submit.disabled) {
            event.preventDefault()
            submit.click()
        }
    })
}

function elementFor(id) {
    return id ? document.getElementById(id) : null
}

function instanceFor(id) {
    const element = elementFor(id)

    if (!element) {
        return null
    }

    if (element.classList.contains('offcanvas')) {
        return Offcanvas.getOrCreateInstance(element)
    }

    if (element.classList.contains('toast')) {
        return Toast.getOrCreateInstance(element)
    }

    return Modal.getOrCreateInstance(element)
}
