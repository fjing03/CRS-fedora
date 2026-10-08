<?php

// dataset/import/course-titles.php — real module titles for the semester-202505
// datasets. Single source; the importer never invents a title.
//
// Port of "py script/course_titles.py" (same keys/values), plus the 2026-10-08
// user-directed additions sourced from the courses registry in the user's
// Downloads/mock-data.js (codes that file printed with a real name; codes that
// printed as 'Subj <code>' there are NOT listed — they stay code-fallback):
// AMIS1012, AMIT2014, AMMS1623, AMMS3653, BBBE1033, BMCS1013, BMCS1053,
// BMCS1113, BMIT2043, BMIT2073, BMIT3173, BMMS1743, BMSE2163, BMSE3153.
//
// Provenance (py source):
//     2026-10-06  the 6 AMCS/AMIS/AMIT/AMSE/BMIT titles were supplied by the
//                 user (they appeared as '—' in the venue NOTE table before).
//     earlier     the Networking/IoT set + BMIT1173/BMIT2013/BMIS2003/BMCS3033
//                 already sat in the venue extractor for the lab-exception table.
//
// Codes NOT listed here fall back to module_name = the code itself and are
// reported by the importer (currently 8: AMSE2002, AMSE2003, AMSE2013,
// BMIT3273, MPU-2212, MPU-2302, MPU-3103, MPU-3302).

return [
    // ── supplied by the user 2026-10-06 ──────────────────────────────────
    'AMCS1013' => 'Problem Solving and Programming',
    'AMCS1043' => 'Database Development and Applications',
    'AMIS1003' => 'Introduction to Cybersecurity',
    'AMIT1303' => 'Introduction to Interface Design',
    'AMSE1003' => 'Software Engineering',
    'BMIT1723' => 'IT Fundamentals and Applications',
    // ── Networking / IoT set (lab-exception table) ───────────────────────
    'AMIT2033' => 'Networking Essentials',
    'AMIT2034' => 'Fundamentals of Computer Networks',
    'BMIT2154' => 'Switching and Routing Technologies',
    'BMIT3084' => 'Enterprise Networking',
    'BMIT2123' => 'Internet of Things',
    // ── other known titles ────────────────────────────────────────────────
    'BMIT2013' => 'Web-Based Integrated Systems',
    'BMIT1173' => 'IT Fundamentals',
    'BMIS2003' => 'Blockchain Application Development',
    'BMCS3033' => 'Social and Professional Issues',
    // ── supplied by the user 2026-10-06 (2nd batch) ──────────────────────
    'BMCS2053' => 'Object-Oriented Analysis and Design',
    'BMIS2113' => 'Information Technology Infrastructure',
    'MPU-3133' => 'Falsafah dan Isu Semasa',
    'MPU-3232' => 'Entrepreneurship',
    'BMIT2203' => 'Human Computer Interaction',
    'BMCS2063' => 'Data Structures and Algorithms',
    'AMCS2093' => 'Operating Systems',
    // ── user-directed additions 2026-10-08 (Downloads/mock-data.js) ──────
    'AMIS1012' => 'Ethics in Computing',
    'AMIT2014' => 'Web and Mobile Systems',
    'AMMS1623' => 'Calculus and Algebra',
    'AMMS3653' => 'Discrete Mathematics',
    'BBBE1033' => 'Economics',
    'BMCS1013' => 'Problem Solving and Programming',
    'BMCS1053' => 'Database Management',
    'BMCS1113' => 'Computer Organisation and Architecture',
    'BMIT2043' => 'Introduction to Internet Security',
    'BMIT2073' => 'Mobile Application Development',
    'BMIT3173' => 'Integrative Programming',
    'BMMS1743' => 'Quantitative Methods',
    'BMSE2163' => 'Software Engineering',
    'BMSE3153' => 'Software Project Management',
    // ── user-directed additions 2026-10-08 (continued) ───────────────────
    'BMIT3273' => 'Cloud Computing',
];
