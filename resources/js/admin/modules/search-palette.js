/**
 * The quick search, from any screen: Ctrl K (unless this browser turned
 * shortcuts off), or anything marked `data-search-palette-open`.
 *
 * Inside it, Enter in the field opens the first result; ↓ goes down into the
 * results and ↑ ↓ move between them; Enter opens the one in focus, and its
 * own keys — «+», «−» — do what it offers. A letter typed on a result goes
 * back into the field.
 */
export function initSearchPalette() {
    const open = () => window.Livewire?.dispatch('search-palette-open')

    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-search-palette-open]')) {
            event.preventDefault()
            open()
        }
    })

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && !event.altKey && event.code === 'KeyK') {
            if (document.documentElement.dataset.shortcuts !== 'off') {
                event.preventDefault()
                open()
            }

            return
        }

        const palette = event.target.closest?.('#search-palette')

        if (!palette || event.ctrlKey || event.metaKey || event.altKey) {
            return
        }

        const field = palette.querySelector('#search-palette-input')
        const results = [...palette.querySelectorAll('[data-palette-result]')]
        const index = results.indexOf(document.activeElement)

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault()
            const next = index === -1 ? (event.key === 'ArrowDown' ? 0 : results.length - 1) : index + (event.key === 'ArrowDown' ? 1 : -1)

            next < 0 ? field?.focus() : results[Math.min(next, results.length - 1)]?.focus()

            return
        }

        if (event.target === field && event.key === 'Enter' && results.length > 0) {
            event.preventDefault()
            results[0].click()

            return
        }

        if (index === -1) {
            return
        }

        const key = event.key === '+' || event.code === 'NumpadAdd' ? '+' : event.key === '-' || event.code === 'NumpadSubtract' ? '-' : null
        const action = key ? results[index].closest('li')?.querySelector(`[data-palette-action="${key}"]`) : null

        if (action) {
            event.preventDefault()
            action.click()
        } else if (event.key.length === 1) {
            // Typed on a result: it belongs in the field.
            field?.focus()
        }
    })
}
