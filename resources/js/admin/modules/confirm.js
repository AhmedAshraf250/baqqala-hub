import { Modal } from 'bootstrap'

/**
 * Asks before anything is deleted, archived, or undone.
 *
 * A control carrying `data-confirm="what will happen"` has its click held back
 * — before Livewire or a form sees it — while the shell's `#confirm-dialog`
 * asks. «Yes» lets the same click through; anything else drops it, and the
 * focus goes back to the control. `data-confirm-title`, `data-confirm-ok`,
 * `data-confirm-tone` (danger or warning), and `data-confirm-icon` shape the
 * question.
 */
export function initConfirm() {
    let pending = null

    document.addEventListener(
        'click',
        (event) => {
            const trigger = event.target.closest?.('[data-confirm]')

            if (!trigger || trigger.dataset.confirmed === 'yes') {
                return
            }

            const dialog = document.getElementById('confirm-dialog')

            if (!dialog) {
                return
            }

            event.preventDefault()
            event.stopImmediatePropagation()

            pending = trigger
            fill(dialog, trigger)
            Modal.getOrCreateInstance(dialog).show()
        },
        true,
    )

    document.addEventListener('click', (event) => {
        if (!event.target.closest?.('#confirm-dialog [data-confirm-ok]') || !pending) {
            return
        }

        const trigger = pending

        pending = null
        Modal.getOrCreateInstance(document.getElementById('confirm-dialog')).hide()

        trigger.dataset.confirmed = 'yes'
        trigger.click()
        delete trigger.dataset.confirmed
    })

    document.addEventListener('shown.bs.modal', (event) => {
        if (event.target.id === 'confirm-dialog') {
            event.target.querySelector('[data-confirm-cancel]')?.focus()
        }
    })

    document.addEventListener('hidden.bs.modal', (event) => {
        if (event.target.id !== 'confirm-dialog') {
            return
        }

        if (pending?.isConnected) {
            pending.focus()
        }

        pending = null
    })
}

function fill(dialog, trigger) {
    const tone = trigger.dataset.confirmTone === 'warning' ? 'warning' : 'danger'
    const title = dialog.querySelector('[data-confirm-title]')
    const ok = dialog.querySelector('[data-confirm-ok]')

    title.textContent = trigger.dataset.confirmTitle || title.dataset.default
    dialog.querySelector('[data-confirm-message]').textContent = trigger.dataset.confirm
    ok.textContent = trigger.dataset.confirmOk || ok.dataset.default
    ok.className = `btn px-4 btn-${tone}`
    dialog.querySelector('[data-confirm-icon]').className = `confirm-dialog-icon mb-3 confirm-dialog-icon-${tone}`
    dialog.querySelector('[data-confirm-icon] i').className = `bi bi-${trigger.dataset.confirmIcon || (tone === 'warning' ? 'archive' : 'exclamation-triangle')}`
}
