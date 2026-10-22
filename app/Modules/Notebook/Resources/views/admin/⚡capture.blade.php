<?php

use App\Foundation\Area\Area;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Notebook\Authorization\NotebookPermission;
use App\Modules\Notebook\Domain\Actions\WriteNote;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The quick note, on every screen: a button at the bottom of the page — or
 * the K key — opens one line to jot «أم كريم زيت وسكر 75», or a microphone
 * to say it. It saves to the notebook and nothing else; the note is posted
 * to an account later, when the notebook is gone through.
 *
 * Placed on every screen by the shell, so it reads nothing until it saves.
 */
new class extends Component {
    public string $kind = 'charge';

    public string $body = '';

    /** Whether the last note was just saved, for the toast. */
    public bool $saved = false;

    /**
     * The voice note just saved — to be heard back from the disk, so whoever
     * said it knows it was kept whole.
     *
     * @var array{note: int, seconds: int|null}|null
     */
    public ?array $heard = null;

    public function save(WriteNote $write): void
    {
        $this->authorizeWriting();

        $this->validate([
            'kind' => ['required', Rule::in([EntryKind::Charge->value, EntryKind::Payment->value])],
            'body' => ['required', 'string', 'max:'.WriteNote::MAX_LENGTH],
        ], attributes: ['body' => __('notebook::module.capture.body')]);

        $write->handle((int) Area::Admin->user()?->getKey(), EntryKind::from($this->kind), $this->body);

        $this->written();
    }

    /**
     * A voice note was saved by the recorder, straight to the notebook.
     */
    #[On('notebook-voice-saved')]
    public function voiceSaved(int $note, ?int $seconds = null): void
    {
        $this->written();
        $this->heard = ['note' => $note, 'seconds' => $seconds];
    }

    /**
     * The longest a note's words may be.
     */
    public function longest(): int
    {
        return WriteNote::MAX_LENGTH;
    }

    /**
     * Whether whoever jotted may also go through the notebook, for the
     * toast's link — asked only once there is a toast to show.
     */
    public function canReview(): bool
    {
        return Area::Admin->user()?->can(NotebookPermission::Review->value) === true;
    }

    private function written(): void
    {
        $this->reset('body', 'kind', 'heard');
        $this->resetErrorBag();
        $this->saved = true;

        $this->dispatch('overlay-hide', id: 'notebook-capture');
        $this->dispatch('overlay-show', id: 'notebook-toast');
        $this->dispatch('note-written');
    }

    private function authorizeWriting(): void
    {
        abort_unless(Area::Admin->user()?->can(NotebookPermission::Write->value) === true, 403);
    }
}; ?>

<div>
    <button
        type="button"
        class="btn btn-primary fab no-print"
        data-bs-toggle="modal"
        data-bs-target="#notebook-capture"
        data-shortcut-click="KeyK"
        aria-keyshortcuts="K"
        aria-label="{{ __('notebook::module.capture.open_label') }}"
    >
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span class="fab-label">{{ __('notebook::module.capture.open') }}</span>
        <kbd class="kbd-hint d-none d-md-inline" aria-hidden="true">K</kbd>
    </button>

    <div class="modal fade" id="notebook-capture" tabindex="-1" aria-labelledby="notebook-capture-title" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered capture-dialog">
            <form class="modal-content" wire:submit="save" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h5 d-flex align-items-center gap-2" id="notebook-capture-title">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>{{ __('notebook::module.capture.title') }}
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('shell.actions.close') }}"></button>
                </div>

                <div class="modal-body d-grid gap-3">
                    <p class="small text-body-secondary mb-0">{{ __('notebook::module.capture.intro') }}</p>

                    <fieldset>
                        <legend class="entry-label">{{ __('notebook::module.capture.kind') }}</legend>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" id="note-kind-charge" name="kind" value="charge" wire:model="kind" data-voice-field>
                                <label class="btn btn-flow flow-debit w-100 entry-kind" for="note-kind-charge">
                                    <i class="bi bi-check2 chip-tick me-1" aria-hidden="true"></i><i class="bi bi-bag-plus me-1" aria-hidden="true"></i>{{ __('notebook::module.kind.charge') }}
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" id="note-kind-payment" name="kind" value="payment" wire:model="kind" data-voice-field>
                                <label class="btn btn-flow flow-credit w-100 entry-kind" for="note-kind-payment">
                                    <i class="bi bi-check2 chip-tick me-1" aria-hidden="true"></i><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>{{ __('notebook::module.kind.payment') }}
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    <div>
                        <label for="notebook-body" class="entry-label">{{ __('notebook::module.capture.body') }}</label>
                        <input
                            id="notebook-body"
                            type="text"
                            name="body"
                            wire:model="body"
                            dir="auto"
                            maxlength="{{ $this->longest() }}"
                            autocomplete="off"
                            placeholder="{{ __('notebook::module.capture.placeholder') }}"
                            data-autofocus
                            data-voice-field
                            aria-describedby="notebook-body-hint @error('body') notebook-body-error @enderror"
                            @error('body') aria-invalid="true" @enderror
                            @class(['form-control form-control-lg', 'is-invalid' => $errors->has('body')])
                        >
                        @error('body')<div id="notebook-body-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        <div id="notebook-body-hint" class="form-text">{{ __('notebook::module.capture.hint') }}</div>
                    </div>

                    {{-- Said rather than written: recorded and sent by
                         resources/js/admin/modules/voice-recorder.js, which shows
                         this only where the browser may use a microphone. --}}
                    <div class="voice-recorder" wire:ignore>
                        <div class="voice-recorder-or small text-body-secondary">{{ __('notebook::module.capture.or') }}</div>
                        <button
                            type="button"
                            class="btn btn-outline-danger w-100"
                            data-voice-recorder
                            data-voice-url="{{ route('admin.notebook.voice.store') }}"
                            data-voice-max="{{ config('notebook.voice.max_seconds') }}"
                            data-voice-event="notebook-voice-saved"
                            data-voice-text="{{ json_encode([
                                'record' => __('notebook::module.voice.record'),
                                'stop' => __('notebook::module.voice.stop'),
                                'recording' => __('notebook::module.voice.recording'),
                                'saving' => __('notebook::module.voice.saving'),
                                'saved' => __('notebook::module.voice.saved'),
                                'denied' => __('notebook::module.voice.denied'),
                                'failed' => __('notebook::module.voice.failed'),
                                'silent' => __('notebook::module.voice.silent'),
                                'broken' => __('notebook::module.voice.broken'),
                            ], JSON_UNESCAPED_UNICODE) }}"
                        >
                            <i class="bi bi-mic-fill" aria-hidden="true"></i>
                            <span data-voice-label>{{ __('notebook::module.voice.record') }}</span>
                            <span class="num" dir="ltr" data-voice-timer></span>
                        </button>
                        <span class="voice-level" data-voice-level aria-hidden="true"></span>
                        <p class="small mb-0 mt-1" data-voice-status role="status" aria-live="polite"></p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary flex-grow-1" wire:loading.attr="disabled" wire:target="save">
                        <span class="spinner-border spinner-border-sm me-1" aria-hidden="true" wire:loading wire:target="save"></span>
                        {{ __('notebook::module.capture.save') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('shell.actions.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Said once, after a save. --}}
    <div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3">
        <div id="notebook-toast" class="toast align-items-center text-bg-dark border-0" role="status" aria-live="polite" aria-atomic="true" data-bs-delay="10000" wire:ignore.self>
            <div class="d-flex align-items-center gap-2 p-2">
                <div class="toast-body flex-grow-1">
                    @if ($saved)
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('notebook::module.capture.saved') }}
                    @endif
                    @if ($heard)
                        {{-- Played from the disk, not from the browser: what was kept. --}}
                        <div class="small mt-2">{{ trans_choice('notebook::module.voice.check', $heard['seconds'] ?? 0, ['seconds' => $heard['seconds'] ?? 0]) }}</div>
                        <audio class="toast-voice mt-1" controls preload="metadata" src="{{ route('admin.notebook.voice.show', $heard['note']) }}" aria-label="{{ __('notebook::module.voice.listen') }}" wire:key="heard-{{ $heard['note'] }}"></audio>
                    @endif
                </div>
                @if ($saved && $this->canReview())
                    <a href="{{ route('admin.notebook.index') }}" class="btn btn-sm btn-light">{{ __('notebook::module.capture.go') }}</a>
                @endif
                <button type="button" class="btn-close btn-close-white me-1" data-bs-dismiss="toast" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>
        </div>
    </div>
</div>
