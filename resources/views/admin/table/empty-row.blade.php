{{-- The "nothing here yet" row, spanning the whole table. --}}
@props([
    'colspan',
    'message' => null,
    'icon' => 'inbox',
])

<tr>
    <td colspan="{{ $colspan }}" class="text-center text-body-secondary py-4">
        <i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>
        {{ $message ?? __('shell.table.empty') }}
    </td>
</tr>
