import { normalize } from './search-text.js'

/**
 * A select with a search box in it (`<x-admin::form.search-select>`), for a
 * list too long to scroll through.
 *
 * The real <select> stays in the form, hidden, so wire:model and validation
 * see an ordinary select. The toggle opens the panel with the search box
 * focused; typing filters the options with the same folding the server uses
 * («ارز» finds «أرز»); ↑ ↓ move, Enter chooses, Escape closes and stays in
 * the drawer or dialog around it. Choosing sets the select and announces the
 * change, as picking it by hand would.
 */
export function initSearchSelects() {
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest?.('[data-search-select-toggle]')

        if (toggle) {
            event.preventDefault()
            const host = toggle.closest('[data-search-select]')

            isOpen(host) ? close(host, true) : open(host)

            return
        }

        const option = event.target.closest?.('[data-search-select] [role="option"]')

        if (option) {
            choose(option.closest('[data-search-select]'), option)

            return
        }

        document.querySelectorAll('[data-search-select].is-open').forEach((host) => {
            if (!host.contains(event.target)) {
                close(host, false)
            }
        })
    })

    document.addEventListener('input', (event) => {
        if (event.target.matches?.('[data-search-select-input]')) {
            filter(event.target.closest('[data-search-select]'), event.target.value)
        }
    })

    // Captured, so Escape closes the list before the drawer around it hears it.
    document.addEventListener(
        'keydown',
        (event) => {
            const host = event.target.closest?.('[data-search-select]')

            if (!host) {
                return
            }

            if (!isOpen(host)) {
                if (event.target.matches('[data-search-select-toggle]') && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                    event.preventDefault()
                    open(host)
                }

                return
            }

            if (event.key === 'Escape') {
                event.preventDefault()
                event.stopPropagation()
                close(host, true)
            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault()
                move(host, event.key === 'ArrowDown' ? 1 : -1)
            } else if (event.key === 'Enter') {
                event.preventDefault()
                event.stopPropagation()
                const active = host.querySelector('[role="option"].active:not([hidden])')

                if (active) {
                    choose(host, active)
                }
            } else if (event.key === 'Tab') {
                close(host, false)
            }
        },
        true,
    )
}

function isOpen(host) {
    return host.classList.contains('is-open')
}

function open(host) {
    const input = host.querySelector('[data-search-select-input]')

    document.querySelectorAll('[data-search-select].is-open').forEach((other) => close(other, false))

    host.classList.add('is-open')
    host.querySelector('[data-search-select-panel]').hidden = false
    host.querySelector('[data-search-select-toggle]').setAttribute('aria-expanded', 'true')

    input.value = ''
    filter(host, '')
    activate(host, host.querySelector('[role="option"][aria-selected="true"]') ?? visible(host)[0])
    input.focus()
}

function close(host, refocus) {
    host.classList.remove('is-open')
    host.querySelector('[data-search-select-panel]').hidden = true

    const toggle = host.querySelector('[data-search-select-toggle]')

    toggle.setAttribute('aria-expanded', 'false')

    if (refocus) {
        toggle.focus()
    }
}

function filter(host, query) {
    const words = normalize(query).split(' ').filter(Boolean)

    host.querySelectorAll('[role="option"]').forEach((option) => {
        option.dataset.folded ??= normalize(option.textContent)
        option.hidden = !words.every((word) => option.dataset.folded.includes(word))
    })

    host.querySelector('[data-search-select-none]').hidden = visible(host).length > 0
    activate(host, visible(host)[0])
}

function visible(host) {
    return [...host.querySelectorAll('[role="option"]:not([hidden])')]
}

function activate(host, option) {
    host.querySelectorAll('[role="option"].active').forEach((other) => other.classList.remove('active'))

    const input = host.querySelector('[data-search-select-input]')

    if (!option) {
        input.removeAttribute('aria-activedescendant')

        return
    }

    option.classList.add('active')
    input.setAttribute('aria-activedescendant', option.id)
    option.scrollIntoView({ block: 'nearest' })
}

function move(host, step) {
    const options = visible(host)
    const index = options.indexOf(host.querySelector('[role="option"].active'))

    activate(host, options[Math.max(0, Math.min(options.length - 1, index + step))])
}

function choose(host, option) {
    const select = host.querySelector('[data-search-select-source]')
    const current = host.querySelector('[data-search-select-current]')

    host.querySelectorAll('[role="option"]').forEach((other) => other.setAttribute('aria-selected', other === option ? 'true' : 'false'))
    current.textContent = option.textContent.trim()
    current.classList.remove('text-body-secondary')

    select.value = option.dataset.value
    select.dispatchEvent(new Event('change', { bubbles: true }))

    close(host, true)
}
