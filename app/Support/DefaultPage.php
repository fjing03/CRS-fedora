<?php

namespace App\Support;

use App\Models\User;

/**
 * The ONE place that knows each role's default page.
 *
 * SDD login-access-403 (2026-11-07): the landing page after login, the target for
 * a signed-in user who opens a login page, and the 403 page's "Go Back" all read
 * this — a single source, so the three can never disagree.
 */
final class DefaultPage
{
    /** Staff (lecturer, PL included) land here. */
    public const STAFF = '/my-timetable-ui';

    /** Students land here. */
    public const STUDENT = '/student-my-timetable-ui';

    /** The public welcome page — guests, and any role we do not recognise. */
    public const GUEST = '/';

    public static function for(?User $user): string
    {
        if ($user === null) {
            return self::GUEST;
        }

        if ($user->isStudent()) {
            return self::STUDENT;
        }

        if ($user->isLecturer()) {
            return self::STAFF;
        }

        return self::GUEST;
    }
}
