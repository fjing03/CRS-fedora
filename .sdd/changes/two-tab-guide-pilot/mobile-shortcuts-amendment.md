# Hide Shortcuts in the mobile timetable

User approved hiding the tab on touch/mobile and explicitly requested no Playwright run after applying. Use the existing Arrangement mobile timetable breakpoint (≤768px), where the desktop grid shortcuts are inactive, rather than hiding it on a touch-capable laptop whose desktop grid still supports shortcuts. Only the optional Shortcuts tab is affected.

Use CSS for immediate visibility. GuideModal selects and cycles only rendered tabs; if the viewport shrinks while Shortcuts is selected, return to Getting started so no hidden tab owns the visible panel or focus. The desktop `?` shortcut keeps working, the help icon still defaults to Getting started, and the other pages retain their existing guide behavior. Update the existing test expectations for future runs, but verify this change only with static checks as requested.

Independent proposal review passed. `node --check public/js/ui-common.js` and `git diff --check` passed; Playwright was not run.
