<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;
use App\Modules\Customers\Contracts\Enums\GroupColor;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;

/**
 * The owner's customer groups: made, renamed, recoloured, archived, restored,
 * and deleted while nobody is in them.
 *
 * Changing a group touches no customer; archiving hides it and keeps who is
 * in it. Every action asks for `customers.manage` again.
 */
new class extends Component {
    /** The group being edited, or null for a new one. */
    public ?int $editing = null;

    public string $name = '';

    public string $color = 'blue';

    public ?string $notice = null;

    /**
     * Every group, archived ones included, in the owner's order.
     *
     * @return Collection<int, CustomerGroupData>
     */
    #[Computed]
    public function groups(): Collection
    {
        return app(CustomerGroupRepositoryInterface::class)->getAll(includeArchived: true);
    }

    /**
     * @return Collection<int, CustomerGroupData>
     */
    #[Computed]
    public function active(): Collection
    {
        return $this->groups->reject(fn (CustomerGroupData $group): bool => $group->isArchived());
    }

    /**
     * @return Collection<int, CustomerGroupData>
     */
    #[Computed]
    public function archived(): Collection
    {
        return $this->groups->filter(fn (CustomerGroupData $group): bool => $group->isArchived());
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function colors(): array
    {
        return collect(GroupColor::cases())->mapWithKeys(fn (GroupColor $color): array => [$color->value => $color->label()])->all();
    }

    public function edit(int $groupId): void
    {
        $group = $this->groups->get($groupId);

        if ($group === null) {
            return;
        }

        $this->resetErrorBag();
        $this->editing = $groupId;
        $this->name = $group->name;
        $this->color = $group->color->value;
    }

    public function startNew(): void
    {
        $this->resetErrorBag();
        $this->reset('editing', 'name', 'color');
    }

    public function save(CustomerGroupRepositoryInterface $groups, AuditTrail $audit): void
    {
        $administrator = $this->authorizeManaging();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', Rule::enum(GroupColor::class)],
        ], attributes: [
            'name' => __('accounts::module.groups.name'),
            'color' => __('accounts::module.groups.color'),
        ]);

        $existing = $this->editing === null ? null : $this->groups->get($this->editing);

        $saved = $groups->save(new CustomerGroupData(
            id: $existing?->id,
            name: trim($validated['name']),
            color: GroupColor::from($validated['color']),
            icon: $existing?->icon,
            sortOrder: $existing->sortOrder ?? ((int) $this->groups->max(fn (CustomerGroupData $group): int => $group->sortOrder) + 10),
            archivedAt: $existing?->archivedAt,
            createdBy: (int) $administrator->getKey(),
        ));

        $audit->record('accounts.group', $saved->id, $saved->name, 'accounts::module.audit.group', $existing === null ? AuditAction::Created : AuditAction::Updated, AuditTrail::differences(
            $existing === null ? [] : ['name' => $existing->name, 'color' => $existing->color->labelKey()],
            ['name' => $saved->name, 'color' => $saved->color->labelKey()],
            ['name' => 'accounts::module.groups.name', 'color' => 'accounts::module.groups.color'],
            ['color'],
        ));

        $this->notice = __('accounts::module.groups.saved', ['name' => $saved->name]);
        $this->startNew();
        $this->changed();
    }

    public function archive(int $groupId, CustomerGroupRepositoryInterface $groups, AuditTrail $audit): void
    {
        $this->authorizeManaging();
        $this->resave($groups, $audit, $groupId, CarbonImmutable::now());
    }

    public function restore(int $groupId, CustomerGroupRepositoryInterface $groups, AuditTrail $audit): void
    {
        $this->authorizeManaging();
        $this->resave($groups, $audit, $groupId, null);
    }

    public function delete(int $groupId, CustomerGroupRepositoryInterface $groups, AuditTrail $audit): void
    {
        $this->authorizeManaging();

        $group = $this->groups->get($groupId);

        // The database refuses a group someone is in; the screen never offers it.
        if ($group === null || $group->memberCount !== 0) {
            return;
        }

        try {
            $groups->delete($groupId);
        } catch (QueryException) {
            return;
        }

        $audit->record('accounts.group', $groupId, $group->name, 'accounts::module.audit.group', AuditAction::Deleted);

        if ($this->editing === $groupId) {
            $this->startNew();
        }

        $this->changed();
    }

    private function resave(CustomerGroupRepositoryInterface $groups, AuditTrail $audit, int $groupId, ?CarbonImmutable $archivedAt): void
    {
        $group = $this->groups->get($groupId);

        if ($group === null) {
            return;
        }

        $groups->save(new CustomerGroupData($group->id, $group->name, $group->color, $group->icon, $group->sortOrder, $archivedAt));
        $audit->record('accounts.group', $group->id, $group->name, 'accounts::module.audit.group', $archivedAt === null ? AuditAction::Restored : AuditAction::Archived);

        $this->changed();
    }

    private function changed(): void
    {
        unset($this->groups, $this->active, $this->archived);
        $this->dispatch('groups-changed');
    }

    private function authorizeManaging(): User
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(CustomerPermission::Manage->value) === true, 403);

        return $administrator;
    }
}; ?>

<div class="modal fade" id="customer-groups" tabindex="-1" aria-labelledby="customer-groups-title" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="customer-groups-title">
                    <i class="bi bi-tags me-1" aria-hidden="true"></i>
                    {{ __('accounts::module.groups.title') }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>

            <div class="modal-body">
                <p class="small text-body-secondary">{{ __('accounts::module.groups.description') }}</p>

                @if ($notice)
                    <div class="alert alert-success py-2 small" role="status">{{ $notice }}</div>
                @endif

                <div class="row g-4">
                    <div class="col-12 col-md-7">
                        @if ($this->active->isEmpty())
                            <p class="text-body-secondary small">{{ __('accounts::module.groups.none') }}</p>
                        @else
                            <ul class="list-group" role="list">
                                @foreach ($this->active as $group)
                                    <li class="list-group-item d-flex align-items-center gap-2" wire:key="group-{{ $group->id }}">
                                        <x-accounts::admin.group-chip :group="$group" />
                                        <span class="small text-body-secondary ms-auto">{{ trans_choice('accounts::module.groups.members', $group->memberCount ?? 0, ['count' => $group->memberCount ?? 0]) }}</span>

                                        <button type="button" class="btn btn-sm btn-link" wire:click="edit({{ $group->id }})" aria-label="{{ __('accounts::module.groups.edit_name', ['name' => $group->name]) }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>

                                        @if ($group->memberCount === 0)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-link text-danger-emphasis"
                                                wire:click="delete({{ $group->id }})"
                                                data-confirm="{{ __('accounts::module.groups.delete_confirm', ['name' => $group->name]) }}"
                                                data-confirm-ok="{{ __('accounts::module.groups.delete_ok') }}"
                                                aria-label="{{ __('accounts::module.groups.delete_name', ['name' => $group->name]) }}"
                                            >
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-link" wire:click="archive({{ $group->id }})" data-confirm="{{ __('accounts::module.groups.archive_confirm', ['name' => $group->name]) }}" data-confirm-tone="warning" data-confirm-ok="{{ __('accounts::module.groups.archive_ok') }}" aria-label="{{ __('accounts::module.groups.archive_name', ['name' => $group->name]) }}">
                                                <i class="bi bi-archive" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($this->archived->isNotEmpty())
                            <h3 class="h6 mt-4 mb-1">{{ __('accounts::module.groups.archived') }}</h3>
                            <p class="small text-body-secondary">{{ __('accounts::module.groups.archived_help') }}</p>

                            <ul class="list-group" role="list">
                                @foreach ($this->archived as $group)
                                    <li class="list-group-item d-flex align-items-center gap-2" wire:key="archived-group-{{ $group->id }}">
                                        <x-accounts::admin.group-chip :group="$group" />
                                        <span class="small text-body-secondary ms-auto">{{ trans_choice('accounts::module.groups.members', $group->memberCount ?? 0, ['count' => $group->memberCount ?? 0]) }}</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="restore({{ $group->id }})" aria-label="{{ __('accounts::module.groups.restore_name', ['name' => $group->name]) }}">
                                            {{ __('accounts::module.groups.restore') }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="col-12 col-md-5">
                        <form wire:submit="save" class="border rounded-3 p-3" novalidate>
                            <h3 class="h6 mb-3">
                                {{ $editing === null ? __('accounts::module.groups.new') : __('accounts::module.groups.edit', ['name' => $this->groups->get($editing)?->name]) }}
                            </h3>

                            <div class="mb-3">
                                <label for="group-name" class="form-label">{{ __('accounts::module.groups.name') }}</label>
                                <input
                                    id="group-name"
                                    type="text"
                                    wire:model="name"
                                    dir="auto"
                                    maxlength="60"
                                    placeholder="{{ __('accounts::module.groups.name_hint') }}"
                                    @if ($errors->has('name')) aria-invalid="true" aria-describedby="group-name-error" @endif
                                    @class(['form-control', 'is-invalid' => $errors->has('name')])
                                >
                                @error('name')<div id="group-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <fieldset class="mb-3">
                                <legend class="form-label fs-6">{{ __('accounts::module.groups.color') }}</legend>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($this->colors as $value => $label)
                                        <input type="radio" class="btn-check" name="group-color" id="group-color-{{ $value }}" value="{{ $value }}" wire:model="color" wire:key="color-input-{{ $value }}">
                                        <label class="btn chip tone-{{ $value }}" for="group-color-{{ $value }}" wire:key="color-label-{{ $value }}">
                                            <i class="bi bi-check2 chip-tick" aria-hidden="true"></i>
                                            <span class="chip-dot" aria-hidden="true"></span>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                                    {{ __('accounts::module.groups.save') }}
                                </button>
                                @if ($editing !== null)
                                    <button type="button" class="btn btn-outline-secondary" wire:click="startNew">{{ __('shell.actions.cancel') }}</button>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
