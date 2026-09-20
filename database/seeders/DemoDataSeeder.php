<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ローカルで画面を確認するためのダミーデータ
 *
 * ダッシュボードの日報、サロン分析（発送件数・個数／直近注文のないサロン／日別）、
 * 加盟店一覧の並び替えと未承認表示が、どれも空にならないようにデータを作る。
 *
 * 作ったものには DEMO- / GOON-DEMO- の印を付けてあり、実行のたびに消してから
 * 作り直すので何度流しても増えない。本番では絶対に流さないこと。
 *
 * 実行: php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    /** 代理店。[印, 名前, テストフラグ] */
    private const AGENCIES = [
        ['DEMO-A1', '関東エリア代理店', 0],
        ['DEMO-A2', '関西エリア代理店', 0],
        ['DEMO-A3', '九州エリア代理店', 0],
        ['DEMO-T1', '動作確認用代理店', 1],
    ];

    /**
     * 加盟店。[印, 代理店の印, 名前, ふりがな, status, 登録が何ヶ月前か, 注文パターン, テストフラグ, 削除済みか]
     *
     * status 1 = 承認済み、2 = 未承認。
     */
    private const MERCHANTS = [
        ['DEMO-M01', 'DEMO-A1', '銀座ヘアサロン ルミエール', 'ぎんざへあさろんるみえーる', 1, 6, 'active', 0, false],
        ['DEMO-M02', 'DEMO-A1', '美容室 ソレイユ 表参道', 'びようしつそれいゆおもてさんどう', 1, 5, 'active', 0, false],
        ['DEMO-M03', 'DEMO-A1', 'Hair Atelier 恵比寿', 'へああとりええびす', 1, 5, 'active', 0, false],
        ['DEMO-M04', 'DEMO-A1', '理容室 かみや', 'りようしつかみや', 1, 4, 'm1', 0, false],
        ['DEMO-M05', 'DEMO-A1', 'サロン・ド・アヤ', 'さろんどあや', 2, 0, 'never', 0, false],
        ['DEMO-M06', 'DEMO-A2', '梅田ビューティー', 'うめだびゅーてぃー', 1, 6, 'active', 0, false],
        ['DEMO-M07', 'DEMO-A2', 'ヘアメイク なんば', 'へあめいくなんば', 1, 3, 'active', 0, false],
        ['DEMO-M08', 'DEMO-A2', '美容室 こころ', 'びようしつこころ', 1, 5, 'm2', 0, false],
        ['DEMO-M09', 'DEMO-A2', '京都サロン 和', 'きょうとさろんなごみ', 1, 2, 'active', 0, false],
        ['DEMO-M10', 'DEMO-A2', 'ネイル&ヘア ミモザ', 'ねいるあんどへあみもざ', 2, 0, 'never', 0, false],
        ['DEMO-M11', 'DEMO-A3', '博多ヘアワークス', 'はかたへあわーくす', 1, 6, 'active', 0, false],
        ['DEMO-M12', 'DEMO-A3', '天神スタイル', 'てんじんすたいる', 1, 4, 'active', 0, false],
        ['DEMO-M13', 'DEMO-A3', '美容院 なのはな', 'びよういんなのはな', 1, 6, 'm3', 0, false],
        ['DEMO-M14', 'DEMO-A3', 'サロン アルモニー', 'さろんあるもにー', 1, 1, 'active', 0, false],
        ['DEMO-M15', null, '個人サロン みどり', 'こじんさろんみどり', 1, 1, 'active', 0, false],
        ['DEMO-M16', 'DEMO-A1', '閉店したサロン', 'へいてんしたさろん', 1, 7, 'm3', 0, true],
        ['DEMO-M17', 'DEMO-T1', '動作確認サロン', 'どうさかくにんさろん', 1, 1, 'active', 1, false],
        ['DEMO-M18', 'DEMO-A3', '新規オープン ひなた', 'しんきおーぷんひなた', 1, 0, 'active', 0, false],
    ];

    /**
     * 注文パターンごとの「何日前に発送したか」
     *
     * m1/m2/m3 は「直近1／2／3ヶ月注文のないサロン」の列に出すためのもの。
     */
    private const SHIPPED_DAYS_AGO = [
        'active' => [0, 1, 2, 4, 7, 11, 16, 24, 38, 55, 80, 110, 145, 170],
        'm1' => [45, 60, 88, 120, 150],
        'm2' => [75, 100, 135, 165],
        'm3' => [125, 150, 175],
        'never' => [],
    ];

    public function run()
    {
        $this->cleanUp();

        $products = DB::table('products')->orderBy('id')->get(['id', 'price']);
        if ($products->isEmpty()) {
            $this->command->error('products が空です。先に ProductSeeder を流してください。');

            return;
        }

        $agencyIds = $this->createAgencies();
        $merchants = $this->createMerchants($agencyIds);
        $orderCount = $this->createOrders($merchants, $products);

        $this->command->info(sprintf(
            'ダミーデータを作成しました: 代理店 %d件 / 加盟店 %d件 / 注文 %d件',
            count($agencyIds),
            count($merchants),
            $orderCount
        ));
    }

    /** 前回のダミーデータを消す。印の付いたものだけを対象にする。 */
    private function cleanUp()
    {
        // 以前の検証用スクリプトが作った注文もここでまとめて片付ける
        $orderIds = DB::table('orders')
            ->where('order_number', 'like', 'GOON-DEMO-%')
            ->orWhere('order_number', 'like', 'GOON-FIX-%')
            ->pluck('id');
        DB::table('order_details')->whereIn('order_id', $orderIds)->delete();
        DB::table('orders')->whereIn('id', $orderIds)->delete();

        $merchantIds = DB::table('merchants')->where('merchant_code', 'like', 'DEMO-%')->pluck('id');
        DB::table('merchants')->whereIn('id', $merchantIds)->delete();
        DB::table('users')->where('email', 'like', '%@demo.example.com')->delete();
        DB::table('agencies')->where('agency_code', 'like', 'DEMO-%')->delete();
    }

    /** @return array<string, int> 印 => agency_id */
    private function createAgencies()
    {
        $now = Carbon::now();
        $ids = [];

        foreach (self::AGENCIES as $i => [$code, $name, $isTest]) {
            $ids[$code] = DB::table('agencies')->insertGetId([
                'agency_code' => $code,
                'is_test' => $isTest,
                'name' => $name,
                'postal_code1' => '100',
                'postal_code2' => str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
                'address' => '東京都千代田区デモ' . ($i + 1) . '-1-1',
                'phone' => '0300000' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'contact_person' => '担当 太郎',
                'email' => strtolower($code) . '@demo.example.com',
                'password' => bcrypt('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    /**
     * @param array<string, int> $agencyIds
     * @return array<int, object> 注文作成に必要な情報を持つ加盟店の配列
     */
    private function createMerchants(array $agencyIds)
    {
        $now = Carbon::now();
        $merchants = [];

        foreach (self::MERCHANTS as $i => [$code, $agencyCode, $name, $kana, $status, $monthsAgo, $pattern, $isTest, $deleted]) {
            // 新規加盟店数の表が月ごとに散らばるよう、登録日を月内でずらす
            $createdAt = $now->copy()->subMonths($monthsAgo)->startOfMonth()->addDays(($i * 3) % 25)->setTime(10, 0);

            $userId = DB::table('users')->insertGetId([
                'name' => $name . ' オーナー',
                'display_name' => $name,
                'email' => strtolower($code) . '@demo.example.com',
                'line_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $merchantId = DB::table('merchants')->insertGetId([
                'agency_id' => $agencyCode === null ? null : $agencyIds[$agencyCode],
                'name' => $name,
                'name_kana' => $kana,
                'status' => $status,
                'is_test' => $isTest,
                'member_rank' => 1,
                'merchant_code' => $code,
                'campaign_code' => null,
                'postal_code1' => '150',
                'postal_code2' => str_pad((string) (1 + $i), 4, '0', STR_PAD_LEFT),
                'address' => '東京都渋谷区デモ' . ($i + 1) . '-2-3',
                'phone' => '0900000' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'bank_account_name' => 'デモ ハナコ',
                'contact_person' => null,
                'user_id' => $userId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'deleted_at' => $deleted ? $now->copy()->subDays(20) : null,
            ]);

            $merchants[] = (object) [
                'id' => $merchantId,
                'user_id' => $userId,
                'agency_id' => $agencyCode === null ? null : $agencyIds[$agencyCode],
                'pattern' => $pattern,
                'index' => $i,
                'createdAt' => $createdAt,
            ];
        }

        return $merchants;
    }

    /**
     * 発送済みの注文と、ダッシュボードの件数用に未発送の注文を作る
     *
     * @param array<int, object> $merchants
     * @return int 作った注文数
     */
    private function createOrders(array $merchants, $products)
    {
        $today = Carbon::now()->startOfDay();
        $count = 0;

        foreach ($merchants as $merchant) {
            foreach (self::SHIPPED_DAYS_AGO[$merchant->pattern] as $i => $daysAgo) {
                // サロンごとに日付をずらして、全員が同じ日に発送したようにならないようにする。
                // 最新の1件だけは残す。これを間引くと最終注文日がずれて、
                // 直近1／2／3ヶ月の振り分けが意図と変わってしまうため。
                if ($i > 0 && ($merchant->index + $i) % 4 === 3) {
                    continue;
                }

                $shippedAt = $today->copy()->subDays($daysAgo)->setTime(9 + (($merchant->index + $i) % 8), 30);
                if ($shippedAt->lt($merchant->createdAt)) {
                    continue;
                }

                $this->insertOrder($merchant, $products, $shippedAt, 6, $shippedAt, ++$count);
            }
        }

        // ダッシュボードのステータス別件数が全部0にならないようにする
        $pending = [2, 3, 4, 5, 9];
        foreach ($merchants as $merchant) {
            if ($merchant->pattern === 'never') {
                continue;
            }
            $status = $pending[$merchant->index % count($pending)];
            $createdAt = $today->copy()->subDays($merchant->index % 5)->setTime(14, 0);
            if ($createdAt->lt($merchant->createdAt)) {
                continue;
            }

            $this->insertOrder($merchant, $products, $createdAt, $status, null, ++$count);
        }

        return $count;
    }

    private function insertOrder($merchant, $products, Carbon $at, $status, $shippedAt, $seq)
    {
        // 商品と数量はサロンと連番から決める。毎回同じデータになるように乱数は使わない
        $lineCount = 1 + (($merchant->index + $seq) % 3);
        $lines = [];
        for ($n = 0; $n < $lineCount; $n++) {
            $product = $products[($merchant->index + $seq + $n * 2) % $products->count()];
            $lines[$product->id] = 1 + (($merchant->index + $seq + $n) % 9);
        }

        $subtotal = 0;
        foreach ($lines as $productId => $quantity) {
            $subtotal += $products->firstWhere('id', $productId)->price * $quantity;
        }

        // 送料は何件かに1件だけ付ける
        $shippingFee = $seq % 3 === 0 ? 800 : 0;

        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $merchant->user_id,
            'order_number' => 'GOON-DEMO-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'agency_id' => $merchant->agency_id,
            'merchant_id' => $merchant->id,
            'total_price' => (int) round($subtotal * 1.1) + $shippingFee,
            'shipping_fee' => $shippingFee,
            'memo' => null,
            'status' => $status,
            'shipped_at' => $shippedAt,
            'is_staff_sale' => 0,
            'created_at' => $at->copy()->subDays(2),
            'updated_at' => $at,
        ]);

        foreach ($lines as $productId => $quantity) {
            DB::table('order_details')->insert([
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $products->firstWhere('id', $productId)->price,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }
}
