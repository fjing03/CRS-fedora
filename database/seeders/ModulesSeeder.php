<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['BMIT1010', 'Introduction to Computing',           'L,T,P'],
            ['BMIT1111', 'Operating Systems',                   'L,T,P'],
            ['BMIT1234', 'Data Structures',                     'L,T,P'],
            ['BMIT2020', 'Programming Fundamentals',            'L,T,P'],
            ['BMIT2222', 'Mathematics for Computing',           'L,T'],
            ['BMIT3030', 'Data Structures',                     'L,T,P'],
            ['BMIT3333', 'Embedded Systems',                    'L,T,P'],
            ['BMIT3344', 'Embedded Systems',                    'L,T,P'],
            ['BMIT3456', 'Artificial Intelligence',             'L,T'],
            ['BMIT4040', 'Web Development',                     'L,T,P'],
            ['BMIT4433', 'Information Security',                'L,T'],
            ['BMIT4567', 'Web Development',                     'L,T,P'],
            ['BMIT5050', 'Database Design',                     'L,T,P'],
            ['BMIT5555', 'Software Engineering',                'L,T'],
            ['BMIT5678', 'Database Systems',                    'L,T,P'],
            ['BMIT6060', 'Cybersecurity Fundamentals',          'L,T'],
            ['BMIT6061', 'UI/UX Design',                       'L,T,P'],
            ['BMIT6062', 'Networking Basics',                   'L,T,P'],
            ['BMIT6767', 'Object-Oriented Programming',         'L,T,P'],
            ['BMIT7070', 'Advanced Software Engineering',       'L,T'],
            ['BMIT7071', 'Research Methods',                    'L,T'],
            ['BMIT7072', 'Capstone Project',                    'L'],
            ['BMIT7073', 'IT Ethics',                           'L,T'],
            ['BMIT7074', 'Software Testing',                    'L,T,P'],
            ['BMIT7075', 'Mobile Application Development',      'L,T,P'],
            ['BMIT7777', 'Cybersecurity',                       'L,T'],
            ['BMIT7890', 'Project Management',                  'L,T'],
            ['BMIT8080', 'Cloud Architecture',                  'L,T'],
            ['BMIT8888', 'Cloud Computing',                     'L,T,P'],
            ['BMIT9012', 'Computer Networks',                   'L,T,P'],
            ['BMIT9999', 'Machine Learning',                    'L,T'],
            ['MPU-3133', 'Falsafah dan Isu Semasa',             'L,T'],
            ['MPU-3232', 'Entrepreneurship',                    'L,T'],
            ['COM1001',  'Introduction to Mass Communication',  'L,T'],
            ['COM2002',  'Journalism',                           'L,T'],
            ['COM3003',  'Public Relations',                     'L,T'],
        ];

        foreach ($modules as [$code, $name, $allowed]) {
            DB::table('modules')->insert([
                'module_code' => $code,
                'module_name' => $name,
                'allowed_session_types' => $allowed,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
