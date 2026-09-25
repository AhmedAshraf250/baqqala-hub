<?php

namespace App\Foundation\Contracts\Modules;

/**
 * A module that needs another module present to work.
 *
 * Dependencies are declared, not discovered: a reference from one module into
 * another that is not declared here fails the architecture tests, and so does a
 * cycle. If two modules each seem to need the other, one of them should be
 * listening for the other's event instead.
 */
interface DependsOnModulesInterface
{
    /**
     * The keys of the modules this one cannot work without.
     *
     * @return list<string>
     */
    public function dependsOn(): array;
}
