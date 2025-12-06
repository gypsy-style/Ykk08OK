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

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * 注文通知メールの送信テスト
     */
    public function test_order_notification_email_sent()
    {
        // テストデータを作成
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

        // メールが送信されたことを確認
        Mail::assertSent(OrderNotification::class, function ($mail) use ($agency) {
            return $mail->hasTo($agency->email);
        });
    }

    /**
     * 代理店のメールアドレスが設定されていない場合のテスト
     */
    public function test_order_notification_email_not_sent_when_agency_email_not_set()
    {
        // メールアドレスなしの代理店を作成
        $agency = Agency::factory()->create([
            'email' => null,
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

        // リレーションを読み込み
        $order->load(['agency', 'merchant']);

        // メール通知サービスを実行
        $emailService = new EmailNotificationService();
        $emailService->sendOrderNotification($order);

        // メールが送信されないことを確認
        Mail::assertNotSent(OrderNotification::class);
    }

    /**
     * 代理店が存在しない場合のテスト
     */
    public function test_order_notification_email_not_sent_when_agency_not_found()
    {
        $order = Order::factory()->create([
            'agency_id' => null,
            'merchant_id' => null,
            'total_price' => 1000,
            'status' => 1
        ]);

        // メール通知サービスを実行
        $emailService = new EmailNotificationService();
        $emailService->sendOrderNotification($order);

        // メールが送信されないことを確認
        Mail::assertNotSent(OrderNotification::class);
    }

    /**
     * メール送信エラーのテスト
     */
    public function test_order_notification_email_error_handling()
    {
        // テストデータを作成
        $agency = Agency::factory()->create([
            'email' => 'test@example.com',
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

        // リレーションを読み込み
        $order->load(['agency', 'merchant']);

        // メール送信を失敗させる
        Mail::shouldReceive('mailer')->andThrow(new \Exception('SMTP connection failed'));

        // メール通知サービスを実行（エラーが発生しても例外を投げないことを確認）
        $emailService = new EmailNotificationService();
        
        // 例外が発生しないことを確認
        $this->expectNotToPerformAssertions();
        $emailService->sendOrderNotification($order);
    }

    /**
     * 注文作成時のメール通知統合テスト
     */
    public function test_order_creation_triggers_email_notification()
    {
        // テストデータを作成
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

        // 注文作成のリクエストをシミュレート
        $orderData = [
            'user_id' => 1,
            'memo' => 'テスト注文',
            'item_number_' . $product->id => 1
        ];

        // 注文作成APIを呼び出し
        $response = $this->postJson('/api/orders', $orderData);

        // レスポンスを確認
        $response->assertStatus(200);

        // メールが送信されたことを確認
        Mail::assertSent(OrderNotification::class, function ($mail) use ($agency) {
            return $mail->hasTo($agency->email);
        });
    }
} 