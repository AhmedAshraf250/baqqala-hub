<?php

namespace App\Frontend\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

/**
 * `<x-frontend::ui.auth-session-status />`: the message a sign-in screen was
 * sent back with — "we have emailed your reset link", say.
 */
final class AuthSessionStatus extends Component
{
    public readonly ?string $status;

    public function __construct(Request $request)
    {
        $status = $request->hasSession() ? $request->session()->get('status') : null;

        $this->status = is_string($status) && $status !== '' ? $status : null;
    }

    public function shouldRender(): bool
    {
        return $this->status !== null;
    }

    public function render(): View
    {
        return view('frontend::ui.auth-session-status');
    }
}
