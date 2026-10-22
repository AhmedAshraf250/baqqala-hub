<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Data\EntryOpening;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Notebook\Authorization\NotebookPermission;
use App\Modules\Notebook\Contracts\Data\NoteReading;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Contracts\NoteReaderInterface;
use App\Modules\Notebook\Database\Models\Note;
use App\Modules\Notebook\Domain\Actions\DiscardNote;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Going through the notebook when the shop is quiet: each note waiting to be
 * posted, heard or read, with who it seems to be about. Choosing the
 * customer opens the drawer that records entries, filled in from the note —
 * the person checks it and saves it, and the note learns it was posted from
 * the ledger. A note that was nothing is deleted.
 *
 * Below, what was gone through today, so it is plain what became of each.
 */
new #[Layout('admin::layout.app')] class extends Component {
    /** The note whose customer is being looked for, if any. */
    public ?int $choosing = null;

    /** What was typed to find that customer. */
    public string $find = '';

    /** What the last action did, in words. */
    public ?string $notice = null;

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('notebook::module.navigation.notebook')]);
    }

    /**
     * What waits to be posted, the oldest first — the order it happened in.
     *
     * @return Collection<int, Note>
     */
    #[Computed]
    public function notes(): Collection
    {
        return Note::query()->pending()->orderBy('created_at')->orderBy('id')->limit(100)->get();
    }

    /**
     * What each written note seems to say, by note.
     *
     * @return array<int, NoteReading>
     */
    #[Computed]
    public function readings(): array
    {
        $reader = app(NoteReaderInterface::class);

        return $this->notes
            ->mapWithKeys(static fn (Note $note): array => [$note->id => $note->body === null ? new NoteReading : $reader->read($note->body)])
            ->all();
    }

    /**
     * What was gone through today, the latest first.
     *
     * @return Collection<int, Note>
     */
    #[Computed]
    public function settled(): Collection
    {
        [$start, $end] = app(LocalClock::class)->dayBounds(app(LocalClock::class)->today());

        return Note::query()
            ->whereIn('status', [NoteStatus::Posted->value, NoteStatus::Discarded->value])
            ->whereBetween('settled_at', [$start, $end])
            ->latest('settled_at')
            ->limit(50)
            ->get();
    }

    /**
     * The names of the people who wrote or went through these notes.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function people(): array
    {
        $ids = $this->notes->pluck('written_by')
            ->merge($this->settled->pluck('written_by'))
            ->merge($this->settled->pluck('settled_by'))
            ->filter()
            ->unique()
            ->all();

        return AdminUser::query()->whereKey($ids)->pluck('name', 'id')->all();
    }

    /**
     * The customers these notes went to, or were on before an undo.
     *
     * @return array<int, CustomerData>
     */
    #[Computed]
    public function customers(): array
    {
        $ids = $this->notes->pluck('customer_id')->merge($this->settled->pluck('customer_id'))->filter()->unique()->values()->all();

        return app(CustomerRepositoryInterface::class)->findMany($ids)->keyBy('id')->all();
    }

    /**
     * Who matches what was typed to find the customer.
     *
     * @return Collection<int, CustomerData>
     */
    #[Computed]
    public function matches(): Collection
    {
        return trim($this->find) === '' ? collect() : app(CustomerRepositoryInterface::class)->search($this->find, 6);
    }

    public function choose(int $noteId): void
    {
        $this->choosing = $noteId;
        $this->find = '';
    }

    public function stopChoosing(): void
    {
        $this->reset('choosing', 'find');
    }

    /**
     * Open the drawer on a note, for one customer, filled in from what the
     * note says. Nothing is posted until the person saves it there.
     */
    public function post(int $noteId, int $customerId, NoteReaderInterface $reader, CustomerRepositoryInterface $customers): void
    {
        $this->authorizeReview();

        $note = Note::query()->pending()->findOrFail($noteId);
        $customer = $customers->find($customerId) ?? abort(404);
        $reading = $note->body === null ? new NoteReading : $reader->read($note->body);

        $opening = new EntryOpening(
            customerId: (int) $customer->id,
            kind: $note->kind,
            amount: $reading->amount,
            description: $reading->description,
            note: __('notebook::module.entry_note', [
                'time' => $this->when($note->created_at),
                'text' => $note->body ?? __('notebook::module.page.voice_only'),
            ]),
            operationId: $note->operation_id,
        );

        $this->reset('choosing', 'find', 'notice');
        $this->dispatch(EntryOpening::EVENT, ...$opening->parameters());
    }

    public function discard(int $noteId, DiscardNote $discard): void
    {
        $this->authorizeReview();

        if ($discard->handle($noteId, (int) Area::Admin->user()?->getKey())) {
            $this->notice = __('notebook::module.page.discarded');
        }

        $this->reset('choosing', 'find');
    }

    /**
     * A note was jotted, or an entry posted — perhaps one of these notes.
     */
    #[On('note-written')]
    #[On('entry-posted')]
    public function refresh(): void
    {
        unset($this->notes, $this->readings, $this->settled, $this->people, $this->customers);
    }

    /**
     * What is wrong with a note's clip on the disk — `missing`, or `damaged`
     * when it is no longer the size it was saved at — or null when it is
     * whole. The size is enough to read on every visit; the fingerprint is
     * kept for a fuller check.
     */
    public function clipProblem(Note $note): ?string
    {
        $disk = Storage::disk((string) config('notebook.voice.disk'));

        return match (true) {
            $note->voice_path === null => null,
            ! $disk->exists($note->voice_path) => 'missing',
            $note->voice_bytes !== null && $disk->size($note->voice_path) !== $note->voice_bytes => 'damaged',
            default => null,
        };
    }

    public function isPosted(Note $note): bool
    {
        return $note->status === NoteStatus::Posted;
    }

    public function isCharge(Note $note): bool
    {
        return $note->kind !== EntryKind::Payment;
    }

    public function when(CarbonImmutable $moment): string
    {
        return local_time($moment)?->isoFormat('LT') ?? '';
    }

    /**
     * Who wrote or went through a note, as a sentence may hold it.
     */
    public function writer(?int $id): string
    {
        return $this->isolated($id === null ? __('notebook::module.page.nobody') : ($this->people[$id] ?? __('notebook::module.page.nobody')));
    }

    /**
     * The customer a note went to, as a sentence may hold it.
     */
    public function customerName(?int $id): ?string
    {
        $name = $id === null ? null : ($this->customers[$id]->name ?? null);

        return $name === null ? null : $this->isolated($name);
    }

    /**
     * A name set apart from the sentence around it, so an Arabic name in an
     * English line — or the other way round — keeps the words after it in
     * their place: «Written by أسامة, 12:58 PM», not the time pulled into it.
     */
    public function isolated(string $text): string
    {
        return "\u{2068}{$text}\u{2069}";
    }

    private function authorizeReview(): void
    {
        abort_unless(Area::Admin->user()?->can(NotebookPermission::Review->value) === true, 403);
    }
}; ?>

<div class="d-grid gap-3">
    <p class="text-body-secondary mb-0">{{ __('notebook::module.page.intro') }}</p>

    @if ($notice)
        <x-admin::ui.alert variant="success" icon="check-circle" dismissible wire:key="notice-{{ md5($notice) }}">{{ $notice }}</x-admin::ui.alert>
    @endif

    <section aria-labelledby="notebook-waiting" class="d-grid gap-2">
        <h2 id="notebook-waiting" class="entry-label mb-0">
            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
            {{ trans_choice('notebook::module.page.waiting', $this->notes->count(), ['count' => $this->notes->count()]) }}
        </h2>

        @forelse ($this->notes as $note)
            @php($reading = $this->readings[$note->id])

            <article @class(['note-card', 'flow-debit' => $this->isCharge($note), 'flow-credit' => ! $this->isCharge($note)]) wire:key="note-{{ $note->id }}" aria-labelledby="note-{{ $note->id }}-kind">
                <header class="note-card-head">
                    <span class="note-card-kind" id="note-{{ $note->id }}-kind">
                        <i @class(['bi', 'bi-bag-plus' => $this->isCharge($note), 'bi-cash-coin' => ! $this->isCharge($note)]) aria-hidden="true"></i>
                        {{ __('notebook::module.kind.'.$note->kind->value) }}
                    </span>
                    <span class="small text-body-secondary">{{ __('notebook::module.page.written', ['name' => $this->writer($note->written_by), 'when' => $this->when($note->created_at)]) }}</span>
                </header>

                @if ($note->body !== null)
                    <p class="note-card-text" dir="auto">{{ $note->body }}</p>
                @endif

                @if ($note->hasVoice())
                    @if ($this->clipProblem($note) !== null)
                        <p class="small text-danger-emphasis mb-0">
                            <i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i>{{ __('notebook::module.page.clip_'.$this->clipProblem($note)) }}
                        </p>
                    @else
                        <audio class="note-card-voice" controls preload="none" src="{{ route('admin.notebook.voice.show', $note) }}" aria-label="{{ __('notebook::module.voice.listen') }}"></audio>
                        @if ($note->isFaint())
                            <p class="small text-warning-emphasis mb-0"><i class="bi bi-volume-down me-1" aria-hidden="true"></i>{{ __('notebook::module.page.clip_faint') }}</p>
                        @endif
                    @endif
                @endif

                @if ($this->customerName($note->customer_id) !== null)
                    <p class="small text-warning-emphasis mb-0">
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('notebook::module.page.was_on', ['name' => $this->customerName($note->customer_id)]) }}
                    </p>
                @endif

                @if ($choosing === $note->id)
                    {{-- Whose it is, found by name or phone. --}}
                    <div class="note-card-find">
                        <label for="note-find-{{ $note->id }}" class="form-label small fw-semibold mb-1">{{ __('notebook::module.page.choose') }}</label>
                        <input
                            id="note-find-{{ $note->id }}"
                            type="search"
                            class="form-control"
                            wire:model.live.debounce.250ms="find"
                            placeholder="{{ __('notebook::module.page.find_placeholder') }}"
                            autocomplete="off"
                            dir="auto"
                            x-init="$el.focus()"
                        >
                        @if ($this->matches->isNotEmpty())
                            <div class="note-card-matches">
                                @foreach ($this->matches as $customer)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="post({{ $note->id }}, {{ $customer->id }})" wire:key="match-{{ $note->id }}-{{ $customer->id }}">
                                        <span dir="auto">{{ $customer->name }}</span>
                                        @if ($customer->phone)<span class="num text-body-secondary ms-1" dir="ltr">{{ $customer->phone }}</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        @elseif (trim($find) !== '')
                            <p class="small text-body-secondary mb-0 mt-2">{{ __('notebook::module.page.no_match') }}</p>
                        @endif
                        <button type="button" class="btn btn-sm btn-link mt-1 px-0" wire:click="stopChoosing">{{ __('notebook::module.page.cancel') }}</button>
                    </div>
                @else
                    <div class="note-card-actions">
                        @if ($reading->customer() !== null)
                            {{-- One customer by that name: one press opens the drawer on them. --}}
                            <button type="button" class="btn btn-sm btn-flow-solid" wire:click="post({{ $note->id }}, {{ $reading->customer()->id }})">
                                @if ($reading->amount !== null)
                                    {{ __('notebook::module.page.post_amount', ['name' => $this->isolated($reading->customer()->name), 'amount' => $reading->amount->toDisplayString()]) }}
                                @else
                                    {{ __('notebook::module.page.post_for', ['name' => $this->isolated($reading->customer()->name)]) }}
                                @endif
                            </button>
                        @elseif ($reading->customers !== [])
                            {{-- Several share the name: which one, by name alone. --}}
                            <span class="small fw-semibold">{{ __('notebook::module.page.which') }}</span>
                            @foreach ($reading->customers as $candidate)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-flow"
                                    wire:click="post({{ $note->id }}, {{ $candidate->id }})"
                                    wire:key="candidate-{{ $note->id }}-{{ $candidate->id }}"
                                    aria-label="{{ __('notebook::module.page.post_for', ['name' => $candidate->name]) }}"
                                    dir="auto"
                                >{{ $candidate->name }}</button>
                            @endforeach
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="choose({{ $note->id }})">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>{{ $reading->customers === [] ? __('notebook::module.page.choose') : __('notebook::module.page.someone_else') }}
                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger ms-auto"
                            wire:click="discard({{ $note->id }})"
                            data-confirm="{{ __('notebook::module.page.discard_confirm') }}"
                        >
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('notebook::module.page.discard') }}
                        </button>
                    </div>
                @endif
            </article>
        @empty
            <x-admin::ui.card>
                <x-admin::ui.empty-state icon="journal-check" :title="__('notebook::module.page.empty_title')" :description="__('notebook::module.page.empty_description')" />
            </x-admin::ui.card>
        @endforelse
    </section>

    @if ($this->settled->isNotEmpty())
        <x-admin::ui.card :title="__('notebook::module.page.today')" icon="check2-all" flush>
            <ul class="list-group list-group-flush">
                @foreach ($this->settled as $note)
                    <li class="list-group-item d-flex flex-wrap align-items-baseline gap-2" wire:key="settled-{{ $note->id }}">
                        <span class="small text-body-secondary num">{{ $this->when($note->created_at) }}</span>
                        <span class="fw-semibold">{{ __('notebook::module.kind.'.$note->kind->value) }}</span>
                        <span class="text-truncate flex-grow-1 min-w-0" dir="auto">{{ $note->body ?? __('notebook::module.page.voice_only') }}</span>
                        @if ($this->isPosted($note))
                            <span class="badge bg-success-subtle text-success-emphasis">
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('notebook::module.page.posted_on', ['name' => $this->customerName($note->customer_id) ?? __('notebook::module.page.nobody')]) }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-body-emphasis">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('notebook::module.page.discarded_by', ['name' => $this->writer($note->settled_by)]) }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-admin::ui.card>
    @endif
</div>
