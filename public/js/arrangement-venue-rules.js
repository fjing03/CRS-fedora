// The Arrangement picker's rule predicates and its read-only guide projection.
var ArrangementVenueRules = (function () {
    const typeNames = { P: 'Practical (P)', L: 'Lecture (L)', T: 'Tutorial (T)' };
    const restrictedRoom = rule => rule.b006_venue_code || 'B006';
    const programmeCode = label => (String(label).replace(/\s+/g, '').match(/^[A-Z]+/) || [''])[0];
    const programmeAllows = (code, allow, deny) => !!code && deny.indexOf(code) === -1 && allow.indexOf(code) !== -1;
    const validCount = value => Number.isInteger(value) && value >= 0;

    function typePermission(rule, type, roomType, roomCode, moduleCode) {
        if (!rule) return 'unavailable';
        const tag = (roomType || '').toLowerCase();
        if (!Array.isArray(rule[type])) return 'unavailable';
        const base = rule[type].includes(tag);
        const additional = moduleCode ? ((rule.lab_access || {})[moduleCode] || []) : [];
        const listed = additional.indexOf(type) !== -1;
        const lab = tag === 'lab' || tag === 'cisco_lab';
        if (base) return 'default';
        if (listed && (lab || roomCode === restrictedRoom(rule))) return 'exception';
        return 'denied';
    }

    function matrixAllows(rule, type, roomType, roomCode, moduleCode, cohorts) {
        const permission = typePermission(rule, type, roomType, roomCode, moduleCode);
        if (permission !== 'default' && permission !== 'exception') return false;
        if (roomCode !== restrictedRoom(rule)) return true;
        const allow = rule.b006_focs_programmes || [];
        const deny = rule.b006_banned_programmes || [];
        const labels = cohorts || [];
        // Preserve the picker's existing short-circuit and vacuous empty-cohort pass.
        for (let i = 0; i < labels.length; i++) {
            if (!programmeAllows(programmeCode(labels[i]), allow, deny)) return false;
        }
        return true;
    }

    function programmeSummary(rule, roomCode, cohorts) {
        if (!rule || roomCode !== restrictedRoom(rule)) return null;
        const allow = rule.b006_focs_programmes;
        const deny = rule.b006_banned_programmes;
        if (!Array.isArray(allow) || !Array.isArray(deny)) return { programmes: null, status: 'unavailable' };
        const programmes = allow.filter(code => !deny.includes(code));
        if (!Array.isArray(cohorts) || !cohorts.length) return { programmes, status: 'unavailable' };
        const codes = cohorts.map(programmeCode);
        // Report a known failure even if a different cohort label could not be parsed.
        const denied = codes.some(code => code && !programmeAllows(code, allow, deny));
        return { programmes, status: denied ? 'denied' : codes.includes('') ? 'unavailable' : 'allowed' };
    }

    function occurrence(course, pick, pin, rows, handoff) {
        if (!course?.sessions) return null;
        const rowAt = (id, week) => rows.find(row => row.code === course.code && String(row.sessionId) === String(id) && row.week === week);
        let anchor;
        if (pick) {
            if (pick.code !== course.code) return null;
            anchor = rowAt(pick.sessionId, pick.week);
            // A stale explicit pick is not permission to substitute a different pin.
            if (!anchor) return null;
        } else if (pin && Number.isInteger(pin.week) && pin.week > 0) {
            const week = pin.week - 1;
            anchor = rowAt(pin.sessionId, week);
            if (!anchor && handoff && handoff.code === course.code && String(handoff.id) === String(pin.sessionId) && handoff.week === pin.week) {
                anchor = { sessionId: handoff.id, week, day: handoff.day };
            }
        }
        const session = anchor ? course.sessions[String(anchor.sessionId)] : null;
        return session ? { anchor, moduleCode: course.code, name: course.name, session } : null;
    }

    function describe(rule, room, selected) {
        const session = selected?.session;
        const seatCount = validCount(room.capacity) ? room.capacity : null;
        const studentCount = validCount(session?.studentCount) ? session.studentCount : null;
        const roomType = room.rawRoomType || '';
        const types = Object.keys(typeNames);
        const defaultsAvailable = !!roomType && types.every(type => Array.isArray(rule?.[type]));
        const defaultTypes = defaultsAvailable ? types.filter(type => rule[type].includes(roomType.toLowerCase())).map(type => typeNames[type]) : [];
        return {
            defaultTypes,
            defaultsAvailable,
            typeName: typeNames[session?.type] || 'Unknown session type',
            permission: session && roomType ? typePermission(rule, session.type, roomType, room.code, selected.moduleCode) : 'unavailable',
            programme: programmeSummary(rule, room.code, session?.cohorts),
            seatCount,
            studentCount,
            fits: seatCount === null || studentCount === null ? null : seatCount >= studentCount,
        };
    }

    return { matrixAllows, occurrence, describe };
})();
