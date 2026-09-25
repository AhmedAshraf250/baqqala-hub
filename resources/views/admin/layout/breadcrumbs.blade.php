{{--
    The breadcrumb trail, derived from the navigation tree.

    Nothing writes a trail by hand — a page cannot claim a parent it does not
    have. A leading home link is a convention, not a hierarchy claim, so
    Settings reads as `⌂ / Settings` rather than pretending to sit under the
    dashboard.

    A screen may suppress the trail entirely (`:breadcrumbs="false"`) when it
    is a single destination with its own internal navigation, such as Settings.

    `$trail` comes from App\Admin\View\Components\Layout\Breadcrumbs, which
    renders nothing when it is empty.
--}}
<nav aria-label="{{ __('shell.layout.breadcrumb') }}">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.home') }}" aria-label="{{ __('shell.navigation.home') }}">
                <i class="bi bi-house-door" aria-hidden="true"></i>
            </a>
        </li>

        @foreach ($trail as $item)
            @php($isLast = $loop->last)

            <li @class(['breadcrumb-item', 'active' => $isLast]) @if ($isLast) aria-current="page" @endif>
                @if ($isLast || $item->route === null)
                    {{ $item->title() }}
                @else
                    <a href="{{ $item->url() }}">{{ $item->title() }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
