{{-- A table header cell. `scope="col"` is set here so no caller forgets it. --}}
@props(['align' => 'start'])

<th scope="col" {{ $attributes->class(['text-'.$align]) }}>
    {{ $slot }}
</th>
