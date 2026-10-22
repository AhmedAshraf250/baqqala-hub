<?php

namespace App\Admin\Search;

use App\Admin\Contracts\Search\ProvidesAdminSearchInterface;
use App\Admin\Contracts\Search\SearchSourceInterface;
use App\Foundation\Area\Area;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Container\Container;

/**
 * The header's quick search: every source the running modules offer, then
 * the shell's own screens, each group shown only to whoever may see it.
 *
 * It knows nothing of what it finds. A module names its sources through
 * {@see ProvidesAdminSearchInterface}; each answers in words and links.
 */
#[Scoped]
final class AdminSearch
{
    /**
     * The sources this administrator may see, resolved once per request.
     *
     * @var list<SearchSourceInterface>|null
     */
    private ?array $sources = null;

    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly Container $container,
    ) {}

    /**
     * What matches the words typed, by group; before anything is typed, what
     * the sources suggest; when nothing matches, what they offer instead.
     *
     * @return list<array{label: string, results: list<SearchResult>}>
     */
    public function find(string $term, int $limit): array
    {
        $term = trim($term);

        if (mb_strlen($term) < (int) config('admin.search.min_query_length', 2)) {
            return $this->groups(static fn (SearchSourceInterface $source): array => $source->suggestions($limit));
        }

        $found = $this->groups(static fn (SearchSourceInterface $source): array => $source->search($term, $limit));

        return $found !== [] ? $found : $this->groups(static function (SearchSourceInterface $source) use ($term): array {
            $offer = $source->fallback($term);

            return $offer === null ? [] : [$offer];
        });
    }

    /**
     * @return list<SearchSourceInterface>
     */
    public function sources(): array
    {
        return $this->sources ??= array_values(array_filter(
            array_map(
                fn (string $class): SearchSourceInterface => $this->container->make($class),
                [
                    ...$this->modules
                        ->providing(ProvidesAdminSearchInterface::class)
                        ->flatMap(static fn (ProvidesAdminSearchInterface $module): array => $module->adminSearchSources())
                        ->all(),
                    ScreenSearch::class,
                ],
            ),
            static fn (SearchSourceInterface $source): bool => $source->permission() === null
                || Area::Admin->user()?->can($source->permission()->value) === true,
        ));
    }

    /**
     * @param  callable(SearchSourceInterface): list<SearchResult>  $ask
     * @return list<array{label: string, results: list<SearchResult>}>
     */
    private function groups(callable $ask): array
    {
        $groups = [];

        foreach ($this->sources() as $source) {
            $results = $ask($source);

            if ($results !== []) {
                $groups[] = ['label' => __($source->label()), 'results' => $results];
            }
        }

        return $groups;
    }
}
