<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LoginRetryGuidanceTest extends TestCase
{
    public static function otherAlerts(): array
    {
        return [
            'staff lockout' => ['staff', 'Account locked. Try again in 10 min. Forgot password? Reset at TARUMT intranet.', false],
            'student throttle' => ['student', 'Too many login attempts. Please try again in 60 seconds.', false],
            'session status' => ['staff', 'Your session has expired.', true],
        ];
    }

    #[DataProvider('otherAlerts')]
    public function test_other_alerts_do_not_get_ordinary_retry_guidance(string $role, string $message, bool $status): void
    {
        // Render with synthetic flash state; do not attempt authentication or lock an account.
        if ($status) {
            $this->withSession(['status' => $message]);
        }

        $this->withViewErrors($status ? [] : ['login_id' => $message])
            ->view('auth.login-'.$role)
            ->assertSeeText($message)
            ->assertDontSeeText('format and re-enter your password.')
            ->assertDontSeeText('Reset it through the TARUMT intranet.');
    }
}
