<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            AdminSeeder::class,
            AgencySeeder::class,
            ProductSeeder::class,
            // 以下は既存スキーマと不整合（UserSeederはusers.agency_id未定義、MerchantSeederはuser_id必須だが未設定等）。
            // 修正後に有効化する。
            // UserSeeder::class,
            // MerchantSeeder::class,
        ]);
    }
}
