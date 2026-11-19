{{--
    The login page's daily quote (SDD `login-daily-quote`, 2026-11-19).

    Receives `$quote` from the layout — the partial never reads the clock or the
    config itself, so both login pages resolve the day exactly once.

    The avatar is decorative (`alt=""` + `aria-hidden`): the visible author name
    in the figcaption carries the attribution, and repeating it in an `alt`
    would announce the same thing twice.
--}}
@if ($quote)
    <figure class="login-quote">
        <blockquote>{{ $quote['quote'] }}</blockquote>
        <figcaption>
            {{-- The wrapper owns the hairline ring so the ink can fade to 60%
                 without fading the ring with it, and so the dark theme's
                 `invert` cannot recolour the ring. --}}
            <span class="login-quote-avatar">
                <img src="/images/quotes/{{ $quote['avatar'] }}" width="32" height="32" alt="" aria-hidden="true">
            </span>
            <span>{{ $quote['author'] }}</span>
        </figcaption>
    </figure>
@endif
