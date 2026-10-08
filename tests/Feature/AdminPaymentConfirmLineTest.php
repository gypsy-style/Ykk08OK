<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Merchant;
use App\Models\User;
use App\Services\LineMessageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminPaymentConfirmLineTest extends TestCase
{
    use RefreshDatabase;

    private $merchant;
    private $line;
    private $month;

    protected function setUp(): void
    {
        parent::setUp();
        // ローカルの APP_URL は本番（/ykk08ok 付き）なので、URL がルートに当たるよう固定する
        $this->app['url']->forceRootUrl('http://localhost');

        // UserFactory は users テーブルに無い password を入れるので使わない
        $owner = User::create(['name' => 'オーナー', 'email' => 'owner@example.com', 'line_id' => 'Uowner']);
        $this->merchant = Merchant::create([
            'name' => 'テスト商店',
            'status' => 1,
            'postal_code1' => '123',
            'postal_code2' => '4567',
            'address' => '東京都渋谷区テスト1-2-3',
            'phone' => '03-1234-5678',
            'user_id' => $owner->id,
        ]);
        $this->month = Carbon::now()->subMonth()->format('Y-m');

        $this->line = Mockery::mock(LineMessageService::class);
        $this->app->instance(LineMessageService::class, $this->line);

        $admin = Admin::create([
            'name' => '管理者', 'email' => 'admin@example.com', 'password' => 'x', 'permission' => 1,
        ]);
        $this->actingAs($admin, 'admin');
    }

    private function toggle()
    {
        return $this->postJson(
            route('admin.sales.payment_confirm', ['merchant' => $this->merchant->id]),
            ['month' => $this->month]
        );
    }

    /** @test */
    public function 振込確認するとオーナーにLINEが送られる()
    {
        $this->line->shouldReceive('sendMessage')->once()->with('Uowner', Mockery::type('string'))
            ->andReturn(['status' => 'success']);

        $this->toggle()
            ->assertOk()
            ->assertJson(['confirmed' => true, 'line' => ['success' => true]]);

        $this->assertDatabaseHas('merchant_payment_confirmations', ['merchant_id' => $this->merchant->id, 'month' => $this->month]);
        $this->assertDatabaseHas('payment_confirmed_line_sends', ['merchant_id' => $this->merchant->id, 'status' => 'success']);
    }

    /** @test */
    public function 外しても付け直してもLINEは1回だけ()
    {
        $this->line->shouldReceive('sendMessage')->once()->andReturn(['status' => 'success']);

        $this->toggle()->assertJson(['confirmed' => true]);
        $this->toggle()->assertJson(['confirmed' => false]);
        $this->toggle()->assertJson(['confirmed' => true, 'line' => ['skipped' => true]]);
    }

    /** @test */
    public function LINE送信に失敗しても振込確認は保存される()
    {
        $this->line->shouldReceive('sendMessage')->once()->andReturn(['status' => 'error', 'message' => 'limit']);

        $this->toggle()
            ->assertOk()
            ->assertJson(['confirmed' => true, 'line' => ['success' => false, 'skipped' => false]]);

        $this->assertDatabaseHas('merchant_payment_confirmations', ['merchant_id' => $this->merchant->id, 'month' => $this->month]);
        $this->assertDatabaseHas('payment_confirmed_line_sends', ['merchant_id' => $this->merchant->id, 'status' => 'failed']);
    }

    /** @test */
    public function 当月は振込確認もLINEもしない()
    {
        $this->line->shouldNotReceive('sendMessage');
        $this->month = Carbon::now()->format('Y-m');

        $this->toggle()->assertStatus(400);
    }
}
