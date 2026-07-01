<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * The endpoint is public, so anyone may submit a message.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the input before validation runs.
     *
     * Converts "smart" quotes that browsers/phones often insert into plain
     * ASCII equivalents so they aren't rejected by the ASCII-only rule below.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => str_replace(
                ['“', '”', '‘', '’'],
                ['"', '"', "'", "'"],
                (string) $this->input('message'),
            ),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Printable ASCII plus tab / carriage return / newline. Disallowing
            // other control bytes prevents raw ESC/POS commands being injected
            // into the print stream through the message field.
            'message' => ['required', 'max:1024', 'regex:/^[\x09\x0A\x0D\x20-\x7E]*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'You have to write something!',
            'message.max' => 'How did you manage to write something that long?',
            'message.regex' => 'Sorry, basic ascii characters only (bummer, I know).',
        ];
    }
}
