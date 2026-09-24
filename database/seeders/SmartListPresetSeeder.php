<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\SmartList\SmartListService;
use Illuminate\Database\Seeder;

class SmartListPresetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first();
        app(SmartListService::class)->seedPresets($admin);
    }
}
