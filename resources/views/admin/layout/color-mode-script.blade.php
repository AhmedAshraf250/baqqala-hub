{{--
    Applies the stored colour mode before the first paint.

    This has to run inline in <head>: deferring it to the bundle would let the
    browser paint a light page first and flash before the dark theme lands.
    The behaviour matches AdminLTE's documented toggler, including `auto`.
--}}
@php
    $storageKey = config('admin.theme_storage_key');
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
    })();
</script>
