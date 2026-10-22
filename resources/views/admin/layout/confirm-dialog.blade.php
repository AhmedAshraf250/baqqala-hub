{{--
    The one question asked before anything is deleted, archived, or undone.

    A control asks it by carrying `data-confirm="what will happen"`, and may
    name the dialog's title (`data-confirm-title`), its button
    (`data-confirm-ok`), and its tone (`data-confirm-tone`: danger, the
    default, or warning). resources/js/admin/modules/confirm.js holds the click
    back, fills this dialog, and lets the click through only on «yes».
--}}
<div class="modal fade" id="confirm-dialog" tabindex="-1" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered confirm-dialog">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <span class="confirm-dialog-icon mb-3" data-confirm-icon aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                <h2 class="h5 mb-2" id="confirm-dialog-title" data-confirm-title data-default="{{ __('shell.confirm.title') }}">{{ __('shell.confirm.title') }}</h2>
                <p class="text-body-secondary mb-0" id="confirm-dialog-message" data-confirm-message dir="auto"></p>
            </div>
            <div class="modal-footer justify-content-center gap-2 border-0 pt-0 pb-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" data-confirm-cancel>{{ __('shell.confirm.cancel') }}</button>
                <button type="button" class="btn btn-danger px-4" data-confirm-ok data-default="{{ __('shell.confirm.ok') }}">{{ __('shell.confirm.ok') }}</button>
            </div>
        </div>
    </div>
</div>
