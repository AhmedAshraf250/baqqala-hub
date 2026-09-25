{{--
    A data table inside a card body.

    Wrapped in .table-responsive so narrow viewports scroll the table instead of
    breaking the card, per the AdminLTE recipe.
--}}
@props([
    'striped' => true,
    'hover' => true,
])

<div class="table-responsive">
    <table {{ $attributes->class([
        'table align-middle mb-0',
        'table-striped' => $striped,
        'table-hover' => $hover,
    ]) }}>
        @if (filled($head ?? null))
            <thead>
                <tr>{{ $head }}</tr>
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>

        @if (filled($foot ?? null))
            <tfoot>{{ $foot }}</tfoot>
        @endif
    </table>
</div>
