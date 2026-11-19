<?php

/*
|--------------------------------------------------------------------------
| Login Daily Quote
|--------------------------------------------------------------------------
|
| One athlete quote per ISO weekday, keyed 1 = Monday … 7 = Sunday. The key is
| the ISO weekday read on the CAMPUS clock (`App\Support\LocalTime`), not the
| UTC app clock, so the quote rolls over at 00:00 MYT. Read through
| `App\Support\LoginQuote`; the login page never touches this array directly.
|
| `avatar` is a bare filename under `public/images/quotes/` — the partial
| prefixes the root-relative path.
|
*/

return [

    1 => [
        'quote' => 'You have to fight to reach your dream. You have to sacrifice and work hard for it.',
        'author' => 'Lionel Messi',
        'avatar' => 'messi.png',
    ],

    2 => [
        'quote' => 'The dream is not the destination. It\'s the journey.',
        'author' => 'Kobe Bryant',
        'avatar' => 'kobe.png',
    ],

    3 => [
        'quote' => 'I\'ve failed over and over and over again in my life. And that is why I succeed.',
        'author' => 'Michael Jordan',
        'avatar' => 'jordan.png',
    ],

    4 => [
        'quote' => 'Success is not an accident, success is actually a choice.',
        'author' => 'Stephen Curry',
        'avatar' => 'curry.png',
    ],

    5 => [
        'quote' => 'Success isn\'t always about greatness. It\'s about consistency.',
        'author' => 'The Rock',
        'avatar' => 'the-rock.png',
    ],

    6 => [
        'quote' => 'Tired is only in the mind. You tell yourself you\'re tired, you\'re gonna be tired.',
        'author' => 'LeBron James',
        'avatar' => 'lebron.png',
    ],

    7 => [
        'quote' => 'Your love makes me strong. Your hate makes me unstoppable.',
        'author' => 'Cristiano Ronaldo',
        'avatar' => 'cr7.png',
    ],

];
