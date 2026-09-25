<x-frontend::layout.auth :title="__('shell.auth.verify_email')">
    <div class="mt-4 flex flex-col gap-6">
        <flux:text class="text-center">
            {{ __('shell.auth.verify_email_message') }}
        </flux:text>

        @if ($linkSent)
            <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                {{ __('shell.settings.verification_sent') }}
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('shell.auth.resend_verification') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                    {{ __('shell.actions.logout') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-frontend::layout.auth>
