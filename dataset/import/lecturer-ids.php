<?php

// dataset/import/lecturer-ids.php — short lecturer name (classroom/programme
// PDFs) -> staff ID. Single source; the importer resolves printed surnames to
// users via lecturers.staff_id.
//
// Port of "py script/lecturer_ids.py" (same 14 entries, same values).
//
// Provenance (py source):
//     2026-10-06  all 14 IDs from dataset-lecturers-schedules-202505.md (the
//                 lecturer extractor's TARGETS list). Every short name's venue
//                 block count was cross-checked against that table's Blocks
//                 column (Ellis 5, Lee 4, Muada 5, Teng 6, Patricia 5,
//                 Dr Chris 8, Sharon 1, Chang 3, Su 10, Ts Shikin 12,
//                 Rahmat 12, Jefther 11, Daniel 12, Jia Zheng 7 = 101 in-scope
//                 blocks) — exact match.
//
// Short names NOT listed here (Kenny, Paul, Noirom, Jr Kinabalu, Chan,
// Barbara Vun, Tan Ai Ping, Suzanne, Pit Kee, Jernestcia, Jamie) belong to
// out-of-scope blocks only and have no known staff ID.

return [
    'Ellis' => '2873',
    'Lee' => '3221',
    'Muada' => '3799',
    'Teng' => '3825',
    'Patricia' => '4127',
    'Dr Chris' => '4288',
    'Sharon' => '4363',
    'Chang' => '5254',
    'Su' => '5425',
    'Ts Shikin' => '5514',
    'Rahmat' => '5516',
    'Jefther' => '5599',
    'Daniel' => '5652',
    'Jia Zheng' => '5770',
];
