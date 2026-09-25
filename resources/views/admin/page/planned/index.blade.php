<x-admin::layout.app>
    <x-admin::ui.card>
        <x-admin::ui.empty-state
            icon="cone-striped"
            :title="__('shell.planned.title')"
            :description="__($description)"
        >
            <x-admin::ui.badge variant="secondary" icon="hourglass-split">
                {{ __('shell.planned.badge') }}
            </x-admin::ui.badge>
        </x-admin::ui.empty-state>
    </x-admin::ui.card>
</x-admin::layout.app>
