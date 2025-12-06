<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Agency;
use App\Models\Merchant;

class MerchantSeeder extends Seeder
{
    public function run()
    {
        // 代理店を作成
        $agencies = [
            [
                'name' => '東京美容代理店',
                'agency_code' => 'AG001',
                'postal_code1' => '150',
                'postal_code2' => '0001',
                'address' => '東京都渋谷区神宮前1-1-1',
                'email' => 'murasakiiroga.suki@gmail.com',
                'password' => Hash::make('doparking'),
            ],
            [
                'name' => '大阪美容代理店',
                'agency_code' => 'AG002',
                'postal_code1' => '530',
                'postal_code2' => '0001',
                'address' => '大阪府大阪市北区梅田1-1-1',
                'email' => 'osaka.agency@example.com',
                'password' => Hash::make('doparking'),
            ],
        ];

        $createdAgencies = [];
        foreach ($agencies as $agency) {
            $createdAgencies[] = Agency::create($agency);
        }

        // 加盟店を作成
        $merchants = [
            [
                'agency_id' => $createdAgencies[0]->id,
                'name' => 'ヘアサロン青山',
                'merchant_code' => 'MER001',
                'status' => 'active',
                'postal_code1' => '107',
                'postal_code2' => '0062',
                'address' => '東京都港区南青山2-2-2',
                'phone' => '03-1234-5678',
                'contact_person' => '田中太郎',
            ],
            [
                'agency_id' => $createdAgencies[0]->id,
                'name' => 'ビューティーサロン原宿',
                'merchant_code' => 'MER002',
                'status' => 'active',
                'postal_code1' => '150',
                'postal_code2' => '0001',
                'address' => '東京都渋谷区神宮前3-3-3',
                'phone' => '03-2345-6789',
                'contact_person' => '佐藤花子',
            ],
            [
                'agency_id' => $createdAgencies[1]->id,
                'name' => 'ヘアスタジオ梅田',
                'merchant_code' => 'MER003',
                'status' => 'active',
                'postal_code1' => '530',
                'postal_code2' => '0001',
                'address' => '大阪府大阪市北区梅田4-4-4',
                'phone' => '06-3456-7890',
                'contact_person' => '山田次郎',
            ],
        ];

        $createdMerchants = [];
        foreach ($merchants as $merchant) {
            $createdMerchants[] = Merchant::create($merchant);
        }

    }
}