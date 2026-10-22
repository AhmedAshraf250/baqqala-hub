<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;
use App\Modules\Customers\Contracts\Exceptions\PhoneAlreadyUsed;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * What the shop knows about a customer — name, phone, address, notes — and
 * the groups the owner put them in, changed behind `customers.manage`.
 *
 * Through Customers' directory, which keeps a phone number to one person; the
 * groups offered are the ones not archived, and saving them leaves an
 * archived one as it was.
 */
new class extends Component {
    public ?int $customerId = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    /**
     * @var list<int>
     */
    public array $groupIds = [];

    #[On('edit-customer')]
    public function open(int $customerId): void
    {
        $this->authorizeManaging();

        $customer = app(CustomerRepositoryInterface::class)->find($customerId);

        abort_if($customer === null, 404);

        $this->resetErrorBag();
        $this->customerId = $customerId;
        $this->name = $customer->name;
        $this->phone = (string) $customer->phone;
        $this->address = (string) $customer->address;
        $this->notes = (string) $customer->notes;
        $this->groupIds = array_map(
            fn (CustomerGroupData $group): int => (int) $group->id,
            app(CustomerGroupRepositoryInterface::class)->getForCustomers([$customerId])->get($customerId) ?? [],
        );

        $this->dispatch('overlay-show', id: 'edit-customer');
    }

    /**
     * @return Collection<int, CustomerGroupData>
     */
    #[Computed]
    public function groups(): Collection
    {
        return app(CustomerGroupRepositoryInterface::class)->getAll();
    }

    public function save(CustomerDirectoryInterface $directory, CustomerRepositoryInterface $customers, CustomerGroupRepositoryInterface $groups): void
    {
        $administrator = $this->authorizeManaging();

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

        $current = $customers->find((int) $this->customerId) ?? abort(404);

        try {
            $directory->update(new CustomerData(
                id: $current->id,
                name: trim($validated['name']),
                phone: filled($validated['phone']) ? $validated['phone'] : null,
                address: filled($validated['address']) ? $validated['address'] : null,
                notes: filled($validated['notes']) ? $validated['notes'] : null,
                userId: $current->userId,
            ));
        } catch (PhoneAlreadyUsed $taken) {
            $holder = $taken->heldByCustomerId === null ? null : $customers->find($taken->heldByCustomerId);
            $this->addError('phone', __('accounts::module.customer_form.phone_taken', ['name' => $holder->name ?? $taken->phone]));

            return;
        }

        $offered = $this->groups->keys()->map(fn (mixed $id): int => (int) $id)->all();
        $groups->saveMembership((int) $current->id, array_values(array_intersect(array_map(intval(...), $this->groupIds), $offered)), (int) $administrator->getKey());

        $this->dispatch('overlay-hide', id: 'edit-customer');
        $this->dispatch('account-changed');
    }

    private function authorizeManaging(): User
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(CustomerPermission::Manage->value) === true, 403);

        return $administrator;
    }
}; ?>

<div class="modal fade" id="edit-customer" tabindex="-1" aria-labelledby="edit-customer-title" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-dialog-scrollable">
        <form class="modal-content" wire:submit="save" novalidate>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="edit-customer-title">{{ __('accounts::module.account.edit_customer') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label for="edit-customer-name" class="form-label">{{ __('accounts::module.customer_form.name') }}</label>
                    <input id="edit-customer-name" type="text" wire:model="name" dir="auto" data-autofocus
                        @if ($errors->has('name')) aria-invalid="true" aria-describedby="edit-customer-name-error" @endif
                        @class(['form-control', 'is-invalid' => $errors->has('name')])>
                    @error('name')<div id="edit-customer-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="edit-customer-phone" class="form-label">{{ __('accounts::module.customer_form.phone') }}</label>
                    <input id="edit-customer-phone" type="tel" inputmode="tel" wire:model="phone" dir="ltr"
                        @if ($errors->has('phone')) aria-invalid="true" aria-describedby="edit-customer-phone-error" @endif
                        @class(['form-control num', 'is-invalid' => $errors->has('phone')])>
                    @error('phone')<div id="edit-customer-phone-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="edit-customer-address" class="form-label">{{ __('accounts::module.customer_form.address') }}</label>
                    <textarea id="edit-customer-address" rows="2" wire:model="address" dir="auto" class="form-control"></textarea>
                </div>

                <div class="mb-3">
                    <label for="edit-customer-notes" class="form-label">{{ __('accounts::module.customer_form.notes') }}</label>
                    <textarea id="edit-customer-notes" rows="2" wire:model="notes" dir="auto" class="form-control"></textarea>
                </div>

                @if ($this->groups->isNotEmpty())
                    <fieldset>
                        <legend class="form-label fs-6">{{ __('accounts::module.list.groups') }}</legend>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($this->groups as $group)
                                <input type="checkbox" class="btn-check" id="edit-customer-group-{{ $group->id }}" value="{{ $group->id }}" wire:model="groupIds" wire:key="edit-group-input-{{ $group->id }}">
                                <label class="btn chip tone-{{ $group->color->value }}" for="edit-customer-group-{{ $group->id }}" wire:key="edit-group-label-{{ $group->id }}" dir="auto">
                                    <i class="bi bi-check2 chip-tick" aria-hidden="true"></i><span class="chip-dot" aria-hidden="true"></span>{{ $group->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('shell.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('shell.actions.save') }}</button>
            </div>
        </form>
    </div>
</div>
