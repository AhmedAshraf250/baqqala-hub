<?php

namespace App\Admin\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\Fortify;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            Fortify::username() => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            Fortify::username() => __('shell.auth.email'),
            'password' => __('shell.auth.password'),
        ];
    }

    /**
     * The submitted username, normalised the way Fortify stores it.
     */
    public function username(): string
    {
        $username = $this->string(Fortify::username())->toString();

        return config('fortify.lowercase_usernames') ? mb_strtolower($username) : $username;
    }

    /**
     * The credential pair, for the failed-login event.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        return [Fortify::username() => $this->username()];
    }
}
