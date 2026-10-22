/**
 * A button that records the person's voice and sends it — for a note said
 * rather than written. It knows nothing of what the clip is for.
 *
 *     <button data-voice-recorder
 *             data-voice-url="…"           where the clip is posted, as `voice`, with `seconds` and `peak`
 *             data-voice-max="60"          the longest a clip may run, in seconds
 *             data-voice-event="…"         the Livewire event dispatched once it is saved, with the server's answer
 *             data-voice-text='{"record": "…", "stop": "…", "recording": "…", "saving": "…", "saved": "…",
 *                               "denied": "…", "failed": "…", "silent": "…", "broken": "…"}'>
 *         <span data-voice-label>…</span> <span data-voice-timer></span>
 *     </button>
 *     <span data-voice-level></span>        a bar that moves with the voice while it records
 *     <p data-voice-status role="status"></p>
 *
 * The fields of its form marked `[data-voice-field]` go with the clip, by
 * name — a checked radio's value, a text field's words.
 *
 * A clip is only sent once it is known to hold a voice: while it records,
 * the bar shows the microphone hearing something; a clip that stayed silent
 * throughout, or that the browser cannot play back, is refused here, with a
 * reason, while the person can still say it again.
 *
 * Shown only where the browser may record: a secure page with a microphone
 * API (`html[data-voice="on"]`, set here, and the theme hides the rest). The
 * first press asks for the microphone and starts; the second — or the limit
 * — stops, checks, and sends.
 */
const recordings = new WeakMap()

/** Below this, 0 to 1, the loudest moment of a clip is silence. */
const SILENCE = 0.02

/** The shortest clip worth keeping, in seconds. */
const SHORTEST = 0.5

export function initVoiceRecorders() {
    if (window.isSecureContext && navigator.mediaDevices?.getUserMedia && window.MediaRecorder) {
        document.documentElement.dataset.voice = 'on'
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-voice-recorder]')

        if (!button) {
            return
        }

        event.preventDefault()

        recordings.has(button) ? stop(button) : start(button)
    })

    // A dialog closed mid-recording keeps nothing it did not save.
    document.addEventListener('hidden.bs.modal', (event) => {
        event.target.querySelectorAll('[data-voice-recorder]').forEach((button) => {
            const recording = recordings.get(button)

            if (recording) {
                recording.discard = true
                stop(button)
            }
        })
    })
}

function text(button, key) {
    try {
        return JSON.parse(button.dataset.voiceText ?? '{}')[key] ?? ''
    } catch {
        return ''
    }
}

function status(button, message, tone = '') {
    const line = button.parentElement?.querySelector('[data-voice-status]')

    if (line) {
        line.textContent = message
        line.className = `small mb-0 mt-1 ${tone}`.trim()
    }
}

function level(button, value) {
    const bar = button.parentElement?.querySelector('[data-voice-level]')

    if (bar) {
        bar.style.setProperty('--voice-level', String(Math.min(1, value * 4)))
        bar.classList.toggle('is-hearing', value >= SILENCE)
    }
}

async function start(button) {
    let stream

    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    } catch {
        status(button, text(button, 'denied'), 'text-danger-emphasis')

        return
    }

    const recorder = new MediaRecorder(stream)
    const chunks = []
    const startedAt = Date.now()
    const limit = Number(button.dataset.voiceMax || 60)
    const recording = { recorder, stream, startedAt, discard: false, ticker: null, peak: 0, listening: null }

    recording.listening = listen(stream, (value) => {
        recording.peak = Math.max(recording.peak, value)
        level(button, value)
    })

    recorder.addEventListener('dataavailable', (event) => event.data.size > 0 && chunks.push(event.data))
    recorder.addEventListener('stop', () => finish(button, recording, chunks))

    recordings.set(button, recording)
    recorder.start()

    button.classList.add('is-recording')
    button.setAttribute('aria-pressed', 'true')
    label(button, text(button, 'stop'))
    status(button, text(button, 'recording'))

    recording.ticker = setInterval(() => {
        const seconds = Math.floor((Date.now() - startedAt) / 1000)

        timer(button, seconds)

        if (seconds >= limit) {
            stop(button)
        }
    }, 250)
}

/**
 * Hear the microphone as it records: how loud it is now, 0 to 1, many times a
 * second. Returns what stops listening.
 */
function listen(stream, heard) {
    const Context = window.AudioContext || window.webkitAudioContext

    if (!Context) {
        return null
    }

    const context = new Context()
    const analyser = context.createAnalyser()
    const samples = new Float32Array(analyser.fftSize)
    let frame = null

    context.createMediaStreamSource(stream).connect(analyser)

    const measure = () => {
        analyser.getFloatTimeDomainData(samples)
        heard(Math.sqrt(samples.reduce((sum, sample) => sum + sample * sample, 0) / samples.length))
        frame = requestAnimationFrame(measure)
    }

    measure()

    return () => {
        cancelAnimationFrame(frame)
        context.close()
    }
}

function stop(button) {
    const recording = recordings.get(button)

    if (!recording) {
        return
    }

    clearInterval(recording.ticker)
    recording.listening?.()
    level(button, 0)

    if (recording.recorder.state !== 'inactive') {
        recording.recorder.stop()
    }

    recording.stream.getTracks().forEach((track) => track.stop())
}

/**
 * How long a clip plays, in seconds, once the browser has read it back —
 * or null when it cannot: the clip is damaged.
 */
async function playable(blob) {
    const Context = window.AudioContext || window.webkitAudioContext

    if (!Context) {
        return 1
    }

    const context = new Context()

    try {
        return (await context.decodeAudioData(await blob.arrayBuffer())).duration
    } catch {
        return null
    } finally {
        context.close()
    }
}

async function finish(button, recording, chunks) {
    recordings.delete(button)
    button.classList.remove('is-recording')
    button.setAttribute('aria-pressed', 'false')
    label(button, text(button, 'record'))
    timer(button, null)

    if (recording.discard || chunks.length === 0) {
        status(button, '')

        return
    }

    const type = recording.recorder.mimeType || 'audio/webm'
    const blob = new Blob(chunks, { type: type.split(';')[0] })

    // Nothing was heard: the microphone was off, muted, or too far.
    if (recording.listening && recording.peak < SILENCE) {
        status(button, text(button, 'silent'), 'text-danger-emphasis')

        return
    }

    const duration = await playable(blob)

    if (duration === null || duration < SHORTEST) {
        status(button, text(button, 'broken'), 'text-danger-emphasis')

        return
    }

    const extension = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm'
    const form = new FormData()

    form.append('voice', new File([blob], `note.${extension}`, { type: blob.type }))
    form.append('seconds', String(Math.max(1, Math.round(Number.isFinite(duration) ? duration : (Date.now() - recording.startedAt) / 1000))))
    form.append('peak', String(Math.round(Math.min(1, recording.peak * 4) * 100)))

    button.closest('form')?.querySelectorAll('[data-voice-field]').forEach((field) => {
        if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) {
            return
        }

        if (field.name) {
            form.append(field.name, field.value)
        }
    })

    button.disabled = true
    status(button, text(button, 'saving'))

    try {
        const response = await fetch(button.dataset.voiceUrl, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        })

        const answer = await response.json().catch(() => ({}))

        if (!response.ok) {
            status(button, answer.message || text(button, 'failed'), 'text-danger-emphasis')

            return
        }

        status(button, text(button, 'saved'), 'text-success-emphasis')
        window.Livewire?.dispatch(button.dataset.voiceEvent, answer)
    } catch {
        status(button, text(button, 'failed'), 'text-danger-emphasis')
    } finally {
        button.disabled = false
    }
}

function label(button, words) {
    const span = button.querySelector('[data-voice-label]')

    if (span) {
        span.textContent = words
    }
}

function timer(button, seconds) {
    const span = button.querySelector('[data-voice-timer]')

    if (span) {
        span.textContent = seconds === null ? '' : `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
    }
}
