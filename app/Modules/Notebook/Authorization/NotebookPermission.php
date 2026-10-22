<?php

namespace App\Modules\Notebook\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * Who may jot in the notebook, and who goes through it.
 *
 * Jotting is for everyone at the counter in the rush. Going through it —
 * hearing the notes, throwing one away, opening the drawer on one — is for
 * whoever posts to accounts, since that is what a note becomes.
 */
enum NotebookPermission: string implements PermissionInterface
{
    case Write = 'notebook.write';

    case Review = 'notebook.review';

    public function label(): string
    {
        return __('notebook::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'notebook';
    }
}
