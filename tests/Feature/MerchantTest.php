<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // テスト用ダミーのline_id（ローカル環境用）
        $this->line_id = 'line_' . rand(1000000, 9999999);
    }

    /** @test */
    public function 加盟店を作成できる()
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create();

        $merchantData = [
            'agency_id' => $agency->id,
            'name' => 'テスト加盟店',
            'merchant_code' => 'TEST001',
            'status' => 'active',
            'postal_code1' => '123',
            'postal_code2' => '4567',
            'address' => '東京都渋谷区テスト1-2-3',
            'phone' => '03-1234-5678',
            'contact_person' => '田中太郎',
            'user_id' => $user->id,
        ];

        $merchant = Merchant::create($merchantData);

        $this->assertDatabaseHas('merchants', [
            'name' => 'テスト加盟店',
            'merchant_code' => 'TEST001',
            'status' => 'active',
        ]);

        $this->assertEquals($agency->id, $merchant->agency_id);
        $this->assertEquals($user->id, $merchant->user_id);
    }

    /** @test */
    public function 加盟店を更新できる()
    {
        $merchant = Merchant::factory()->create([
            'name' => '元の店名',
            'merchant_code' => 'OLD001',
        ]);

        $merchant->update([
            'name' => '更新後の店名',
            'merchant_code' => 'NEW001',
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('merchants', [
            'id' => $merchant->id,
            'name' => '更新後の店名',
            'merchant_code' => 'NEW001',
            'status' => 'inactive',
        ]);
    }

    /** @test */
    public function 加盟店をソフトデリートできる()
    {
        $merchant = Merchant::factory()->create();

        $merchant->delete();

        $this->assertSoftDeleted('merchants', [
            'id' => $merchant->id,
        ]);

        // ソフトデリート後も取得可能
        $deletedMerchant = Merchant::withTrashed()->find($merchant->id);
        $this->assertNotNull($deletedMerchant);
        $this->assertNotNull($deletedMerchant->deleted_at);
    }

    /** @test */
    public function 加盟店は代理店に属する()
    {
        $agency = Agency::factory()->create(['name' => 'テスト代理店']);
        $merchant = Merchant::factory()->create(['agency_id' => $agency->id]);

        $this->assertEquals($agency->id, $merchant->agency->id);
        $this->assertEquals('テスト代理店', $merchant->agency->name);
    }

    /** @test */
    public function 加盟店は複数のユーザーを持てる()
    {
        $merchant = Merchant::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $user->update(['merchant_id' => $merchant->id]);
        }

        $this->assertCount(3, $merchant->user);
    }

    /** @test */
    public function 加盟店は複数のメンバーを持てる()
    {
        $merchant = Merchant::factory()->create();
        
        // メンバーのテストデータを作成（MerchantMemberモデルが存在する場合）
        $merchant->members()->create([
            'name' => 'スタッフ1',
            'email' => 'staff1@test.com',
            'line_id' => $this->line_id . '_1',
        ]);

        $merchant->members()->create([
            'name' => 'スタッフ2', 
            'email' => 'staff2@test.com',
            'line_id' => $this->line_id . '_2',
        ]);

        $this->assertCount(2, $merchant->members);
    }

    /** @test */
    public function 必須フィールドが設定されていない場合はエラーになる()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Merchant::create([
            // nameフィールドを省略してエラーを発生させる
            'merchant_code' => 'TEST001',
        ]);
    }

    /** @test */
    public function アクティブな加盟店のみを取得できる()
    {
        Merchant::factory()->create(['status' => 'active', 'name' => 'アクティブ店舗']);
        Merchant::factory()->create(['status' => 'inactive', 'name' => '非アクティブ店舗']);
        Merchant::factory()->create(['status' => 'pending', 'name' => '保留中店舗']);

        $activeMerchants = Merchant::where('status', 'active')->get();

        $this->assertCount(1, $activeMerchants);
        $this->assertEquals('アクティブ店舗', $activeMerchants->first()->name);
    }

    /** @test */
    public function 加盟店コードでの検索ができる()
    {
        $merchant1 = Merchant::factory()->create(['merchant_code' => 'SHOP001']);
        $merchant2 = Merchant::factory()->create(['merchant_code' => 'SHOP002']);

        $foundMerchant = Merchant::where('merchant_code', 'SHOP001')->first();

        $this->assertEquals($merchant1->id, $foundMerchant->id);
        $this->assertEquals('SHOP001', $foundMerchant->merchant_code);
    }

    /** @test */
    public function 郵便番号での住所検索ができる()
    {
        Merchant::factory()->create([
            'postal_code1' => '150',
            'postal_code2' => '0001',
            'address' => '東京都渋谷区神宮前',
        ]);

        Merchant::factory()->create([
            'postal_code1' => '160',
            'postal_code2' => '0022', 
            'address' => '東京都新宿区新宿',
        ]);

        $shibuya = Merchant::where('postal_code1', '150')
                          ->where('postal_code2', '0001')
                          ->first();

        $this->assertEquals('東京都渋谷区神宮前', $shibuya->address);
    }
}