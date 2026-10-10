<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input shape for submitting a replacement request (FR 2.9). Livewire
 * components validate the payload with Validator::make($payload, static::rules())
 * (design D8); state/authorization invariants live in SubmitReplacementRequest.
 */
final class StoreReplacementRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'class_session_id' => ['required', 'integer', 'exists:class_sessions,id'],
            'week_number' => ['required', 'integer', 'min:1', 'max:14'],
            'replacement_time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
