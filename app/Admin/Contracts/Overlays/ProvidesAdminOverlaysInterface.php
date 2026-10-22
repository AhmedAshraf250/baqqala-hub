<?php

namespace App\Admin\Contracts\Overlays;

use App\Admin\Overlays\AdminOverlay;

/**
 * A module that keeps something on every admin screen, closed until asked
 * for — a drawer, a dialog — so the person can reach it from wherever they
 * are, and any screen can open it with a browser event.
 *
 * The provider only names its components; the shell places each one on the
 * page for whoever holds its permission. A component placed here renders on
 * every screen, so it reads nothing until it is opened.
 */
interface ProvidesAdminOverlaysInterface
{
    /**
     * @return list<AdminOverlay>
     */
    public function adminOverlays(): array;
}
