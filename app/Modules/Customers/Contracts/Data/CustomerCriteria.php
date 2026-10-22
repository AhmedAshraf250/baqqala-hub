<?php

namespace App\Modules\Customers\Contracts\Data;

/**
 * Which customers a list narrows to, by what only this module knows: who
 * they are, and which groups the owner put them in.
 *
 * `term` is what was typed in the search box, as typed — words of the name
 * and digits of the phone, in any order and either script. `groupIds` keeps
 * customers who are in any one of those groups. Either left empty narrows
 * nothing.
 */
final readonly class CustomerCriteria
{
    /**
     * @param  list<int>  $groupIds
     */
    public function __construct(
        public ?string $term = null,
        public array $groupIds = [],
    ) {}
}
