{{--
    What the running modules keep on every screen: each a Livewire component,
    closed until its own button or a browser event opens it.

    `$components` comes from App\Admin\View\Components\Layout\Overlays.
--}}
@foreach ($components as $component)
    <livewire:dynamic-component :is="$component" :key="'overlay-'.$component" />
@endforeach
