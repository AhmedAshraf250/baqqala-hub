{{--
    Renders one-off session messages as dismissible Bootstrap alerts.

    Controllers and actions flash with `->with('success', __('...'))`; nothing
    needs to render alerts by hand. `$messages` comes from
    App\Admin\View\Components\Ui\FlashMessages.
--}}
@foreach ($messages as $message)
    <x-admin::ui.alert :variant="$message['variant']" :icon="$message['icon']" dismissible>
        {{ $message['text'] }}
    </x-admin::ui.alert>
@endforeach
