<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('loan_requests', 'is_active')) {
            Schema::table('loan_requests', function (Blueprint $table) {
                // NULL = inactive (keeps history, allows multiple rows)
                // true = active (pending/approved). Unique constraint prevents duplicates.
                $table->boolean('is_active')->nullable()->after('status');
            });
        }

        // Backfill existing rows based on status.
        DB::table('loan_requests')
            ->whereNull('is_active')
            ->whereIn('status', ['pending', 'approved', 'return_requested'])
            ->update(['is_active' => true]);

        DB::table('loan_requests')
            ->whereIn('status', ['rejected', 'returned'])
            ->update(['is_active' => null]);

        // If there are already duplicates (e.g. from dev testing), keep only the latest
        // active request per (user_id, book_id) so the unique index can be created.
        $duplicates = DB::table('loan_requests')
            ->select('user_id', 'book_id', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->where('is_active', true)
            ->groupBy('user_id', 'book_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('loan_requests')
                ->where('user_id', $dup->user_id)
                ->where('book_id', $dup->book_id)
                ->where('is_active', true)
                ->where('id', '!=', $dup->keep_id)
                ->update(['is_active' => null]);
        }

        $driver = DB::getDriverName();
        $hasIndex = false;
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('loan_requests')");
            foreach ($indexes as $idx) {
                if (($idx->name ?? null) === 'loan_requests_one_active_per_user_book') {
                    $hasIndex = true;
                    break;
                }
            }
        } elseif ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT COUNT(1) as c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                ['loan_requests', 'loan_requests_one_active_per_user_book']
            );
            $hasIndex = ((int) ($row->c ?? 0)) > 0;
        }

        if (!$hasIndex) {
            Schema::table('loan_requests', function (Blueprint $table) {
                $table->unique(['user_id', 'book_id', 'is_active'], 'loan_requests_one_active_per_user_book');
            });
        }
    }

    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropUnique('loan_requests_one_active_per_user_book');
            $table->dropColumn('is_active');
        });
    }
};
