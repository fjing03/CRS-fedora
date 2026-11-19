<?php

namespace Tests\Feature\Auth;

use App\Support\LocalTime;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SDD `login-daily-quote`: both login pages greet the visitor with a sports
 * quote for TODAY — the day read on the CAMPUS calendar, not the UTC app clock.
 *
 * The page must render, between its title and the login form:
 *
 *   <figure class="login-quote">
 *       <blockquote>…the day's quote…</blockquote>
 *       <figcaption><img src="/images/quotes/<file>.png" …> …author…</figcaption>
 *   </figure>
 *
 * The day is the ISO weekday (1 = Monday … 7 = Sunday) resolved in
 * `App\Support\LocalTime::TZ` (Asia/Kuala_Lumpur) from `config('login-quotes.<iso>')`.
 * The app timezone stays UTC; the conversion happens at read time.
 *
 * DELIBERATELY NO DATABASE TRAITS. The login page reads no database, so this
 * test must not borrow one: `phpunit.xml` points `DB_DATABASE` at
 * `class_replacement_testing`, which holds protected manual data, and a
 * schema-rebuilding trait there would wipe it. Everything asserted below is
 * the rendered response, the config array and the files on disk.
 */
final class LoginDailyQuoteTest extends TestCase
{
    /**
     * The seven records, verbatim from the SDD brief — the test states the
     * expected content itself rather than echoing whatever config holds, so a
     * wrong quote/author/avatar pair cannot pass by agreeing with itself.
     *
     * @var array<int, array{quote: string, author: string, avatar: string}>
     */
    private const QUOTES = [
        1 => [
            'quote' => 'You have to fight to reach your dream. You have to sacrifice and work hard for it.',
            'author' => 'Lionel Messi',
            'avatar' => 'messi.png',
        ],
        2 => [
            'quote' => "The dream is not the destination. It's the journey.",
            'author' => 'Kobe Bryant',
            'avatar' => 'kobe.png',
        ],
        3 => [
            'quote' => "I've failed over and over and over again in my life. And that is why I succeed.",
            'author' => 'Michael Jordan',
            'avatar' => 'jordan.png',
        ],
        4 => [
            'quote' => 'Success is not an accident, success is actually a choice.',
            'author' => 'Stephen Curry',
            'avatar' => 'curry.png',
        ],
        5 => [
            'quote' => "Success isn't always about greatness. It's about consistency.",
            'author' => 'The Rock',
            'avatar' => 'the-rock.png',
        ],
        6 => [
            'quote' => "Tired is only in the mind. You tell yourself you're tired, you're gonna be tired.",
            'author' => 'LeBron James',
            'avatar' => 'lebron.png',
        ],
        7 => [
            'quote' => 'Your love makes me strong. Your hate makes me unstoppable.',
            'author' => 'Cristiano Ronaldo',
            'avatar' => 'cr7.png',
        ],
    ];

    /**
     * One real campus mid-day per ISO weekday: Mon 16 Nov → Sun 22 Nov 2026.
     * Mid-day keeps the test far away from the midnight boundary, which has
     * its own tests below.
     *
     * @var array<int, string>
     */
    private const WEEKDAY_DATES = [
        1 => '2026-11-16',
        2 => '2026-11-17',
        3 => '2026-11-18',
        4 => '2026-11-19',
        5 => '2026-11-20',
        6 => '2026-11-21',
        7 => '2026-11-22',
    ];

    /** @var list<string> */
    private const LOGIN_PAGES = ['/login/staff', '/login/student'];

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Freeze the clock at 10:00 MYT on the given ISO weekday. */
    private function freezeCampusMidMorning(int $isoWeekday): void
    {
        Carbon::setTestNow(Carbon::parse(self::WEEKDAY_DATES[$isoWeekday].' 10:00:00', LocalTime::TZ));
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function weekdayLoginPages(): array
    {
        $cases = [];

        foreach (self::QUOTES as $iso => $quote) {
            $weekday = Carbon::parse(self::WEEKDAY_DATES[$iso], LocalTime::TZ)->format('l');

            foreach (self::LOGIN_PAGES as $url) {
                $page = str_contains($url, 'staff') ? 'staff' : 'student';
                $cases["{$weekday} (ISO {$iso}), {$page} login"] = [$iso, $url];
            }
        }

        return $cases;
    }

    /**
     * The whole week, on both routes: each day's quote, author and avatar.
     *
     * `e($quote)` + `escape: false` is the apostrophe-safe comparison: Blade's
     * `{{ }}` prints `I&#039;ve`, so the expected value is escaped with the same
     * helper and compared raw instead of being escaped a second time.
     */
    #[DataProvider('weekdayLoginPages')]
    public function test_both_login_pages_show_the_days_quote_author_and_avatar(int $iso, string $url): void
    {
        $this->freezeCampusMidMorning($iso);

        $expected = self::QUOTES[$iso];

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('<figure class="login-quote"', false);
        $response->assertSee(e($expected['quote']), false);
        $response->assertSee($expected['author']);
        $response->assertSee('/images/quotes/'.$expected['avatar'], false);
    }

    /** The quote block belongs between the page title and the login form. */
    public function test_the_quote_sits_between_the_page_title_and_the_login_form(): void
    {
        $this->freezeCampusMidMorning(1);

        $this->get('/login/student')
            ->assertOk()
            ->assertSeeInOrder([
                '<div class="page-title"',
                '<figure class="login-quote"',
                '<form method="POST" action="/login">',
            ], false);
    }

    /**
     * The discriminator: Sundays end at 00:00 MYT, not at 00:00 UTC.
     *
     * 22 Nov 2026 15:59 UTC is Sun 23:59 MYT — still Sunday on campus, although
     * the app clock's weekday is also Sunday here… the point is the LAST MINUTE;
     * one minute later the campus clock rolls to Monday while UTC still says
     * Sunday. Both directions are asserted on /login/student.
     */
    public function test_the_last_minute_of_campus_sunday_still_shows_the_sunday_quote(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-22 15:59:00', 'UTC'));   // Sun 22 Nov, 23:59 MYT

        $response = $this->get('/login/student');

        $response->assertOk();
        $response->assertSee(e(self::QUOTES[7]['quote']), false);
        $response->assertSee('Cristiano Ronaldo');
        $response->assertDontSee('Lionel Messi');
    }

    /** The flip side: 16:00 UTC is Mon 23 Nov 00:00 MYT, so Monday's quote must take over. */
    public function test_the_first_minute_of_campus_monday_shows_the_monday_quote(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-22 16:00:00', 'UTC'));   // Mon 23 Nov, 00:00 MYT

        $response = $this->get('/login/student');

        $response->assertOk();
        $response->assertSee(e(self::QUOTES[1]['quote']), false);
        $response->assertSee('Lionel Messi');
        $response->assertDontSee('Cristiano Ronaldo');
    }

    /**
     * The raster avatars must ship in `public/images/quotes/` — the `.svg`
     * sources live in the same directory, so the `.png` suffix is asserted too.
     * The 5 KB ceiling keeps the login payload light.
     */
    public function test_all_seven_avatars_exist_as_small_png_files(): void
    {
        foreach (self::QUOTES as $iso => $quote) {
            $this->assertStringEndsWith(
                '.png',
                $quote['avatar'],
                "ISO day {$iso} must use the raster .png avatar, not the .svg source",
            );

            $path = public_path('images/quotes/'.$quote['avatar']);

            $this->assertFileExists($path, "ISO day {$iso} avatar is missing: {$path}");

            $size = filesize($path);

            $this->assertIsInt($size);
            $this->assertLessThan(5120, $size, "ISO day {$iso} avatar must be under 5 KB");
        }
    }

    /** Exactly the seven weekdays — no 0 (Sunday-as-0) and no 8th row to go blank. */
    public function test_the_quote_config_covers_exactly_the_seven_iso_weekdays(): void
    {
        $rows = config('login-quotes');

        $this->assertIsArray($rows, 'config/login-quotes.php must return the seven quote records');

        $keys = array_map(strval(...), array_keys($rows));
        sort($keys);

        $this->assertSame(
            ['1', '2', '3', '4', '5', '6', '7'],
            $keys,
            'login-quotes must be keyed by ISO weekday 1–7 and nothing else',
        );

        foreach ($rows as $iso => $row) {
            $this->assertIsArray($row, "ISO day {$iso} must be a quote record");

            foreach (['quote', 'author', 'avatar'] as $field) {
                $this->assertArrayHasKey($field, $row, "ISO day {$iso} is missing [{$field}]");
                $this->assertIsString($row[$field], "ISO day {$iso} [{$field}] must be a string");
                $this->assertNotSame('', trim($row[$field]), "ISO day {$iso} [{$field}] must not be blank");
            }
        }
    }
}
