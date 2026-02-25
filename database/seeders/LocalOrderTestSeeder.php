<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Merchant;

class LocalOrderTestSeeder extends Seeder
{
    public function run()
    {
        $dummyLineId = env('DUMMY_LINE_ID', 'DUMMY_LINE_ID');

        // ダミーオーナーユーザー（LINEログイン不要のローカルテスト用）
        $user = User::updateOrCreate(
            ['email' => 'test-owner@example.com'],
            [
                'name' => 'テストオーナー',
                'display_name' => 'テストオーナー',
                'line_id' => $dummyLineId,
                'richmenu_id' => 'RICHMENU_ID_2',
            ]
        );

        // オーナーに紐づく加盟店（order/storeでMerchant::where('user_id', $userId)->first() で引けるようにする）
        Merchant::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'テスト店舗',
                'status' => 1,
                'member_rank' => 1,
                'postal_code1' => '150',
                'postal_code2' => '0001',
                'address' => '東京都渋谷区テスト1-1-1',
                'phone' => '0311112222',
                'contact_person' => null,
                'merchant_code' => 'TEST-MERCHANT',
                'agency_id' => null,
            ]
        );
    }
}


