{{--
    The admin area's stylesheet and script.

    AdminLTE ships separate LTR and RTL builds, and its docs are explicit that
    the two must never load together, so the reader's direction picks exactly
    one. Both admin layouts include this, so the choice is written once.

    The stylesheet leads with Cairo; `@fonts` serves its faces, so it shows on
    every machine and not only where it happens to be installed.
--}}
@fonts('cairo')
@vite([
    is_rtl() ? 'resources/css/admin/app.rtl.css' : 'resources/css/admin/app.css',
    'resources/js/admin/app.js',
])
