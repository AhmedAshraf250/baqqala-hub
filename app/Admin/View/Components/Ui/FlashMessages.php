<?php

namespace App\Admin\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

/**
 * `<x-admin::ui.flash-messages />`: whatever the last action flashed —
 * `->with('success', __('…'))` — as dismissible alerts.
 */
final class FlashMessages extends Component
{
    /**
     * The session key each level is flashed under, and how it is shown.
     */
    private const array Levels = [
        'success' => ['variant' => 'success', 'icon' => 'check-circle'],
        'error' => ['variant' => 'danger', 'icon' => 'exclamation-octagon'],
        'warning' => ['variant' => 'warning', 'icon' => 'exclamation-triangle'],
        'status' => ['variant' => 'info', 'icon' => 'info-circle'],
    ];

    /**
     * @var list<array{variant: string, icon: string, text: string}>
     */
    public readonly array $messages;

    public function __construct(Request $request)
    {
        $messages = [];

        foreach (self::Levels as $key => $level) {
            $text = $request->hasSession() ? $request->session()->get($key) : null;

            if (is_string($text) && $text !== '') {
                $messages[] = [...$level, 'text' => $text];
            }
        }

        $this->messages = $messages;
    }

    public function render(): View
    {
        return view('admin::ui.flash-messages');
    }
}
