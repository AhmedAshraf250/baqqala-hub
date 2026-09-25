<x-admin::layout.app :title="__('shell.page.dashboard.title')">
    <p class="text-body-secondary">{{ __('shell.page.dashboard.subtitle') }}</p>

    <x-admin::ui.card :title="__('shell.page.dashboard.getting_started')" icon="rocket-takeoff">
        <x-admin::ui.empty-state
            icon="shop"
            :title="__('shell.page.dashboard.empty_title')"
            :description="__('shell.page.dashboard.empty_description')"
        />
    </x-admin::ui.card>
</x-admin::layout.app>
