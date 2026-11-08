<?php

namespace Tests\Unit;

use App\Models\Module;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * User request 2026-11-08 — a subject without a real name reads "Subject AMSE2003".
 *
 * Four seeded modules store their code as the name (AMSE2002, AMSE2003,
 * AMSE2013, MPU-2302), so pages that print `CODE` + name read
 * "AMSE2003 AMSE2003 (L)". Module::displayName() is the one rule every display
 * payload reads.
 *
 * Pure unit test (no DB): the model is built from attributes.
 */
final class ModuleDisplayNameTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function cases(): array
    {
        return [
            'a real name is kept' => ['AMSE2003', 'Software Engineering', 'Software Engineering'],
            'the code repeated as the name' => ['AMSE2003', 'AMSE2003', 'Subject AMSE2003'],
            'an empty name' => ['MPU-2302', '', 'Subject MPU-2302'],
            'a whitespace-only name' => ['AMSE2013', "   \t", 'Subject AMSE2013'],
            'surrounding whitespace is trimmed' => ['AMSE2002', '  Data Structures  ', 'Data Structures'],
            'a hyphenated code keeps its form' => ['MPU-3232', 'MPU-3232', 'Subject MPU-3232'],
        ];
    }

    #[DataProvider('cases')]
    public function test_the_display_name_falls_back_to_the_code(string $code, string $name, string $expected): void
    {
        $module = new Module(['module_code' => $code, 'module_name' => $name]);

        $this->assertSame($expected, $module->displayName());
    }

    public function test_a_module_without_a_code_prints_nothing_rather_than_a_bare_prefix(): void
    {
        $module = new Module(['module_code' => '', 'module_name' => '']);

        $this->assertSame('', $module->displayName());
    }
}
