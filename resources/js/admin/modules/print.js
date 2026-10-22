/**
 * Prints what a button names: `data-print` prints the page as its print
 * styles draw it (a statement, without the sidebar or the buttons);
 * `data-print="receipt"` prints only the open drawer marked `.receipt`.
 *
 * The browser's own dialog does the rest — paper, or a PDF to send.
 */
export function initPrint() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-print]')

        if (!button) {
            return
        }

        const receipt = button.dataset.print === 'receipt'

        document.body.classList.toggle('printing-receipt', receipt)
        window.print()
        document.body.classList.remove('printing-receipt')
    })
}
