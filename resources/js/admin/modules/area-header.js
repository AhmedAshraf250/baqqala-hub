/**
 * Tells the server which area every Livewire request comes from.
 *
 * Livewire posts all component updates to one endpoint outside `/admin`, so
 * the URL cannot say which session to open. Without this the server falls
 * back to the Referer header, which proxies and privacy settings strip — and
 * every admin update then met the frontend session and a 419. The name and
 * value are `App\Foundation\Area\Area::RequestHeader` and `Area::Admin`.
 */
const AREA_HEADER = 'X-Area'
const AREA = 'admin'

export const initAreaHeader = () => {
    const register = () => {
        window.Livewire.interceptRequest(({ request }) => {
            request.options.headers[AREA_HEADER] = AREA
        })
    }

    // Livewire may already be running by the time this bundle executes.
    if (window.Livewire) {
        register()
    } else {
        document.addEventListener('livewire:init', register, { once: true })
    }
}
