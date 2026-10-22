/**
 * Offers the browser's own time zone where a settings form asks for one.
 *
 * The server cannot know which zone the reader's device is set to; the
 * browser can. A hint marked `data-device-timezone` names the select it
 * belongs to in `data-target`; it is shown with the zone's name only when that
 * select offers the zone, and its `data-device-timezone-use` button picks it
 * — through a change event, so Livewire's binding hears it as a choice.
 */
export function initDeviceTimezone() {
    let zone = ''

    try {
        zone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? ''
    } catch {
        return
    }

    if (zone === '') {
        return
    }

    const targetOf = (hint) => document.getElementById(hint?.dataset.target ?? '')

    const reveal = () => {
        document.querySelectorAll('[data-device-timezone]').forEach((hint) => {
            const target = targetOf(hint)

            if (!target || ![...target.options].some((option) => option.value === zone)) {
                return
            }

            hint.querySelector('[data-device-timezone-name]').textContent = zone
            hint.hidden = false
        })
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-device-timezone-use]')
        const target = targetOf(button?.closest('[data-device-timezone]'))

        if (!target) {
            return
        }

        target.value = zone
        target.dispatchEvent(new Event('change', { bubbles: true }))
    })

    reveal()
}
