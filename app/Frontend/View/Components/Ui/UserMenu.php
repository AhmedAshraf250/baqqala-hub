<?php

namespace App\Frontend\View\Components\Ui;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\FrontendUser;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * `<x-frontend::ui.user-menu placement="sidebar|header" />`: the signed-in
 * visitor, their settings, and signing out — at the foot of the sidebar on a
 * wide screen, in the header on a narrow one. One menu, two places to open it.
 */
final class UserMenu extends Component
{
    public readonly ?FrontendUser $user;

    public function __construct(public readonly string $placement = 'sidebar')
    {
        if (! in_array($placement, ['sidebar', 'header'], true)) {
            throw new InvalidArgumentException("The user menu opens from the sidebar or the header, not [{$placement}].");
        }

        $user = Area::Frontend->user();

        $this->user = $user instanceof FrontendUser ? $user : null;
    }

    public function shouldRender(): bool
    {
        return $this->user !== null;
    }

    public function render(): View
    {
        return view('frontend::ui.user-menu');
    }
}
