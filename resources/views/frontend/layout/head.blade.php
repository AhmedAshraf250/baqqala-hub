<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
{{-- This area's own token. Without it, script that posts (passkeys) falls
     back to the XSRF-TOKEN cookie, which is one cookie for the whole site and
     holds whichever area's token was issued last. --}}
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.__('shell.brand.name') : __('shell.brand.name') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])
@fluxAppearance
