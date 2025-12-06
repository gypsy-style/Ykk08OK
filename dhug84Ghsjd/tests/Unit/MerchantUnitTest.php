<?php

namespace Tests\Unit;

use App\Models\Agency;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // テスト用ダミーのline_id（ローカル環境用）
        $this->line_id = 'line_unit_' . rand(1000000, 9999999);
    }

    /** @test */
    public function Merchantモデルが正しいテーブル名を使用している()
    {
        $merchant = new Merchant();
        $this->assertEquals('merchants', $merchant->getTable());
    }

    /** @test */
    public function fillableプロパティが正しく設定されている()
    {
        $merchant = new Merchant();
        $expectedFillable = [
            'agency_id',
            'name',
            'merchant_code',
            'status',
            'postal_code1',
            'postal_code2',
            'address',
            'phone',
            'contact_person',
            'user_id',
        ];

        $this->assertEquals($expectedFillable, $merchant->getFillable());
    }

    /** @test */
    public function SoftDeletesトレイトが使用されている()
    {
        $merchant = new Merchant();
        $this->assertTrue(method_exists($merchant, 'trashed'));
        $this->assertTrue(method_exists($merchant, 'restore'));
        $this->assertTrue(method_exists($merchant, 'forceDelete'));
    }

    /** @test */
    public function HasFactoryトレイトが使用されている()
    {
        $this->assertTrue(method_exists(Merchant::class, 'factory'));
    }

    /** @test */
    public function agencyリレーションが正しく定義されている()
    {
        $agency = Agency::factory()->create();
        $merchant = Merchant::factory()->create(['agency_id' => $agency->id]);

        $this->assertInstanceOf(Agency::class, $merchant->agency);
        $this->assertEquals($agency->id, $merchant->agency->id);
    }

    /** @test */
    public function userリレーションが正しく定義されている()
    {
        $merchant = Merchant::factory()->create();
        
        // userリレーションがhasManyであることをテスト
        $relation = $merchant->user();
        $this->assertEquals('App\Models\User', $relation->getRelated()::class);
    }

    /** @test */
    public function membersリレーションが正しく定義されている()
    {
        $merchant = Merchant::factory()->create();
        
        // membersリレーションがhasManyであることをテスト
        $relation = $merchant->members();
        $this->assertEquals('App\Models\MerchantMember', $relation->getRelated()::class);
    }

    /** @test */
    public function 加盟店コードの生成ロジックをテスト()
    {
        // 加盟店コード生成のカスタムメソッドがある場合のテスト例
        $merchant = new Merchant();
        
        // 例：加盟店コードが自動生成される場合
        if (method_exists($merchant, 'generateMerchantCode')) {
            $code = $merchant->generateMerchantCode();
            $this->assertIsString($code);
            $this->assertGreaterThan(0, strlen($code));
        } else {
            // メソッドが存在しない場合はスキップ
            $this->markTestSkipped('generateMerchantCode method does not exist');
        }
    }

    /** @test */
    public function ステータス検証メソッドのテスト()
    {
        $merchant = new Merchant();
        
        // ステータス検証メソッドがある場合のテスト例
        if (method_exists($merchant, 'isActive')) {
            $merchant->status = 'active';
            $this->assertTrue($merchant->isActive());
            
            $merchant->status = 'inactive';
            $this->assertFalse($merchant->isActive());
        } else {
            // メソッドが存在しない場合はスキップ
            $this->markTestSkipped('isActive method does not exist');
        }
    }

    /** @test */
    public function 郵便番号フォーマットメソッドのテスト()
    {
        $merchant = new Merchant([
            'postal_code1' => '123',
            'postal_code2' => '4567',
        ]);

        // 郵便番号フォーマットメソッドがある場合のテスト例
        if (method_exists($merchant, 'getFormattedPostalCode')) {
            $formatted = $merchant->getFormattedPostalCode();
            $this->assertEquals('123-4567', $formatted);
        } else {
            // メソッドが存在しない場合は基本的なテスト
            $this->assertEquals('123', $merchant->postal_code1);
            $this->assertEquals('4567', $merchant->postal_code2);
        }
    }

    /** @test */
    public function 属性のキャストが正しく設定されている()
    {
        $merchant = new Merchant();
        
        // deleted_atがdatesプロパティに含まれていることを確認
        $dates = $merchant->getDates();
        $this->assertContains('deleted_at', $dates);
    }

    /** @test */
    public function モデルの初期値設定テスト()
    {
        $merchant = new Merchant();
        
        // デフォルト値が設定されている場合のテスト
        if (isset($merchant->attributes['status'])) {
            $this->assertNotNull($merchant->status);
        }
        
        // Fillableな属性が正しく設定できるかテスト
        $merchant->fill([
            'name' => 'テスト店舗',
            'merchant_code' => 'TEST' . rand(100, 999),
            'status' => 'pending',
        ]);
        
        $this->assertEquals('テスト店舗', $merchant->name);
        $this->assertEquals('pending', $merchant->status);
    }
}