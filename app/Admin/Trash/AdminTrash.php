<?php

namespace App\Admin\Trash;

use App\Admin\Contracts\Trash\ProvidesTrashInterface;
use App\Admin\Contracts\Trash\TrashSourceInterface;
use App\Foundation\Area\Area;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Container\Container;

/**
 * The trash: every source the running modules delete into, each shown only
 * to whoever may bring its things back.
 *
 * It knows nothing of what it holds. A module names its sources through
 * {@see ProvidesTrashInterface}; each lists, restores, and purges its own.
 */
#[Scoped]
final class AdminTrash
{
    /**
     * The sources this administrator may use, by class, resolved once per
     * request.
     *
     * @var array<class-string<TrashSourceInterface>, TrashSourceInterface>|null
     */
    private ?array $sources = null;

    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly Container $container,
    ) {}

    /**
     * @return array<class-string<TrashSourceInterface>, TrashSourceInterface>
     */
    public function sources(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $this->sources = [];

        $classes = $this->modules
            ->providing(ProvidesTrashInterface::class)
            ->flatMap(static fn (ProvidesTrashInterface $module): array => $module->adminTrashSources());

        foreach ($classes as $class) {
            $source = $this->container->make($class);

            if ($source->permission() === null || Area::Admin->user()?->can($source->permission()->value) === true) {
                $this->sources[$class] = $source;
            }
        }

        return $this->sources;
    }

    /**
     * One source by its class, when this administrator may use it — the
     * class arrives from the browser, so nothing outside the list is made.
     */
    public function source(string $class): ?TrashSourceInterface
    {
        return $this->sources()[$class] ?? null;
    }
}
