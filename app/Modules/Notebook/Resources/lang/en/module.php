<?php

return [
    'name' => 'Notebook',

    'navigation' => [
        'notebook' => 'Notebook',
    ],

    'permissions' => [
        'write' => 'Write in the notebook',
        'review' => 'Go through the notebook and post it to accounts',
    ],

    'status' => [
        'pending' => 'Waiting to be posted',
        'posted' => 'Posted to the account',
        'discarded' => 'In the trash',
    ],

    'kind' => [
        'charge' => 'Charge',
        'payment' => 'Payment',
    ],

    // The quick note: a button on every screen.
    'capture' => [
        'open' => 'Notebook',
        'open_label' => 'Write in the notebook',
        'title' => 'Write in the notebook',
        'intro' => 'Busy? Write who took what the way you would say it, or record it by voice, then post it to the account from the notebook when it is quiet.',
        'kind' => 'What happened?',
        'body' => 'Who, what, and how much?',
        'placeholder' => 'Umm Karim oil and sugar 75',
        'hint' => 'The name first, then what was taken, then the amount if you know it. No balance changes now.',
        'save' => 'Save to the notebook',
        'or' => 'or',
        'saved' => 'Saved to the notebook. No balance has changed.',
        'go' => 'Open the notebook',
    ],

    // A note said rather than written.
    'voice' => [
        'record' => 'Record by voice',
        'stop' => 'Stop and save',
        'recording' => 'Recording — press again to save.',
        'saving' => 'Saving the recording…',
        'saved' => 'Recording saved.',
        'denied' => 'The microphone was not allowed. Allow it in the browser settings, or write the note.',
        'failed' => 'The recording could not be saved. Try again, or write the note.',
        'listen' => 'Listen to the note',
        'silent' => 'No sound was picked up — the microphone is off or too far. Nothing was saved; record again or write the note.',
        'broken' => 'The recording is damaged or too short, so it was not saved. Record again.',
        'refused' => 'What arrived is not a sound recording, so it was not saved. Record again.',
        'check' => '{0} Play the saved copy to be sure:|{1} A 1-second recording — play the saved copy to be sure:|[2,*] A :seconds-second recording — play the saved copy to be sure:',
    ],

    // Going through the notebook when the shop is quiet.
    'page' => [
        'intro' => 'What was jotted in the rush and is not on an account yet. For each note, choose whose it is: the recording form opens filled in for you to check and save. Or delete it if it was nothing.',
        'waiting' => '{0} Nothing waiting to be posted|{1} 1 note waiting to be posted|[2,*] :count notes waiting to be posted',
        'empty_title' => 'Nothing waiting to be posted',
        'empty_description' => 'Everything written has been posted. In a rush, press «Notebook» at the bottom of any screen, or the K key.',
        'written' => 'Written by :name, :when',
        'voice_only' => 'Voice note',
        'post_for' => 'Post to :name',
        'post_amount' => 'Post :amount to :name',
        'which' => 'Which one?',
        'someone_else' => 'Another customer',
        'choose' => 'Whose note is this?',
        'find_placeholder' => 'Name or part of the phone',
        'no_match' => 'No customer by that name.',
        'cancel' => 'Cancel',
        'clip_missing' => 'This note\'s voice recording is not on this computer.',
        'clip_damaged' => 'This note\'s voice recording has changed since it was saved, and may be damaged.',
        'clip_faint' => 'The voice in this recording is faint; listen closely.',
        'discard' => 'Delete it',
        'discard_confirm' => 'This note moves to the trash, words and recording, and nothing from it is posted to any account. It can be restored from the trash.',
        'discarded' => 'The note was moved to the trash.',
        'was_on' => 'Posted to :name, then undone',
        'today' => 'Gone through today',
        'posted_on' => 'Posted to :name',
        'discarded_by' => 'Deleted by :name',
        'nobody' => '—',
    ],

    // Written into the entry a note becomes, so the statement says where it came from.
    'entry_note' => 'From the notebook (:time): :text',

    'trash' => [
        'notes' => 'Notes deleted from the notebook',
        'with_voice' => 'with a voice recording',
    ],
];
