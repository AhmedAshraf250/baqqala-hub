import { normalize } from './search-text.js'

/**
 * A search box over a list the page hands it, worked from the keyboard — the
 * combobox pattern: type, ↑ ↓, Enter. It knows nothing of what it lists.
 *
 *     <input data-search-picker
 *            data-picker-source="catalog"     a #[Renderless] method of the Livewire
 *                                             component around it, called with the
 *                                             version held: answers {version, fresh}
 *                                             or {version, items, groups}
 *            data-picker-select="add"         called with (id, quantity) for a choice
 *            data-picker-submit="addTyped"    called for Enter with nothing chosen
 *            data-picker-listbox="results"    where the results are drawn (wire:ignore)
 *            data-picker-boost-from="ids"     an element whose data-ids lists ids to rank first
 *            data-picker-text='{"amount": "…:amount…", "free": "…:query…", "group": "…:name… :count…", "filtered": "…:name…"}'
 *            role="combobox" aria-autocomplete="list" aria-controls="results" wire:ignore.self>
 *
 * An item is {id, label, detail, aside, terms, group, rank}: `terms` folded on
 * the server by SearchText, and the query folded here by its twin. Every word
 * typed must be found, in any order — a whole word first, then the start of
 * one, then inside one, then one letter off. «3*» before the words is a
 * quantity. Words typed on the wrong keyboard layout are read again as
 * Arabic. A number alone is offered as an amount with no item to it, and a
 * name that finds nothing as a line by that name: both go to `submit`.
 * A button `[data-picker-group="id"]` with `data-picker-for` naming the box
 * narrows it to one group; Backspace in the empty box lets go.
 */
const LIMIT = 8

/** The Arabic letter each key of a Latin layout types on the Arabic one. */
const ARABIC_LAYOUT = {
    q: 'ض', w: 'ص', e: 'ث', r: 'ق', t: 'ف', y: 'غ', u: 'ع', i: 'ه', o: 'خ', p: 'ح', '[': 'ج', ']': 'د',
    a: 'ش', s: 'س', d: 'ي', f: 'ب', g: 'ل', h: 'ا', j: 'ت', k: 'ن', l: 'م', ';': 'ك', "'": 'ط',
    z: 'ئ', x: 'ء', c: 'ؤ', v: 'ر', b: 'لا', n: 'ى', m: 'ة', ',': 'و', '.': 'ز', '/': 'ظ',
}

const catalogs = new Map()
const states = new WeakMap()

export function initSearchPickers() {
    document.addEventListener('focusin', (event) => {
        if (event.target.matches?.('[data-search-picker]')) {
            load(event.target)
        }
    })

    document.addEventListener('input', (event) => {
        if (event.target.matches?.('[data-search-picker]')) {
            refresh(event.target)
        }
    })

    document.addEventListener('keydown', (event) => {
        if (event.target.matches?.('[data-search-picker]')) {
            onKey(event.target, event)
        }
    })

    document.addEventListener('focusout', (event) => {
        const input = event.target

        if (input.matches?.('[data-search-picker]')) {
            // Late enough for a click on a result to land first — and not at
            // all if the box has its focus back by then: a shelf button takes
            // the focus for its click, opens its list, and hands the focus
            // back, and closing then would shut the list it just opened.
            setTimeout(() => {
                if (document.activeElement !== input) {
                    close(input)
                }
            }, 150)
        }
    })

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-picker-group]')
        const input = button ? document.getElementById(button.dataset.pickerFor ?? '') : null

        if (input) {
            stateOf(input).group = Number(button.dataset.pickerGroup)
            input.focus()
            refresh(input)
        }
    })
}

function stateOf(input) {
    if (!states.has(input)) {
        states.set(input, { options: [], active: -1, group: null, quantity: '' })
    }

    return states.get(input)
}

function wireOf(input) {
    const id = input.closest('[wire\\:id]')?.getAttribute('wire:id')

    return id ? window.Livewire?.find(id) : null
}

function text(input, key, replacements = {}) {
    let template = ''

    try {
        template = JSON.parse(input.dataset.pickerText ?? '{}')[key] ?? ''
    } catch {
        return ''
    }

    return Object.entries(replacements).reduce((result, [name, value]) => result.replaceAll(`:${name}`, value), template)
}

/**
 * Fetch the list once, then only ask whether it is still current.
 */
async function load(input) {
    const source = input.dataset.pickerSource
    const wire = wireOf(input)

    if (!source || !wire) {
        return
    }

    const held = catalogs.get(source)

    if (held && Date.now() - held.checkedAt < 60_000) {
        return
    }

    try {
        const answer = await wire[source](held?.version ?? null)

        if (answer?.fresh && held) {
            held.checkedAt = Date.now()

            return
        }

        catalogs.set(source, {
            version: answer.version,
            checkedAt: Date.now(),
            groups: answer.groups ?? [],
            items: (answer.items ?? []).map((item) => ({ ...item, words: String(item.terms ?? '').split(' ').filter(Boolean) })),
        })

        refresh(input)
    } catch {
        // Without the list the box still takes an amount or a name.
    }
}

function refresh(input) {
    const state = stateOf(input)
    const catalog = catalogs.get(input.dataset.pickerSource) ?? { items: [], groups: [] }
    const { quantity, query } = splitQuantity(input.value)

    state.quantity = quantity
    state.options = options(input, catalog, query, state.group)
    state.active = state.options.length > 0 ? 0 : -1

    draw(input, catalog)
}

function splitQuantity(typed) {
    const latin = String(typed)
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660))
        .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0))
        .replace('٫', '.')
    const prefixed = latin.match(/^\s*(\d+(?:\.\d+)?)\s*[*×]\s*(.*)$/u)

    return prefixed ? { quantity: prefixed[1], query: prefixed[2] } : { quantity: '', query: latin }
}

function options(input, catalog, query, group) {
    const folded = normalize(query)
    const words = folded === '' ? [] : folded.split(' ')

    if (words.length === 0 && group === null) {
        return []
    }

    if (words.length === 1 && /^\d{1,7}(\.\d{1,2})?$/.test(words[0]) && group === null) {
        return [{ kind: 'submit', label: text(input, 'amount', { amount: words[0] }) }]
    }

    let found = rank(input, catalog.items, words, group)

    if (found.length === 0 && /[a-z]/.test(folded)) {
        const retyped = normalize([...query.toLowerCase()].map((key) => ARABIC_LAYOUT[key] ?? key).join(''))

        found = rank(input, catalog.items, retyped === '' ? [] : retyped.split(' '), group)
    }

    const results = found.slice(0, LIMIT).map((item) => ({ kind: 'item', item }))

    if (group === null && words.length > 0) {
        catalog.groups
            .filter((candidate) => normalize(candidate.label).split(' ').some((word) => word.startsWith(words[0])))
            .slice(0, 2)
            .forEach((candidate) => results.push({ kind: 'group', group: candidate, label: text(input, 'group', { name: candidate.label, count: candidate.count }) }))
    }

    if (results.length === 0 && words.length > 0) {
        results.push({ kind: 'submit', label: text(input, 'free', { query: query.trim() }) })
    }

    return results
}

function rank(input, items, words, group) {
    const boosted = boostedIds(input)
    const scored = []

    for (const item of items) {
        if (group !== null && item.group !== group) {
            continue
        }

        const score = words.length === 0 ? 1 : scoreOf(item.words, words)

        if (score > 0) {
            scored.push({ item, score: score + (boosted.has(item.id) ? 2 : 0) })
        }
    }

    return scored
        .sort((a, b) => b.score - a.score
            || (a.item.rank ?? Infinity) - (b.item.rank ?? Infinity)
            || a.item.label.length - b.item.label.length)
        .map((entry) => entry.item)
}

function boostedIds(input) {
    try {
        return new Set(JSON.parse(document.getElementById(input.dataset.pickerBoostFrom ?? '')?.dataset.ids ?? '[]'))
    } catch {
        return new Set()
    }
}

/**
 * How well every word typed is found among an item's words; zero when one
 * of them is not found at all.
 */
function scoreOf(itemWords, typedWords) {
    let total = 0

    for (const typed of typedWords) {
        let best = 0

        for (const word of itemWords) {
            if (word === typed) {
                best = 4
            } else if (word.startsWith(typed)) {
                best = Math.max(best, 3)
            } else if (word.includes(typed)) {
                best = Math.max(best, 2)
            } else if (typed.length >= 4 && [word, word.slice(0, typed.length), word.slice(0, typed.length + 1)].some((part) => oneEditApart(typed, part))) {
                best = Math.max(best, 1)
            }

            if (best === 4) {
                break
            }
        }

        if (best === 0) {
            return 0
        }

        total += best
    }

    return total
}

/** Whether two words differ by at most one letter added, dropped, or changed. */
function oneEditApart(a, b) {
    if (Math.abs(a.length - b.length) > 1) {
        return false
    }

    let i = 0
    let j = 0
    let edits = 0

    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            i++
            j++
            continue
        }

        if (++edits > 1) {
            return false
        }

        if (a.length > b.length) {
            i++
        } else if (b.length > a.length) {
            j++
        } else {
            i++
            j++
        }
    }

    return edits + (a.length - i) + (b.length - j) <= 1
}

function listboxOf(input) {
    return document.getElementById(input.dataset.pickerListbox ?? '')
}

function draw(input, catalog) {
    const state = stateOf(input)
    const listbox = listboxOf(input)

    if (!listbox) {
        return
    }

    listbox.replaceChildren()

    if (state.group !== null) {
        const group = catalog.groups.find((candidate) => candidate.id === state.group)
        const header = document.createElement('div')

        header.className = 'picker-filter'
        header.textContent = text(input, 'filtered', { name: group?.label ?? '' })
        listbox.append(header)
    }

    state.options.forEach((option, index) => {
        const row = document.createElement('div')

        row.id = `${listbox.id}-option-${index}`
        row.setAttribute('role', 'option')
        row.setAttribute('aria-selected', index === state.active ? 'true' : 'false')
        row.className = `picker-option picker-option-${option.kind}`

        const label = document.createElement('span')
        label.className = 'picker-label'
        label.dir = 'auto'
        label.textContent = option.kind === 'item' ? option.item.label : option.label
        row.append(label)

        if (option.kind === 'item') {
            const detail = document.createElement('span')
            detail.className = 'picker-detail'
            detail.dir = 'auto'
            detail.textContent = option.item.detail ?? ''

            const aside = document.createElement('span')
            aside.className = 'picker-aside'
            aside.dir = 'ltr'
            aside.textContent = option.item.aside ?? ''

            row.append(detail, aside)
        }

        row.addEventListener('mousedown', (event) => event.preventDefault())
        row.addEventListener('click', () => choose(input, index))
        row.addEventListener('mousemove', () => activate(input, index))

        listbox.append(row)
    })

    const open = state.options.length > 0 || state.group !== null

    listbox.hidden = !open
    input.setAttribute('aria-expanded', open ? 'true' : 'false')
    activate(input, state.active)
}

function activate(input, index) {
    const state = stateOf(input)
    const listbox = listboxOf(input)

    state.active = index

    listbox?.querySelectorAll('[role="option"]').forEach((row, position) => {
        row.setAttribute('aria-selected', position === index ? 'true' : 'false')
        row.classList.toggle('active', position === index)
    })

    const current = index >= 0 ? document.getElementById(`${listbox?.id}-option-${index}`) : null

    current ? input.setAttribute('aria-activedescendant', current.id) : input.removeAttribute('aria-activedescendant')
    current?.scrollIntoView({ block: 'nearest' })
}

function close(input) {
    const listbox = listboxOf(input)

    if (listbox) {
        listbox.hidden = true
    }

    input.setAttribute('aria-expanded', 'false')
    input.removeAttribute('aria-activedescendant')
}

function isOpen(input) {
    return listboxOf(input)?.hidden === false
}

function onKey(input, event) {
    const state = stateOf(input)

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()

        if (!isOpen(input)) {
            refresh(input)

            return
        }

        const count = state.options.length

        if (count > 0) {
            activate(input, (state.active + (event.key === 'ArrowDown' ? 1 : -1) + count) % count)
        }

        return
    }

    if (event.key === 'Escape' && isOpen(input)) {
        event.preventDefault()
        event.stopPropagation()
        close(input)

        return
    }

    if (event.key === 'Backspace' && input.value === '' && state.group !== null) {
        state.group = null
        refresh(input)

        return
    }

    if (event.key === 'Enter' && !event.ctrlKey && !event.metaKey) {
        event.preventDefault()

        if (isOpen(input) && state.active >= 0) {
            choose(input, state.active)
        } else {
            submit(input)
        }
    }
}

function choose(input, index) {
    const state = stateOf(input)
    const option = state.options[index]

    if (!option) {
        return
    }

    if (option.kind === 'group') {
        state.group = option.group.id
        input.value = ''
        refresh(input)

        return
    }

    if (option.kind === 'submit') {
        submit(input)

        return
    }

    const wire = wireOf(input)
    const quantity = state.quantity

    clear(input)
    wire?.[input.dataset.pickerSelect]?.(option.item.id, quantity)
}

function submit(input) {
    const wire = wireOf(input)
    const method = input.dataset.pickerSubmit

    close(input)

    if (wire && method) {
        Promise.resolve(wire[method]()).finally(() => clear(input))
    }
}

function clear(input) {
    const state = stateOf(input)

    input.value = ''
    input.dispatchEvent(new Event('input', { bubbles: true }))
    state.quantity = ''
    close(input)
}
