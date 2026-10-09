<?php

namespace Tests\Unit;

use App\Models\Merchant;
use App\Models\PaymentConfirmedLineSend;
use App\Models\User;
use App\Services\LineMessageService;
use App\Services\PaymentConfirmedLineSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentConfirmedLineSenderTest extends TestCase
{
    use RefreshDatabase;

    private function merchant($lineId = 'Uowner')
    {
        $merchant = new Merchant(['name' => 'テスト商店']);
        $merchant->id = 10;
        $merchant->setRelation('owner', $lineId === null ? null : new User(['line_id' => $lineId]));
        return $merchant;
    }

    private function line($result = ['status' => 'success'])
    {
        $line = Mockery::mock(LineMessageService::class);
        $this->app->instance(LineMessageService::class, $line);
        return $line;
    }

    /** @test */
    public function オーナーに振込確認のLINEを送り履歴を残す()
    {
        $this->line()->shouldReceive('sendMessage')->once()
            ->with('Uowner', "いつもお世話になっております。\n2026年9月分のお振込みを確認いたしました。\nお忙しい中、ご対応いただきありがとうございます。\n\n引き続き、どうぞよろしくお願いいたします。")
            ->andReturn(['status' => 'success']);

        $result = app(PaymentConfirmedLineSender::class)->send($this->merchant(), '2026-09');

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('payment_confirmed_line_sends', [
            'merchant_id' => 10, 'month' => '2026-09', 'status' => 'success', 'line_id' => 'Uowner',
        ]);
    }

    /** @test */
    public function 送信済みの月には再送しない()
    {
        PaymentConfirmedLineSend::create([
            'merchant_id' => 10, 'month' => '2026-09', 'line_id' => 'Uowner', 'status' => 'success', 'sent_at' => now(),
        ]);
        $this->line()->shouldNotReceive('sendMessage');

        $result = app(PaymentConfirmedLineSender::class)->send($this->merchant(), '2026-09');

        $this->assertTrue($result['skipped']);
    }

    /** @test */
    public function オーナーのLINE_IDがなければ送らずスキップを記録する()
    {
        $this->line()->shouldNotReceive('sendMessage');

        $result = app(PaymentConfirmedLineSender::class)->send($this->merchant(null), '2026-09');

        $this->assertTrue($result['skipped']);
        $this->assertDatabaseHas('payment_confirmed_line_sends', [
            'merchant_id' => 10, 'month' => '2026-09', 'status' => 'skipped',
        ]);
    }

    /** @test */
    public function 送信に失敗したら失敗を記録し再チェックで再送できる()
    {
        $line = $this->line();
        $line->shouldReceive('sendMessage')->once()->andReturn(['status' => 'error', 'message' => 'limit']);
        $line->shouldReceive('sendMessage')->once()->andReturn(['status' => 'success']);
        $sender = app(PaymentConfirmedLineSender::class);

        $failed = $sender->send($this->merchant(), '2026-09');
        $this->assertFalse($failed['success']);
        $this->assertDatabaseHas('payment_confirmed_line_sends', [
            'merchant_id' => 10, 'month' => '2026-09', 'status' => 'failed', 'error' => 'limit',
        ]);

        $retried = $sender->send($this->merchant(), '2026-09');
        $this->assertTrue($retried['success']);
        $this->assertSame(1, PaymentConfirmedLineSend::count());
    }
}
