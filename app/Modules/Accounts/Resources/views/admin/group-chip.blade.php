{{--
    A customer group, as a quiet pill with a dot in the colour the owner chose.

    `<x-accounts::admin.group-chip :group="$group" />` — a CustomerGroupData.
--}}
@props(['group'])

<span {{ $attributes->class(['chip', 'tone-'.$group->color->value]) }} dir="auto">
    <span class="chip-dot" aria-hidden="true"></span>
    @if ($group->icon)
        <i class="bi bi-{{ $group->icon }}" aria-hidden="true"></i>
    @endif
    {{ $group->name }}
</span>
