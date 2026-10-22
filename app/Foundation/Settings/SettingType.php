<?php

namespace App\Foundation\Settings;

/**
 * The kinds of value an owner sets from the panel, each read back as its own
 * PHP type and drawn with its own control.
 */
enum SettingType: string
{
    /** A word or a few — a name the counter sees. Read as a string, or null when left empty. */
    case Text = 'text';

    /** A whole number within bounds — days, minutes, seconds. Read as an int. */
    case Number = 'number';

    /** On or off. Read as a bool. */
    case Toggle = 'toggle';

    /** An amount of money. Read as a Money. */
    case Money = 'money';
}
