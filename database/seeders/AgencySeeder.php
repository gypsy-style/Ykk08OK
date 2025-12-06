<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;


class AgencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // サンプルデータを挿入
        DB::table('agencies')->insert([
            [
                'name' => 'テスト代理店2',
                'postal_code1' => '550',
                'postal_code2' => '0001',
                'phone' => '09012345678',
                'email' => 'agency@example.com',
                'contact_person' => '09012345678',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
