# Proposal: Venue room_name column

## Intent
D8 (FYP 4.3 ERD brief) locks Venue attributes as `name, capacity, room_type, allowed_session_type`; FR 4.2 requires storing each room's "room name". Live `venues` table has only `room_code` — the ERD/report cannot show a `name` attribute that does not exist. Student ruled 2026-08-24: add it.

## Scope
### In Scope
- New migration `2026_08_24_000002_add_room_name_to_venues_table.php`: `$table->string('room_name', 60)->nullable()`; `down()` drops the column
- `app/Models/Venue.php`: `#[Fillable]` += `room_name`; docblock `@property string|null $room_name`
- `database/seeders/VenuesSeeder.php`: backfill pattern names derived from type + code (`Tutorial Room B100`, `Lecture Hall B110`, `Computer Lab B009`, `Cisco Lab B006`) via match expression
- Docs: CodingMAIN §5 delta line + page-changelogs entry

### Out of Scope
- No consumer changes (ApiReadController/engine keep selecting `room_code` until Sprint 3 UI wiring)
- No NOT NULL, no index, no UI work
- Real FOCS display names can replace pattern values later by editing seeder only

## Capabilities
New: none. Modified: none at spec level (attribute addition to existing venue catalog).

## Approach
Single nullable-column migration; seeder derives name from existing tuple fields (5-line diff, no 23-tuple rewrite).

## Affected Areas
| Path | Change |
| --- | --- |
| `database/migrations/2026_08_24_000002_add_room_name_to_venues_table.php` | New |
| `app/Models/Venue.php` | Fillable + docblock |
| `database/seeders/VenuesSeeder.php` | insert payload += room_name |
| `CodingMAIN.md` §5, `page-changelogs/backend-automated-by-ai.md` | docs |

## Risks
- Pattern names are placeholders, not official FOCS labels → flagged for student replacement
- Nullable means NULL rows possible outside seeder path → acceptable (report reads attribute presence, not population)

## Rollback Plan
`php artisan migrate:rollback --step=1`; code reverts via git.

## Dependencies
Green baseline after db-optimization-pass1 (uncommitted in tree — will coexist; commit split decided by student).

## Success Criteria
- [ ] `\d venues` shows `room_name varchar(60) NULL`
- [ ] migrate:fresh --seed → all 23 rows have non-null room_name following type+code pattern
- [ ] Pint clean on touched files
- [ ] rollback --step=1 removes column cleanly
