<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DefaultPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SDD `login-access-403` (user brief 2026-11-07), part 2: the 403 page.
 *
 *  - the "Access Denied" title is white (user ruling),
 *  - "Go Back" returns the user to THEIR default page (staff / student) instead of
 *    always `/`,
 *  - and the role gates themselves are untouched: staff on a student page (and the
 *    reverse) still get 403, guests still get the login redirect.
 */
final class AccessDeniedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_on_a_student_page_gets_403_with_a_staff_way_back(): void
    {
        $staff = User::factory()->lecturer()->create();

        $response = $this->actingAs($staff)->get('/student-my-timetable-ui');

        $response->assertForbidden();
        $response->assertSee('Access Denied');
        $response->assertSee('403');
        // the way back is the STAFF default page, not `/`
        $response->assertSee('href="'.DefaultPage::STAFF.'" class="btn-back"', false);
        $this->assertSame('/my-timetable-ui', DefaultPage::STAFF);
    }

    public function test_student_on_a_staff_page_gets_403_with_a_student_way_back(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get('/replacement-arrangement');

        $response->assertForbidden();
        $response->assertSee('Access Denied');
        $response->assertSee('href="'.DefaultPage::STUDENT.'" class="btn-back"', false);
        $this->assertSame('/student-my-timetable-ui', DefaultPage::STUDENT);
    }

    public function test_the_access_denied_title_is_white(): void
    {
        $response = $this->actingAs(User::factory()->lecturer()->create())
            ->get('/student-my-timetable-ui');

        $response->assertForbidden();

        $css = $this->titleRule($response->getContent());
        $this->assertNotSame('', $css, 'the 403 page must style .error-title');
        $this->assertMatchesRegularExpression(
            '/color:\s*(#fff\b|#ffffff\b|white\b)/i',
            $css,
            '.error-title must be white (user ruling 2026-11-07)',
        );
    }

    /**
     * …and it must stay READABLE where the card is light: this standalone page
     * loads no theme stylesheet, so the "light" card shows the page's pale
     * background and white text would disappear (user's option A, 2026-11-07).
     */
    public function test_the_access_denied_title_is_dark_on_the_light_card(): void
    {
        $html = $this->actingAs(User::factory()->student()->create())
            ->get('/replacement-arrangement')
            ->assertForbidden()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/\.light\s+\.error-title\s*\{([^}]*)\}/s',
            $html,
            'the light card needs its own .error-title rule',
        );
        preg_match('/\.light\s+\.error-title\s*\{([^}]*)\}/s', $html, $m);
        $this->assertDoesNotMatchRegularExpression(
            '/color:\s*(#fff\b|#ffffff\b|white\b)/i',
            $m[1],
            'the light card\'s title must not be white — it would be invisible',
        );
    }

    /** A guest never reaches the 403 page — `auth` sends them to the login page. */
    public function test_guests_are_still_redirected_instead_of_seeing_403(): void
    {
        $this->get('/student-my-timetable-ui')->assertRedirect(route('login.student'));
    }

    /** Pull the `.error-title { … }` declaration block out of the inline <style>. */
    private function titleRule(string $html): string
    {
        if (! preg_match('/\.error-title\s*\{(.*?)\}/s', $html, $m)) {
            return '';
        }

        return $m[1];
    }
}
