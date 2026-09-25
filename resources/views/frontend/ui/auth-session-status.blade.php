{{--
    `$status` comes from App\Frontend\View\Components\Ui\AuthSessionStatus,
    which renders nothing when there is none.
--}}
<div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
    {{ $status }}
</div>
