{{--
    The notifications bell.

    `$notifications` is empty until a module supplies it; the badge hides itself
    and the menu falls back to an empty state, so this is safe to ship now.
--}}
@php
    /** @var \Illuminate\Support\Collection<int, array{title: string, time: string, icon: string}> $notifications */
    $notifications = collect();
@endphp

<li class="nav-item dropdown">
    <a
        class="nav-link position-relative"
        href="#"
        data-bs-toggle="dropdown"
        aria-label="{{ __('shell.notifications.title') }}"
    >
        <i class="bi bi-bell-fill" aria-hidden="true"></i>

        @if ($notifications->isNotEmpty())
            <span class="navbar-badge badge text-bg-warning">{{ $notifications->count() }}</span>
        @endif
    </a>

    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
        <span class="dropdown-item dropdown-header">
            {{ trans_choice('shell.notifications.count', $notifications->count(), ['count' => $notifications->count()]) }}
        </span>

        <div class="dropdown-divider"></div>

        @forelse ($notifications as $notification)
            <a href="#" class="dropdown-item">
                <i class="bi bi-{{ $notification['icon'] }} me-2" aria-hidden="true"></i>
                {{ $notification['title'] }}
                <span class="float-end text-body-secondary fs-7">{{ $notification['time'] }}</span>
            </a>
            <div class="dropdown-divider"></div>
        @empty
            <span class="dropdown-item text-body-secondary text-center py-3">
                {{ __('shell.notifications.empty') }}
            </span>
            <div class="dropdown-divider"></div>
        @endforelse

        <a href="#" class="dropdown-item dropdown-footer">{{ __('shell.notifications.view_all') }}</a>
    </div>
</li>
