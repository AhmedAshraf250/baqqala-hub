/**
 * Colour-mode switching for the admin area.
 *
 * The initial theme is applied inline in <head> to avoid a flash of the wrong
 * palette; this module only handles the dropdown and system-preference changes
 * once the page is interactive.
 */

const STORAGE_KEY = document.documentElement.dataset.themeStorageKey ?? 'admin.theme'

const systemPrefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches

const resolveTheme = (choice) =>
    choice === 'dark' || (choice === 'auto' && systemPrefersDark()) ? 'dark' : 'light'

const readChoice = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) ?? 'auto'
    } catch {
        return 'auto'
    }
}

const writeChoice = (choice) => {
    try {
        localStorage.setItem(STORAGE_KEY, choice)
    } catch {
        // Storage can be unavailable; the theme still applies for this page.
    }
}

const applyTheme = (choice) => {
    document.documentElement.setAttribute('data-bs-theme', resolveTheme(choice))
    document.documentElement.dataset.themeChoice = choice
}

const markActiveOption = (choice) => {
    const activeIcon = document.querySelector('.theme-icon-active i')
    const activeButton = document.querySelector(`[data-bs-theme-value="${choice}"]`)

    for (const button of document.querySelectorAll('[data-bs-theme-value]')) {
        button.classList.remove('active')
        button.setAttribute('aria-pressed', 'false')
        button.querySelector('.bi-check-lg')?.classList.add('d-none')
    }

    if (!activeButton) {
        return
    }

    activeButton.classList.add('active')
    activeButton.setAttribute('aria-pressed', 'true')
    activeButton.querySelector('.bi-check-lg')?.classList.remove('d-none')

    const optionIcon = activeButton.querySelector('i')?.getAttribute('class')

    if (activeIcon && optionIcon) {
        activeIcon.setAttribute('class', optionIcon)
    }
}

export const initColorMode = () => {
    const choice = readChoice()

    applyTheme(choice)
    markActiveOption(choice)

    for (const button of document.querySelectorAll('[data-bs-theme-value]')) {
        button.addEventListener('click', () => {
            const next = button.getAttribute('data-bs-theme-value') ?? 'auto'

            writeChoice(next)
            applyTheme(next)
            markActiveOption(next)
        })
    }

    // Follow the OS while the user is on "auto".
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (readChoice() === 'auto') {
            applyTheme('auto')
        }
    })
}
