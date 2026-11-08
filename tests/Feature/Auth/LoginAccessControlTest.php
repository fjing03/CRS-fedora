<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\DefaultPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SDD `login-access-403` (user brief 2026-11-07), part 1: a signed-in user must
 * never see a login page again — both login routes carry the `guest` middleware,
 * whose redirect target is the role's default page (`App\Support\DefaultPage`,
 * wired in bootstrap/app.php's `redirectUsersTo`). Guests keep both pages.
 */
final class LoginAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function loginPages(): array
    {
        return [
            'staff login page' => ['/login/staff'],
            'student login page' => ['/login/student'],
        ];
    }

    #[DataProvider('loginPages')]
    public function test_guests_still_see_both_login_pages(string $url): void
    {
        $this->get($url)->assertOk();
    }

    #[DataProvider('loginPages')]
    public function test_a_signed_in_staff_member_is_sent_to_the_staff_default_page(string $url): void
    {
        $staff = User::factory()->lecturer()->create();

        $this->actingAs($staff)->get($url)->assertRedirect(DefaultPage::STAFF);
        $this->assertSame('/my-timetable-ui', DefaultPage::STAFF);
    }

    #[DataProvider('loginPages')]
    public function test_a_signed_in_student_is_sent_to_the_student_default_page(string $url): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get($url)->assertRedirect(DefaultPage::STUDENT);
        $this->assertSame('/student-my-timetable-ui', DefaultPage::STUDENT);
    }

    /** Fortify's own /login follows the same role-aware target (guest middleware). */
    public function test_fortify_login_view_also_sends_signed_in_users_to_their_default_page(): void
    {
        $this->actingAs(User::factory()->lecturer()->create())
            ->get('/login')
            ->assertRedirect(DefaultPage::STAFF);

        $this->actingAs(User::factory()->student()->create())
            ->get('/login')
            ->assertRedirect(DefaultPage::STUDENT);
    }

    /** The map itself: staff, student, guest. */
    public function test_the_default_page_map_is_staff_student_and_guest(): void
    {
        $this->assertSame('/my-timetable-ui', DefaultPage::for(User::factory()->lecturer()->create()));
        $this->assertSame('/student-my-timetable-ui', DefaultPage::for(User::factory()->student()->create()));
        $this->assertSame('/', DefaultPage::for(null));
    }
}
