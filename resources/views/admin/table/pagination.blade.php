{{--
    Pages for a Livewire list: `{{ $rows->links('admin::table.pagination') }}`.

    In place of Livewire's own Bootstrap view, whose summary is untranslated
    and whose grey fails contrast. Buttons rather than links: the list is
    redrawn where it is, and its address follows through the component's
    #[Url] page.
--}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('shell.pagination.label') }}" class="d-flex flex-wrap align-items-center gap-2">
        <p class="small text-body-secondary mb-0">
            {{ __('shell.pagination.summary', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>

        <ul class="pagination pagination-sm mb-0 ms-auto" role="list">
            <li @class(['page-item', 'disabled' => $paginator->onFirstPage()])>
                <button
                    type="button"
                    class="page-link"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    aria-label="{{ __('shell.pagination.previous') }}"
                    @disabled($paginator->onFirstPage())
                >
                    <i class="bi bi-chevron-{{ is_rtl() ? 'right' : 'left' }}" aria-hidden="true"></i>
                </button>
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-hidden="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li
                            @class(['page-item', 'active' => $page === $paginator->currentPage()])
                            wire:key="page-{{ $paginator->getPageName() }}-{{ $page }}"
                        >
                            <button
                                type="button"
                                class="page-link num"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                aria-label="{{ __('shell.pagination.page', ['page' => $page]) }}"
                                @if ($page === $paginator->currentPage()) aria-current="page" @endif
                            >{{ $page }}</button>
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li @class(['page-item', 'disabled' => ! $paginator->hasMorePages()])>
                <button
                    type="button"
                    class="page-link"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    aria-label="{{ __('shell.pagination.next') }}"
                    @disabled(! $paginator->hasMorePages())
                >
                    <i class="bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }}" aria-hidden="true"></i>
                </button>
            </li>
        </ul>
    </nav>
@endif
