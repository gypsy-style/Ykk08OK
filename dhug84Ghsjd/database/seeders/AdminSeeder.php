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
        $admins = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => '管理者太郎',
                'email' => 'admin.taro@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '管理者花子',
                'email' => 'admin.hanako@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => 'システム管理者',
                'email' => 'system@example.com',
                'password' => Hash::make('system123'),
            ],
        ];

        foreach ($admins as $admin) {
            Admin::create($admin);
        }
    }
}