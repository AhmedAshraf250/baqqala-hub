<?php

use App\Foundation\Area\Area;
use App\Foundation\Text\PhoneNumber;
use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;
use App\Modules\Customers\Contracts\Exceptions\PhoneAlreadyUsed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Adding someone at the counter, while they wait: a name, and a phone if
 * they have one — the rest can come later.
 *
 * Through Customers' directory, which keeps one phone number to one person:
 * a number someone already has is refused with who has it, and the form
 * offers to show them instead.
 */
new class extends Component {
    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    /** Who already has the phone typed, when the save was refused for it. */
    public ?string $holderPhone = null;

    public ?string $holderName = null;

    /**
     * Open the form with what was searched for and not found: digits are a
     * phone, anything else a name.
     */
    #[On('new-customer-prefill')]
    public function prefill(string $term): void
    {
        $this->resetForm();

        $term = trim($term);
        $isNumber = preg_match('/^[\d\s\-+().٠-٩۰-۹]+$/u', $term) === 1 && PhoneNumber::normalize($term) !== null;

        $isNumber ? $this->phone = $term : $this->name = $term;

        $this->dispatch('overlay-show', id: 'new-customer');
    }

    public function save(CustomerDirectoryInterface $directory, CustomerRepositoryInterface $customers): void
    {
        abort_unless(Area::Admin->user()?->can(CustomerPermission::Create->value) === true, 403);

        $this->reset('holderPhone', 'holderName');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'name' => __('accounts::module.customer_form.name'),
            'phone' => __('accounts::module.customer_form.phone'),
            'address' => __('accounts::module.customer_form.address'),
            'notes' => __('accounts::module.customer_form.notes'),
        ]);

        try {
            $customer = $directory->add(new CustomerData(
                id: null,
                name: trim($validated['name']),
                phone: filled($validated['phone']) ? $validated['phone'] : null,
                address: filled($validated['address']) ? $validated['address'] : null,
                notes: filled($validated['notes']) ? $validated['notes'] : null,
            ));
        } catch (PhoneAlreadyUsed $taken) {
            $holder = $taken->heldByCustomerId === null ? null : $customers->find($taken->heldByCustomerId);
            $this->holderPhone = $taken->phone;
            $this->holderName = $holder?->name;
            $this->addError('phone', __('accounts::module.customer_form.phone_taken', ['name' => $holder->name ?? $taken->phone]));

            return;
        }

        $this->resetForm();
        $this->dispatch('overlay-hide', id: 'new-customer');
        $this->dispatch('customer-added', term: $customer->phone ?? $customer->name, notice: __('accounts::module.customer_form.added', ['name' => $customer->name]));
    }

    /**
     * Show whoever already has the number, instead of adding them twice.
     */
    public function showHolder(): void
    {
        $phone = $this->holderPhone;

        $this->resetForm();
        $this->dispatch('overlay-hide', id: 'new-customer');

        if ($phone !== null) {
            $this->dispatch('customer-added', term: $phone);
        }
    }

    private function resetForm(): void
    {
        $this->reset();
        $this->resetErrorBag();
    }
}; ?>

<div class="modal fade" id="new-customer" tabindex="-1" aria-labelledby="new-customer-title" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-dialog-scrollable">
        <form class="modal-content" wire:submit="save" novalidate>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="new-customer-title">
                    <i class="bi bi-person-plus me-1" aria-hidden="true"></i>
                    {{ __('accounts::module.customer_form.title') }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label for="new-customer-name" class="form-label">
                        {{ __('accounts::module.customer_form.name') }}
                        <span class="text-danger-emphasis" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="new-customer-name"
                        type="text"
                        wire:model="name"
                        dir="auto"
                        autocomplete="off"
                        required
                        data-autofocus
                        @if ($errors->has('name')) aria-invalid="true" aria-describedby="new-customer-name-error" @endif
                        @class(['form-control form-control-lg', 'is-invalid' => $errors->has('name')])
                    >
                    @error('name')<div id="new-customer-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="new-customer-phone" class="form-label">{{ __('accounts::module.customer_form.phone') }}</label>
                    <input
                        id="new-customer-phone"
                        type="tel"
                        inputmode="tel"
                        wire:model="phone"
                        dir="ltr"
                        autocomplete="off"
                        aria-describedby="new-customer-phone-help @if ($errors->has('phone')) new-customer-phone-error @endif"
                        @if ($errors->has('phone')) aria-invalid="true" @endif
                        @class(['form-control form-control-lg num', 'is-invalid' => $errors->has('phone')])
                    >
                    @error('phone')
                        <div id="new-customer-phone-error" class="invalid-feedback">
                            {{ $message }}
                            @if ($holderPhone)
                                <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-danger-emphasis" wire:click="showHolder">
                                    {{ __('accounts::module.customer_form.phone_taken_open', ['name' => $holderName ?? $holderPhone]) }}
                                </button>
                            @endif
                        </div>
                    @enderror
                    <div id="new-customer-phone-help" class="form-text">{{ __('accounts::module.customer_form.phone_hint') }}</div>
                </div>

                <details>
                    <summary class="small fw-semibold mb-2">{{ __('accounts::module.customer_form.more') }}</summary>

                    <div class="mb-3">
                        <label for="new-customer-address" class="form-label">{{ __('accounts::module.customer_form.address') }}</label>
                        <textarea id="new-customer-address" rows="2" wire:model="address" dir="auto" @class(['form-control', 'is-invalid' => $errors->has('address')])></textarea>
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label for="new-customer-notes" class="form-label">{{ __('accounts::module.customer_form.notes') }}</label>
                        <textarea id="new-customer-notes" rows="2" wire:model="notes" dir="auto" @class(['form-control', 'is-invalid' => $errors->has('notes')])></textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </details>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('shell.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <span class="spinner-border spinner-border-sm me-1" aria-hidden="true" wire:loading wire:target="save"></span>
                    {{ __('accounts::module.customer_form.save') }}
                </button>
            </div>
        </form>
    </div>
</div>
