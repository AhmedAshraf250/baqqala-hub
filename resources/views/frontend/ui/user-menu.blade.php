{{--
    The signed-in visitor's menu. `$user` comes from
    App\Frontend\View\Components\Ui\UserMenu; `$placement` says where it
    opens from — the foot of the sidebar, or the header on a narrow screen.
--}}
<flux:dropdown
    :position="$placement === 'sidebar' ? 'bottom' : 'top'"
    :align="$placement === 'sidebar' ? 'start' : 'end'"
    {{ $attributes }}
>
    @if ($placement === 'sidebar')
        <flux:sidebar.profile
            :name="$user->name"
            :initials="$user->initials()"
            icon:trailing="chevrons-up-down"
            data-test="sidebar-menu-button"
        />
    @else
        <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />
    @endif

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar :name="$user->name" :initials="$user->initials()" />

            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                <flux:text class="truncate">{{ $user->email }}</flux:text>
            </div>
        </div>

        <flux:menu.separator />

        <flux:menu.radio.group>
            <flux:menu.item :href="route('frontend.account.settings.profile')" icon="cog" wire:navigate>
                {{ __('shell.navigation.settings') }}
            </flux:menu.item>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('shell.actions.logout') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
