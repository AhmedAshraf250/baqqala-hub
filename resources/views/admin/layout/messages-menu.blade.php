{{--
    The messages menu.

    Same shape as the notifications bell, and empty for now: there is no way
    yet for a module to supply messages. That is a capability interface in
    app/Admin/Contracts, like navigation, to be added with the first module
    that has something to say here.
--}}
@php
    /** @var \Illuminate\Support\Collection<int, array{from: string, excerpt: string, time: string}> $messages */
    $messages = collect();
@endphp

<li class="nav-item dropdown">
    <a
        class="nav-link position-relative"
        href="#"
        data-bs-toggle="dropdown"
        aria-label="{{ __('shell.messages.title') }}"
    >
        <i class="bi bi-chat-text-fill" aria-hidden="true"></i>

        @if ($messages->isNotEmpty())
            <span class="navbar-badge badge text-bg-danger">{{ $messages->count() }}</span>
        @endif
    </a>

    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
        <span class="dropdown-item dropdown-header">
            {{ trans_choice('shell.messages.count', $messages->count(), ['count' => $messages->count()]) }}
        </span>

        <div class="dropdown-divider"></div>

        @forelse ($messages as $message)
            <a href="#" class="dropdown-item">
                <h3 class="dropdown-item-title">
                    {{ $message['from'] }}
                    <span class="float-end text-body-secondary fs-7">{{ $message['time'] }}</span>
                </h3>
                <p class="fs-7 text-body-secondary mb-0">{{ $message['excerpt'] }}</p>
            </a>
            <div class="dropdown-divider"></div>
        @empty
            <span class="dropdown-item text-body-secondary text-center py-3">
                {{ __('shell.messages.empty') }}
            </span>
            <div class="dropdown-divider"></div>
        @endforelse

        <a href="#" class="dropdown-item dropdown-footer">{{ __('shell.messages.view_all') }}</a>
    </div>
</li>
