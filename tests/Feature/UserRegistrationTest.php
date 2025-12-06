<?php

namespace Tests\Feature;

use App\Http\Controllers\MerchantController;
use App\Http\Controllers\UserController;
use App\Models\Merchant;
use App\Models\User;
use App\Services\LineRichMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ユーザー登録でline_idとdisplay_nameが保存される()
    {
        $lineId = 'line_' . uniqid();
        $displayName = 'テストユーザー_' . uniqid();

        // LINE プロフィールAPIをスタブ
        Http::fake([
            'https://api.line.me/v2/profile' => Http::response([
                'userId' => $lineId,
                'displayName' => $displayName,
            ], 200),
        ]);

        // リッチメニューサービスをモック
        $this->mock(LineRichMenuService::class, function ($mock) use ($lineId) {
            $mock->shouldReceive('switchRichMenu')
                ->once()
                ->with($lineId, \Mockery::type('string'))
                ->andReturn(['success' => true]);
        });

        $request = Request::create('/register', 'POST', [
            'access_token' => 'dummy_access_token',
            'name' => '表示名とは別の登録名',
        ]);

        /** @var UserController $controller */
        $controller = app(UserController::class);
        $jsonResponse = $controller->store(app(LineRichMenuService::class), $request);

        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertTrue($jsonResponse->getData(true)['success']);

        $this->assertDatabaseHas('users', [
            'line_id' => $lineId,
            'display_name' => $displayName,
            'name' => '表示名とは別の登録名',
        ]);

        $user = User::where('line_id', $lineId)->first();
        $this->assertNotNull($user);
    }

    /** @test */
    public function 登録したユーザーIDで加盟店登録ができる()
    {
        $lineId = 'line_' . uniqid();
        $displayName = 'テストユーザー_' . uniqid();

        // 1) ユーザー登録のHTTPとサービスをスタブ/モック
        Http::fake([
            'https://api.line.me/v2/profile' => Http::response([
                'userId' => $lineId,
                'displayName' => $displayName,
            ], 200),
        ]);

        $this->mock(LineRichMenuService::class, function ($mock) use ($lineId) {
            $mock->shouldReceive('switchRichMenu')
                ->once()
                ->with($lineId, \Mockery::type('string'))
                ->andReturn(['success' => true]);
        });

        $request = Request::create('/register', 'POST', [
            'access_token' => 'dummy',
            'name' => '登録ユーザー',
        ]);
        /** @var UserController $userController */
        $userController = app(UserController::class);
        $userResponse = $userController->store(app(LineRichMenuService::class), $request);
        $this->assertEquals(200, $userResponse->getStatusCode());

        $user = User::where('line_id', $lineId)->firstOrFail();

        // 2) 加盟店登録時のリッチメニュー更新もモック（2回目呼ばれる想定）
        $this->mock(LineRichMenuService::class, function ($mock) use ($lineId) {
            $mock->shouldReceive('switchRichMenu')
                ->once()
                ->with($lineId, \Mockery::type('string'))
                ->andReturn(['success' => true]);
        });

        $merchantPayload = [
            'name' => 'テスト加盟店',
            'status' => 1,
            'postal_code1' => '123',
            'postal_code2' => '4567',
            'address' => '東京都テスト区1-2-3',
            'phone' => '0312345678',
            'contact_person' => '担当 太郎',
            'user_id' => $user->id,
        ];

        $merchantRequest = Request::create('/merchants', 'POST', $merchantPayload);
        /** @var MerchantController $merchantController */
        $merchantController = app(MerchantController::class);
        $merchantResponse = $merchantController->store(app(LineRichMenuService::class), $merchantRequest);

        $this->assertEquals(200, $merchantResponse->getStatusCode());
        $this->assertTrue($merchantResponse->getData(true)['success']);

        $this->assertDatabaseHas('merchants', [
            'name' => 'テスト加盟店',
            'user_id' => $user->id,
            'status' => 1,
        ]);

        $merchant = Merchant::where('user_id', $user->id)->first();
        $this->assertNotNull($merchant);
    }
}


