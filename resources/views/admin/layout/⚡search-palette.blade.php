<?php

use App\Admin\Search\AdminSearch;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The quick search, from any screen: Ctrl K, or the header's search field.
 *
 * It asks nothing until it is opened — a page that never opens it pays for
 * nothing — and then whatever the running modules find, grouped, with each
 * result's own keys beside it. Before anything is typed, what the modules
 * suggest; when nothing matches, what they offer instead.
 */
new class extends Component {
    public bool $opened = false;

    public string $q = '';

    #[On('search-palette-open')]
    public function open(): void
    {
        $this->opened = true;
        $this->q = '';

        $this->dispatch('overlay-show', id: 'search-palette');
    }

    /**
     * @return list<array{label: string, results: list<\App\Admin\Search\SearchResult>}>
     */
    #[Computed]
    public function groups(): array
    {
        return $this->opened ? app(AdminSearch::class)->find($this->q, (int) config('admin.search.max_results_per_group', 8)) : [];
    }
}; ?>

<div class="modal fade" id="search-palette" tabindex="-1" aria-labelledby="search-palette-title" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-lg search-palette-dialog">
        <div class="modal-content">
            <div class="modal-header gap-2 py-2">
                <h2 class="visually-hidden" id="search-palette-title">{{ __('shell.search.title') }}</h2>
                <i class="bi bi-search fs-5 text-body-secondary" aria-hidden="true"></i>
                <label for="search-palette-input" class="visually-hidden">{{ __('shell.actions.search') }}</label>
                <input
                    id="search-palette-input"
                    type="search"
                    class="form-control form-control-lg border-0 shadow-none px-1"
                    wire:model.live.debounce.150ms="q"
                    placeholder="{{ __('shell.search.placeholder') }}"
                    autocomplete="off"
                    dir="auto"
                    data-autofocus
                    aria-describedby="search-palette-keys"
                >
                <kbd class="kbd-hint d-none d-md-inline" aria-hidden="true">Esc</kbd>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>

            <div class="modal-body p-0 search-palette-body" aria-live="polite">
                @if ($opened)
                    <div wire:loading.delay.class="is-refreshing" wire:target="q">
                        @if (trim($q) === '' && $this->groups !== [])
                            <p class="search-palette-hint">{{ __('shell.search.before_typing') }}</p>
                        @endif

                        @forelse ($this->groups as $group)
                            <section class="search-palette-group" wire:key="palette-group-{{ $loop->index }}" aria-labelledby="palette-group-{{ $loop->index }}">
                                <h3 class="search-palette-heading" id="palette-group-{{ $loop->index }}">{{ $group['label'] }}</h3>

                                <ul class="search-palette-list" role="list">
                                    @foreach ($group['results'] as $result)
                                        <li class="search-palette-item" wire:key="palette-{{ $loop->parent->index }}-{{ $loop->index }}">
                                            <a href="{{ $result->url }}" class="search-palette-link" data-palette-result>
                                                <span class="search-palette-icon" aria-hidden="true"><i class="bi bi-{{ $result->icon }}"></i></span>
                                                <span class="min-w-0 flex-grow-1">
                                                    <span class="d-block fw-semibold text-truncate" dir="auto">{{ $result->title }}</span>
                                                    @if ($result->subtitle)
                                                        <span class="d-block small text-body-secondary text-truncate" dir="auto">{{ $result->subtitle }}</span>
                                                    @endif
                                                </span>
                                                @if ($result->badge)
                                                    <span @class(['search-palette-badge num', 'flow-debit flow-text' => $result->tone === 'debit', 'flow-credit flow-text' => $result->tone === 'credit'])>{{ $result->badge }}</span>
                                                @endif
                                            </a>

                                            @if ($result->actions !== [])
                                                <span class="search-palette-actions">
                                                    @foreach ($result->actions as $action)
                                                        <a
                                                            href="{{ $action->url }}"
                                                            @class(['btn btn-sm btn-flow', 'flow-debit' => $action->tone === 'debit', 'flow-credit' => $action->tone === 'credit'])
                                                            data-palette-action="{{ $action->key }}"
                                                            aria-keyshortcuts="{{ $action->key }}"
                                                            aria-label="{{ $action->label }} — {{ $result->title }}"
                                                            tabindex="-1"
                                                        >
                                                            <i class="bi bi-{{ $action->icon }}" aria-hidden="true"></i>
                                                            <span class="d-none d-sm-inline">{{ $action->label }}</span>
                                                            <kbd class="kbd-hint d-none d-md-inline" aria-hidden="true">{{ $action->key === '-' ? '−' : $action->key }}</kbd>
                                                        </a>
                                                    @endforeach
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @empty
                            @if (trim($q) !== '')
                                <x-admin::ui.empty-state icon="search" :title="__('shell.search.nothing', ['term' => trim($q)])" />
                            @else
                                <p class="search-palette-hint">{{ __('shell.search.start') }}</p>
                            @endif
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="modal-footer justify-content-start py-2">
                <p id="search-palette-keys" class="kbd-hints kbd-hint small text-body-secondary mb-0 d-flex flex-wrap gap-3">
                    <span><kbd dir="ltr">↑ ↓</kbd> {{ __('shell.search.key_move') }}</span>
                    <span><kbd>Enter</kbd> {{ __('shell.search.key_open') }}</span>
                    <span><kbd>Esc</kbd> {{ __('shell.search.key_close') }}</span>
                </p>
            </div>
        </div>
    </div>
</div>
