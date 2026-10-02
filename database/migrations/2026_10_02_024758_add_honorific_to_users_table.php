<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Known Malaysian/academic honorific prefixes used across the dataset. */
    private const HONORIFICS = ['Dr', 'Prof', 'Ts', 'Pn', 'En', 'Cik', 'Ir', 'Mr', 'Mrs', 'Ms'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('honorific', 20)->nullable()->after('name');
        });

        $this->splitExistingNames();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('honorific')
            ->update([
                'name' => DB::raw("honorific || ' ' || name"),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('honorific');
        });
    }

    /**
     * Move a leading honorific out of users.name into users.honorific.
     * Rows without a known prefix (e.g. "Student 25DFT0001") are left untouched.
     */
    private function splitExistingNames(): void
    {
        $pattern = '/^('.implode('|', self::HONORIFICS).')(\.?)\s+(.+)$/u';

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) use ($pattern) {
            foreach ($users as $user) {
                if (! preg_match($pattern, $user->name, $matches)) {
                    continue;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'honorific' => $matches[1].$matches[2],
                    'name' => $matches[3],
                ]);
            }
        });
    }
};
