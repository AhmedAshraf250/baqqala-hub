/**
 * The browser's twin of App\Foundation\Text\SearchText: the same folding,
 * letter for letter, so what a person types here matches what the server
 * stored as `search_name`.
 *
 * Both are held to tests/Fixtures/search-text.json — the Pest test reads it
 * for the PHP side, the browser test for this one, through the
 * `search-text-normalize` event below. Change one, change the other.
 */
const DIGITS = {
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٫': '.',
}

const LETTERS = {
    'أ': 'ا', 'إ': 'ا', 'آ': 'ا', 'ٱ': 'ا',
    'ة': 'ه',
    'ى': 'ي',
    'ؤ': 'و',
    'ئ': 'ي',
}

export function normalize(text) {
    let folded = String(text ?? '').replace(/[٠-٩۰-۹٫]/g, (character) => DIGITS[character])

    folded = folded.toLowerCase()

    // Vowel marks, every other combining mark, and tatweel mean nothing to a search.
    folded = folded.replace(/[\p{Mn}ـ]/gu, '')

    folded = folded.replace(/[أإآٱةىؤئ]/g, (character) => LETTERS[character])

    // Punctuation and symbols separate words; a point between two digits is
    // part of a size such as «2.25».
    folded = folded.replace(/(?<!\d)\.|\.(?!\d)|[^\p{L}\p{N}.]+/gu, ' ')

    return folded.replace(/\s+/gu, ' ').trim()
}

/**
 * Lets a test ask for a folding without a global: dispatch
 * `new CustomEvent('search-text-normalize', { detail: { input } })` on the
 * document, and read `detail.output` once it returns.
 */
export function initSearchText() {
    document.addEventListener('search-text-normalize', (event) => {
        event.detail.output = normalize(event.detail.input)
    })
}
