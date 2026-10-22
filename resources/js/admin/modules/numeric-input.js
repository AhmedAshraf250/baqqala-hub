/**
 * Fields for figures take only what a figure is made of.
 *
 * `inputmode="decimal"`: digits — Latin, Arabic-Indic, or Persian — and one
 * decimal separator («.», «٫», or «,»). `inputmode="numeric"`: digits alone.
 * A letter typed or pasted into one never appears, so a slip shows at once
 * rather than as an error on saving. The server still checks every value.
 */
const DIGIT = /[0-9٠-٩۰-۹]/
const SEPARATOR = /[.,٫]/

export function initNumericInputs() {
    document.addEventListener('beforeinput', (event) => {
        const field = numericField(event.target)

        if (!field || !event.inputType?.startsWith('insert') || event.data == null) {
            return
        }

        const separatorTaken = SEPARATOR.test(field.value.slice(0, field.selectionStart ?? 0) + field.value.slice(field.selectionEnd ?? 0))

        for (const character of event.data) {
            const fits = DIGIT.test(character) || (field.inputMode === 'decimal' && SEPARATOR.test(character) && !separatorTaken)

            if (!fits) {
                event.preventDefault()

                return
            }
        }
    })

    // Whatever reached the field another way — pasted, dropped, filled in —
    // is cleaned before anything listening to the field reads it.
    document.addEventListener(
        'input',
        (event) => {
            const field = numericField(event.target)

            if (field) {
                const cleaned = clean(field.value, field.inputMode === 'decimal')

                if (cleaned !== field.value) {
                    field.value = cleaned
                }
            }
        },
        true,
    )
}

function numericField(target) {
    return target instanceof HTMLInputElement && (target.inputMode === 'decimal' || target.inputMode === 'numeric') ? target : null
}

function clean(value, decimal) {
    let separatorSeen = false

    return [...value]
        .filter((character) => {
            if (DIGIT.test(character)) {
                return true
            }

            if (decimal && SEPARATOR.test(character) && !separatorSeen) {
                separatorSeen = true

                return true
            }

            return false
        })
        .join('')
}
