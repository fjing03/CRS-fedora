{{--
    THE system-wide footer (SDD ui-footer-global, frozen 2026-11-01).
    ONE quiet centered strip — identity ① · version ② · owner ③ · demo notice ④
    · contact ⑤ · repo ⑥. Absolutely no logic beyond the version constant and the
    optional `fixed` switch; colors are theme.css tokens only (§10.0).

    Wired from the LAYOUTS, never per page (decision 9):
      layouts/ui-template   → @include('partials.ui-footer')            (in flow)
      layouts/login-template → @include('partials.ui-footer', ['fixed' => true])

    Single-writer law: `$version` is named HERE and nowhere else in the repo.
--}}
@php
    $version = 'v1.0';
    $fixed = $fixed ?? false;
@endphp
<footer class="ui-footer{{ $fixed ? ' ui-footer--fixed' : '' }}" role="contentinfo">
    <span class="ui-footer-brand">TAR UMT Class Replacement System</span>
    <span class="ui-footer-chip">{{ $version }}</span>
    <span class="ui-footer-owner">© 2026 Poong Foo Jing · Bachelor in Information Technology (Honours) (Software Systems Development) · TAR UMT Sabah (RSD)</span>
    <span class="ui-footer-note">Demo dataset — all names &amp; schedules simulated</span>
    <a
        class="ui-footer-link"
        href="mailto:poongfj@gmail.com"
        aria-label="Email the developer at poongfj@gmail.com"
        title="poongfj@gmail.com"
    >
        <flux:icon.mail class="ui-footer-icon" />
        <span>poongfj@gmail.com</span>
    </a>
    <a
        class="ui-footer-link"
        href="https://github.com/fjing03/CRS-fedora"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Project repository on GitHub: github.com/fjing03/CRS-fedora"
        title="https://github.com/fjing03/CRS-fedora"
    >
        <flux:icon.folder-git-2 class="ui-footer-icon" />
        <span>https://github.com/fjing03/CRS-fedora</span>
    </a>
</footer>
