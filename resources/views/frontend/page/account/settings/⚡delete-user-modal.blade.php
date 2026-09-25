<?php

use App\Foundation\Identity\Validation\PasswordRules;
use App\Frontend\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => PasswordRules::forCurrentPassword(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('shell.account.delete_confirm_title') }}</flux:heading>

            <flux:subheading>
                {{ __('shell.account.delete_confirm_message') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('shell.auth.password')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('shell.actions.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('shell.account.delete') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
