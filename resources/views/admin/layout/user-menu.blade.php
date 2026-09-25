{{--
    The signed-in administrator's menu, laid out as AdminLTE's user-menu: a
    coloured header naming the person, a row of their own settings, and a
    footer with their profile at the start and signing out at the end.

    What it shows comes from App\Admin\View\Components\Layout\UserMenu.
--}}

<li class="nav-item dropdown user-menu">
    <a
        href="#"
        class="nav-link dropdown-toggle d-flex align-items-center gap-2"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="{{ $user->name }}"
    >
        <x-admin::ui.avatar :name="$user->name" size="sm" />
        <span class="d-none d-md-inline" dir="auto">{{ $user->name }}</span>
    </a>

    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
        <li class="user-header text-bg-primary">
            <x-admin::ui.avatar :name="$user->name" size="lg" variant="light" class="shadow" />

            <p>
                <span dir="auto">{{ $user->name }}</span> - {{ $position }}

                @if ($memberSince !== null)
                    <small>{{ __('shell.profile.member_since', ['date' => $memberSince]) }}</small>
                @endif
            </p>
        </li>

        <li class="user-body">
            <div class="row">
                @foreach ($personalSettings as $section)
                    <div class="col text-center">
                        <a href="{{ route('admin.settings', ['tab' => $section->key]) }}">{{ $section->title() }}</a>
                    </div>
                @endforeach
            </div>
        </li>

        <li class="user-footer">
            <a href="{{ route('admin.profile') }}" class="btn btn-outline-secondary">
                {{ __('shell.navigation.profile') }}
            </a>

            <form method="POST" action="{{ route('admin.logout') }}" class="float-end">
                @csrf

                <button type="submit" class="btn btn-outline-danger">
                    {{ __('shell.actions.logout') }}
                </button>
            </form>
        </li>
    </ul>
</li>
