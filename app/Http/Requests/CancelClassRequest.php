<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input shape for cancelling a class (FR 2.16). The reason whitelist mirrors
 * the class_exceptions CHECK; ownership + duplicate-occurrence checks live in
 * CancelClass.
 */
final class CancelClassRequest extends FormRequest
{
    public const REASONS = ['public_holiday', 'annual_leave', 'medical_leave', 'official_event', 'emergency_leave'];

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'class_session_id' => ['required', 'integer', 'exists:class_sessions,id'],
            'week_number' => ['required', 'integer', 'min:1', 'max:14'],
            'reason' => ['required', 'string', 'in:'.implode(',', self::REASONS)],
        ];
    }
}
