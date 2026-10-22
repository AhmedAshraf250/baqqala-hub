{{--
    Applies this browser's display preferences before the first paint: the
    colour mode, and whether keyboard shortcuts are shown and answered.

    This has to run inline in <head>: deferring it to the bundle would let the
    browser paint a light page first and flash before the dark theme lands.
    The behaviour matches AdminLTE's documented toggler, including `auto`.
--}}
@php
    $storageKey = config('admin.theme_storage_key');
    $shortcutsKey = config('admin.shortcuts_storage_key');
@endphp

<script>
    (() => {
        const storageKey = @json($storageKey);

        const resolve = (theme) =>
            theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)
                ? 'dark'
                : 'light';

        let stored = 'auto';

        try {
            stored = localStorage.getItem(storageKey) ?? 'auto';
        } catch {
            // Private browsing or blocked storage: fall back to the system preference.
        }

        document.documentElement.setAttribute('data-bs-theme', resolve(stored));
        document.documentElement.dataset.themeChoice = stored;
        // Hand the key to the bundle so it is configured in exactly one place.
        document.documentElement.dataset.themeStorageKey = storageKey;

        // Keyboard shortcuts, and the hints that name them: on unless this
        // browser turned them off — hidden before the first paint, never flashed.
        const shortcutsKey = @json($shortcutsKey);
        let shortcuts = 'on';

        try {
            shortcuts = localStorage.getItem(shortcutsKey) ?? 'on';
        } catch {
            // Blocked storage: the default stands.
        }

        document.documentElement.dataset.shortcuts = shortcuts === 'off' ? 'off' : 'on';
        document.documentElement.dataset.shortcutsStorageKey = shortcutsKey;
    })();
</script>
