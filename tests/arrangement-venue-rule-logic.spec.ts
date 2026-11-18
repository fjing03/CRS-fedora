import { expect, test } from '@playwright/test';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';

const rule = {
  P: ['lab', 'cisco_lab'], L: ['tutorial', 'lecture_hall'], T: ['tutorial'],
  lab_access: { A: ['L'], B: ['T'], C: ['T', 'L'], D: ['X'] },
  b006_venue_code: 'B006', b006_focs_programmes: ['RSD', 'DFT', 'DSF'], b006_banned_programmes: ['RAF', 'DFT'],
};

function loadRules(map: unknown = rule) {
  const context = createContext({ window: { __ruleMap: map } });
  runInContext(readFileSync('public/js/arrangement-venue-rules.js', 'utf8'), context);
  const page = readFileSync('resources/views/livewire/replacement-arrangement.blade.php', 'utf8');
  runInContext(page.slice(page.indexOf('        function venueMatrixAllows('),
    page.indexOf('        /* ── arrangement-week-keep-on-subject-switch')), context);
  return context;
}

// SHA-256 of the ordered boolean outputs captured from both picker functions
// before the refactor (116ce1b). Cases include missing lists, fallback defaults,
// mixed/unknown cohorts, case-normalized rooms and capacity edges.
const baseline = [
  ['e74ea4440c45824b834c5de2fbbfb59fac136b5cf878ae8815c4f4f1e6a4d937', '8e403ae3ab6c962f8d904da4b9a9b9f72fb61e8239d66546d7ec65a232802d86'],
  ['5b297529efe90e66e1fe4c8f6cf3ae377db513aced587f0233e483ee4ce2e2a5', '72ea096f1687d7c1fc6fdcaab9f523b2c07749cfad8ec75968fc82763523800e'],
  ['66584682ef49c403750e3dc0232ca04c79739cb59f7ed93852143dc39b0e426f', '3fc81ff6aff2db9d958625f4c360d4c3066637210235428fef8ed68c4e26e9a1'],
  ['5b297529efe90e66e1fe4c8f6cf3ae377db513aced587f0233e483ee4ce2e2a5', '72ea096f1687d7c1fc6fdcaab9f523b2c07749cfad8ec75968fc82763523800e'],
  ['eb55d13618ce7c6388a9244e45622aa57ffb032c6317ab94734e86ae45f37bc1', 'fed08b4c837e0f5181cf55a8829a9f8a4ddd908f7b9484f30845853063eac253'],
];

test('both picker verdicts match the baseline for identical inputs', () => {
  const { b006_focs_programmes, b006_banned_programmes, ...partial } = rule;
  const { lab_access, ...noExceptions } = rule;
  const empty = { ...rule, b006_focs_programmes: [], b006_banned_programmes: [] };
  for (const [i, map] of [rule, partial, noExceptions, empty, null].entries()) {
    const picker = loadRules(map);
    const matrix: number[] = [];
    const eligibility: number[] = [];
    const rooms = [['lab', 'B009'], ['cisco_lab', 'B006'], ['tutorial', 'B006'], ['TUTORIAL', 'B002'], ['lecture_hall', 'B111'], ['', 'UNKNOWN']];
    const cohortCases = [[], ['RSD2 (S1) G1'], ['DFT2G1'], ['RAF2G1'], [''], ['RSD2G1', 'RAF2G1'], ['?', 'RAF2G1'], ['RAF2G1', '?']];
    for (const [rawRoomType, code] of rooms) for (const type of ['P', 'L', 'T', 'X']) {
      for (const course of ['A', 'B', 'C', 'D', 'Z', '']) for (const cohorts of cohortCases) {
        matrix.push(+picker.venueMatrixAllows(type, rawRoomType, code, course, cohorts));
        for (const capacity of [23, 24, 25, null, undefined]) for (const studentCount of [24, undefined]) {
          eligibility.push(+picker.venueEligible({ rawRoomType, code, capacity }, { type, code: course, cohorts, studentCount }));
        }
      }
    }
    expect([matrix, eligibility].map(values => createHash('sha256').update(values.join('')).digest('hex'))).toEqual(baseline[i]);
  }
});

test('guide separates permission, programme restrictions and capacity', () => {
  const { describe } = loadRules().ArrangementVenueRules;
  const room = { code: 'B006', rawRoomType: 'cisco_lab', capacity: 24 };
  const selected = (type: string, cohorts: string[], studentCount: number | null = 24) => ({ moduleCode: 'A', session: { type, cohorts, studentCount } });
  expect(describe(rule, room, selected('P', ['RSD2G1']))).toMatchObject({ permission: 'default', fits: true, programme: { status: 'allowed', programmes: ['RSD', 'DSF'] } });
  expect(describe(rule, room, selected('L', ['DFT2G1']))).toMatchObject({ permission: 'exception', programme: { status: 'denied' } });
  expect(describe(rule, room, selected('T', ['RSD2G1']))).toMatchObject({ permission: 'denied', programme: { status: 'allowed' } });
  expect(describe(rule, room, selected('X', ['RSD2G1']))).toMatchObject({ permission: 'unavailable' });
  expect(describe(null, room, selected('L', []))).toMatchObject({ permission: 'unavailable', defaultsAvailable: false, defaultTypes: [] });
  for (const labels of [['?', 'RAF2G1'], ['RAF2G1', '?'], ['RSD2G1', 'RAF2G1']]) {
    expect(describe(rule, room, selected('L', labels)).programme.status).toBe('denied');
  }
  for (const labels of [[], ['RSD2G1', '?']]) {
    expect(describe(rule, room, selected('L', labels)).programme.status).toBe('unavailable');
  }
  expect(describe({ ...rule, b006_focs_programmes: undefined }, room, null).programme).toEqual({ programmes: null, status: 'unavailable' });
  expect(describe({ ...rule, b006_focs_programmes: [] }, room, selected('L', ['RSD2G1'])).programme).toEqual({ programmes: [], status: 'denied' });
  expect(describe(rule, { ...room, capacity: 23 }, selected('P', [])).fits).toBe(false);
  expect(describe(rule, { ...room, capacity: 25 }, selected('P', [])).fits).toBe(true);
  expect(describe(rule, { ...room, capacity: null }, selected('P', [])).fits).toBeNull();
  expect(describe(rule, room, selected('P', [], null)).fits).toBeNull();
});

test('the picked or verified original occurrence, not the module fallback, controls the explanation', () => {
  const { occurrence } = loadRules().ArrangementVenueRules;
  const course = { code: 'A', name: 'Module A', type: 'P', sessions: { 12: { type: 'L' }, 13: { type: 'T' } } };
  const rows = [{ code: 'A', sessionId: 12, week: 1 }, { code: 'A', sessionId: 12, week: 3 }, { code: 'A', sessionId: 13, week: 3 }];
  const earlier = { sessionId: 12, week: 2 };
  expect(occurrence(course, null, null, rows, null)).toBeNull();
  expect(occurrence(course, rows[1], earlier, rows, null)).toMatchObject({ anchor: { week: 3 }, session: { type: 'L' } });
  expect(occurrence(course, rows[2], earlier, rows, null)).toMatchObject({ session: { type: 'T' } });
  expect(occurrence(course, { ...rows[1], week: 4 }, earlier, rows, null)).toBeNull();
  expect(occurrence(course, null, earlier, rows, null)).toMatchObject({ anchor: { week: 1 } });
  expect(occurrence(course, null, earlier, [], { id: 12, week: 2, code: 'A', day: 1 })).toMatchObject({ anchor: { week: 1, day: 1 } });
  expect(occurrence(course, null, earlier, [], { id: 12, week: 3, code: 'A', day: 1 })).toBeNull();
  expect(occurrence({ ...course, code: 'B' }, null, earlier, rows, { id: 12, week: 2, code: 'A' })).toBeNull();
});
