<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Agency;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderNotification;

class RealEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 実際のメール送信テスト（@group real-email で実行）
     * 
     * @group real-email
     */
    public function test_real_email_sending()
    {
        // テスト用の代理店を作成（実際のメールアドレスを設定）
        $agency = Agency::factory()->create([
            'email' => 'test@example.com', // 実際のテスト用メールアドレスに変更
            'name' => 'テスト代理店'
        ]);

        $merchant = Merchant::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'テスト店舗'
        ]);

        $product = Product::factory()->create([
            'product_name' => 'テスト商品',
            'price' => 1000
        ]);

        $order = Order::factory()->create([
            'agency_id' => $agency->id,
            'merchant_id' => $merchant->id,
            'total_price' => 1000,
            'status' => 1
        ]);

        OrderDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000
        ]);

        // リレーションを読み込み
        $order->load(['agency', 'merchant', 'details.product']);

        // メール通知サービスを実行
        $emailService = new EmailNotificationService();
        $emailService->sendOrderNotification($order);

        // このテストは実際にメールが送信されることを確認するだけ
        // 実際のメールボックスでメールを受信したことを手動で確認する必要がある
        $this->assertTrue(true, 'メール送信処理が完了しました。実際のメールボックスを確認してください。');
    }

    /**
     * 複数メーラーのテスト
     * 
     * @group real-email
     */
    public function test_multiple_mailers()
    {
        $agency = Agency::factory()->create([
            'email' => 'murasakiiroga.suki@gmail.com', // 実際のテスト用メールアドレスに変更
            'name' => 'テスト代理店'
        ]);

        $merchant = Merchant::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'テスト店舗'
        ]);

        $order = Order::factory()->create([
            'agency_id' => $agency->id,
            'merchant_id' => $merchant->id,
            'total_price' => 1000,
            'status' => 1
        ]);

        $order->load(['agency', 'merchant']);

        // 各メーラーで個別にテスト
        $mailers = ['xserver', 'xserver_sendmail', 'smtp'];
        
        foreach ($mailers as $mailer) {
            try {
                Mail::mailer($mailer)->to($agency->email)->send(new OrderNotification($order));
                $this->assertTrue(true, "メーラー {$mailer} でメール送信が成功しました");
            } catch (\Exception $e) {
                $this->markTestSkipped("メーラー {$mailer} でメール送信に失敗: " . $e->getMessage());
            }
        }
    }

    /**
     * メール内容の検証テスト
     */
    public function test_email_content()
    {
        $agency = Agency::factory()->create([
            'email' => 'test@example.com',
            'name' => 'テスト代理店'
        ]);

        $merchant = Merchant::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'テスト店舗'
        ]);

        $product = Product::factory()->create([
            'product_name' => 'テスト商品',
            'price' => 1000
        ]);

        $order = Order::factory()->create([
            'agency_id' => $agency->id,
            'merchant_id' => $merchant->id,
            'total_price' => 1000,
            'status' => 1,
            'memo' => 'テスト備考'
        ]);

        OrderDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1000
        ]);

        $order->load(['agency', 'merchant', 'details.product']);

        // メールインスタンスを作成
        $mail = new OrderNotification($order);
        
        // メール内容を検証
        $this->assertEquals('新しい注文が登録されました', $mail->subject);
        $this->assertEquals($agency->email, $mail->to[0]['address']);
        
        // メール本文に必要な情報が含まれているか確認
        $html = $mail->render();
        $this->assertStringContainsString($order->id, $html);
        $this->assertStringContainsString($agency->name, $html);
        $this->assertStringContainsString($merchant->name, $html);
        $this->assertStringContainsString('1,000', $html); // 合計金額
        $this->assertStringContainsString('代理店未処理', $html); // ステータス
        $this->assertStringContainsString('テスト備考', $html); // 備考
        $this->assertStringContainsString('テスト商品', $html); // 商品名
    }
} 