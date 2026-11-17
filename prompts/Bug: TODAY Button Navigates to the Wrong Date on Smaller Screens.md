# Bug: TODAY Button Navigates to the Wrong Date on Smaller Screens

## Issue

Today is **Monday, 16 November 2026**.

On smaller screen sizes, clicking the **TODAY** button sometimes navigates to **Thursday, 19 November 2026** instead of the actual current date, 16 November 2026.

This issue has been observed on:
- iPad Mini
- Other iPad screen sizes
- Mobile view
- Generally, screen sizes below Desktop

It does not happen consistently.

## Example

1. I am currently viewing **Thursday, 26 November 2026**.
2. I click the **TODAY** button.
3. Instead of navigating to **Monday, 16 November 2026**, it navigates to **Thursday, 19 November 2026**.

## Expected Behaviour

Regardless of screen size or current calendar position, clicking **TODAY** must always navigate to the actual current date.

For this example:
- Actual today: Monday, 16 November 2026.
- Expected destination: Monday, 16 November 2026.
- Incorrect destination: Thursday, 19 November 2026.

## Investigation & Fix

1. Reproduce the issue across Desktop, iPad, iPad Mini and Mobile screen sizes.
2. Investigate the TODAY button's click handler, date calculation, week navigation and responsive-specific logic.
3. Check whether the issue is related to date offsets, timezone handling, calendar week alignment, stale state or differences between Desktop and smaller-screen implementations.
4. Identify the actual root cause before making changes.
5. Fix the issue without changing unrelated calendar navigation behaviour.

## Verification

- Test clicking TODAY from different dates, including dates before and after 16 November.
- Test across all relevant screen sizes.
- Confirm that TODAY always returns to the correct current date.
- Confirm that Previous Week, Next Week and other calendar navigation functions still work correctly.

**Important:**
- Use the existing system logic and SDD if needed.
- Do not assume the issue is caused by CSS or responsive layout alone.
- Preserve existing calendar behaviour and date calculations.
- Report the root cause and the changes made.


# BUG 2 — Missing Class Event Blocks on Public Holidays (Smaller Screens)

## Screenshot Reference

`ui-prototype/my timteatble/smaller screen PH missing event block Student My Timetable.png`

## Issue

On the **Student My Timetable page**, class event blocks are missing on Public Holidays (PH) when viewing the timetable on smaller screens.

Affected screen sizes include:
- iPad and iPad Mini
- Other tablet sizes
- Mobile view
- Generally, screens below Desktop size

However, the same class event blocks are still displayed correctly on Desktop.

## Example

**Date:** 9 November 2026

There are 2 scheduled classes:
- 11:00 AM – 1:00 PM
- 2:00 PM – 3:00 PM

**Current behaviour:**
- Desktop: Both class event blocks are visible.
- Smaller screens: Both class event blocks are missing.

## Expected Behaviour

Public Holiday indicators and class event blocks should be displayed correctly across all screen sizes.

Even when a date is marked as a Public Holiday, its scheduled class event blocks should not disappear from the smaller-screen timetable.

Preserve the existing Public Holiday styling and class event information.

## Investigation & Fix

1. Reproduce the issue on Desktop, iPad, iPad Mini and Mobile view.
2. Compare the Desktop and smaller-screen rendering of Public Holiday dates.
3. Inspect the relevant HTML, CSS, JavaScript and responsive-specific rendering logic.
4. Identify why class event blocks disappear only on smaller screens.
5. Fix the actual root cause without affecting normal dates or existing timetable behaviour.

## Verification

- Confirm both classes on 9 November 2026 appear on smaller screens.
- Test other Public Holiday dates with scheduled classes.
- Confirm normal non-Public-Holiday dates still display correctly.
- Confirm Desktop rendering remains unchanged.
- Check for clipping, overlapping, hidden elements or incorrect responsive visibility.

**Important:**
- Use SDD if needed.
- Do not assume this is only a CSS visibility issue.
- Preserve existing Public Holiday and timetable business rules.
- Report the root cause and changes made.
- Do not modify unrelated pages or features.
