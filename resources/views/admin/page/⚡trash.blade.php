<?php

use App\Admin\Authorization\AdminPermission;
use App\Admin\Trash\AdminTrash;
use App\Admin\Trash\TrashItem;
use App\Foundation\Area\Area;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The trash: what was deleted, by kind, the latest first — each brought back
 * where it was with one press, or let go of for good after a question.
 *
 * It knows nothing of what it holds. Each kind is a module's source, which
 * restores and purges by its own rules and says no when something stops it:
 * a category's product kept in the trash, a sale that still names a product.
 */
new #[Layout('admin::layout.app')] class extends Component {
    /** What the last action did, in words, and whether it went as asked. */
    public ?string $notice = null;

    public bool $noticeIsGood = true;

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('shell.navigation.trash')]);
    }

    /**
     * Each kind this administrator may see, with what it holds.
     *
     * @return list<array{source: string, label: string, items: list<TrashItem>}>
     */
    #[Computed]
    public function groups(): array
    {
        $groups = [];

        foreach (app(AdminTrash::class)->sources() as $class => $source) {
            $items = $source->items();

            if ($items !== []) {
                $groups[] = ['source' => $class, 'label' => __($source->label()), 'items' => $items];
            }
        }

        return $groups;
    }

    public function restore(string $source, int $id, string $name): void
    {
        $this->act($source, $id, $name, restore: true);
    }

    public function purge(string $source, int $id, string $name): void
    {
        $this->act($source, $id, $name, restore: false);
    }

    public function when(CarbonImmutable $moment): string
    {
        return local_time($moment)?->diffForHumans() ?? '';
    }

    /**
     * A name set apart from the sentence around it, so either script keeps
     * the words after it in their place.
     */
    public function isolated(string $text): string
    {
        return "\u{2068}{$text}\u{2069}";
    }

    private function act(string $class, int $id, string $name, bool $restore): void
    {
        abort_unless(Area::Admin->user()?->can(AdminPermission::ManageTrash->value) === true, 403);

        $source = app(AdminTrash::class)->source($class);
        $held = $source === null || ! collect($source->items())->contains(static fn (TrashItem $item): bool => $item->id === $id);

        if ($held) {
            [$this->notice, $this->noticeIsGood] = [__('shell.trash.gone'), false];

            return;
        }

        $done = $restore ? $source->restore($id) : $source->purge($id);
        $name = $this->isolated($name);

        [$this->notice, $this->noticeIsGood] = match (true) {
            $restore && $done => [__('shell.trash.restored', ['name' => $name]), true],
            $restore => [__('shell.trash.not_restored', ['name' => $name]), false],
            $done => [__('shell.trash.purged', ['name' => $name]), true],
            default => [__('shell.trash.held', ['name' => $name]), false],
        };

        unset($this->groups);
    }
}; ?>

<div class="d-grid gap-3">
    <p class="text-body-secondary mb-0">{{ __('shell.trash.intro') }}</p>

    @if ($notice)
        <x-admin::ui.alert :variant="$noticeIsGood ? 'success' : 'warning'" :icon="$noticeIsGood ? 'check-circle' : 'exclamation-triangle'" dismissible wire:key="notice-{{ md5($notice) }}">{{ $notice }}</x-admin::ui.alert>
    @endif

    @forelse ($this->groups as $group)
        <x-admin::ui.card :title="$group['label']" icon="trash3" flush wire:key="trash-{{ md5($group['source']) }}">
            <ul class="list-group list-group-flush">
                @foreach ($group['items'] as $item)
                    <li class="list-group-item trash-item" wire:key="trash-{{ md5($group['source']) }}-{{ $item->id }}">
                        <span class="trash-item-icon" aria-hidden="true"><i class="bi bi-{{ $item->icon }}"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-semibold text-break" dir="auto">{{ $item->title }}</div>
                            @if ($item->subtitle)
                                <div class="small text-body-secondary text-break" dir="auto">{{ $item->subtitle }}</div>
                            @endif
                            <div class="small text-body-secondary">
                                @if ($item->deletedBy)
                                    {{ __('shell.trash.deleted_by', ['name' => $this->isolated($item->deletedBy), 'when' => $this->when($item->deletedAt)]) }}
                                @else
                                    {{ __('shell.trash.deleted', ['when' => $this->when($item->deletedAt)]) }}
                                @endif
                            </div>
                        </div>
                        <div class="trash-item-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                wire:click="restore(@js($group['source']), {{ $item->id }}, @js($item->title))"
                                aria-label="{{ __('shell.trash.restore_named', ['name' => $item->title]) }}"
                            >
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('shell.trash.restore') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                wire:click="purge(@js($group['source']), {{ $item->id }}, @js($item->title))"
                                data-confirm="{{ __('shell.trash.purge_confirm', ['name' => $item->title]) }}"
                                aria-label="{{ __('shell.trash.purge_named', ['name' => $item->title]) }}"
                            >
                                <i class="bi bi-x-octagon me-1" aria-hidden="true"></i>{{ __('shell.trash.purge') }}
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-admin::ui.card>
    @empty
        <x-admin::ui.card>
            <x-admin::ui.empty-state icon="trash3" :title="__('shell.trash.empty_title')" :description="__('shell.trash.empty_description')" />
        </x-admin::ui.card>
    @endforelse
</div>
