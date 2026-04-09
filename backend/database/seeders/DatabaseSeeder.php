<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@biblio.local');
        $adminPassword = env('ADMIN_PASSWORD', 'admin12345');

        User::query()->firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin',
                'password' => Hash::make($adminPassword),
                'role' => User::ROLE_ADMIN,
            ]
        );

        // Default rules (can be changed via /api/admin/settings)
        Setting::put('loan.max_days', 30);
        Setting::put('loan.grace_days', 0);
        Setting::put('loan.block_on_overdue', true);
        Setting::put('loan.block_on_unpaid_fines', true);
        Setting::put('loan.max_unpaid_fines_cents', 0); // 0 = any unpaid fine blocks

        Setting::put('fine.per_day_cents', 100); // 1.00 per day
        Setting::put('fine.cap_cents', 3000); // 30.00 max

        Setting::put('reminder.due_soon_days_before', 3);
        Setting::put('reminder.overdue_frequency_days', 7);
    }
}
