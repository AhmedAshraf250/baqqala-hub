{{-- The .app-footer region. --}}
<footer class="app-footer">
    <div class="float-end d-none d-sm-inline">
        {{ __('shell.layout.version', ['version' => config('foundation.version')]) }}
    </div>

    <strong>&copy; {{ now()->year }} {{ __('shell.brand.name') }}.</strong>
    {{ __('shell.layout.copyright') }}
</footer>
