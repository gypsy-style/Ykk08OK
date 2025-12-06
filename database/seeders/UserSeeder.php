<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Agency;
use App\Models\Merchant;

class UserSeeder extends Seeder
{
    public function run()
    {
        // 代理店とマーチャントを取得
        $agencies = Agency::all();
        $merchants = Merchant::all();

        $users = [
            [
                'name' => '田中太郎',
                'email' => 'tanaka@example.com',
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 0 ? $agencies[0]->id : null,
                'merchant_id' => $merchants->count() > 0 ? $merchants[0]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
            [
                'name' => '佐藤花子',
                'email' => 'sato@example.com', 
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 0 ? $agencies[0]->id : null,
                'merchant_id' => $merchants->count() > 1 ? $merchants[1]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
            [
                'name' => '山田次郎',
                'email' => 'yamada@example.com',
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 1 ? $agencies[1]->id : null,
                'merchant_id' => $merchants->count() > 2 ? $merchants[2]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
            [
                'name' => '鈴木一郎',
                'email' => 'suzuki@example.com',
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 0 ? $agencies[0]->id : null,
                'merchant_id' => $merchants->count() > 0 ? $merchants[0]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
            [
                'name' => '高橋美香',
                'email' => 'takahashi@example.com',
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 1 ? $agencies[1]->id : null,
                'merchant_id' => $merchants->count() > 2 ? $merchants[2]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
            [
                'name' => '伊藤健太',
                'email' => 'ito@example.com',
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 0 ? $agencies[0]->id : null,
                'merchant_id' => $merchants->count() > 1 ? $merchants[1]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
            [
                'name' => '渡辺さくら',
                'email' => 'watanabe@example.com',
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 1 ? $agencies[1]->id : null,
                'merchant_id' => $merchants->count() > 2 ? $merchants[2]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
            [
                'name' => '中村雄太',
                'email' => 'nakamura@example.com',
                'line_id' => 'line_' . rand(1000000, 9999999),
                'agency_id' => $agencies->count() > 0 ? $agencies[0]->id : null,
                'merchant_id' => $merchants->count() > 0 ? $merchants[0]->id : null,
                'richmenu_id' => 'menu_' . rand(1000, 9999),
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}