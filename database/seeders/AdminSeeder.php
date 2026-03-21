<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 倉庫アカウント（permission=2: ダッシュボード・受注管理のみ）
        Admin::create([
            'name' => '倉庫担当',
            'email' => 'warehouse@example.com',
            'password' => Hash::make('password'),
            'permission' => 2,
        ]);
    }
}
