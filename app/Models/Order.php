<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'agency_id',
        'merchant_id', 
        'total_price', 
        'order_number', 
        'is_staff_sale', 
        'shipping_fee', 
        'status', 
        'memo'];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->created_at)->format('Y/m/d H:i');
    }

    // 注文詳細とのリレーション
    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }
    // Order.php
    public function agency()
    {
        // return $this->hasOneThrough(
        //     Agency::class,   // 最終的に取得したいモデル
        //     Merchant::class, // 中間テーブル（モデル）
        //     'id',            // Merchant テーブルの主キー
        //     'id',            // Agency テーブルの主キー
        //     'merchant_id',   // Order テーブルから Merchant テーブルへの外部キー
        //     'agency_id'      // Merchant テーブルから Agency テーブルへの外部キー
        // );
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ステータス変更履歴を取得
     */
    public function statusChangeLogs()
    {
        return $this->hasMany(ActivityLog::class, 'model_id')
            ->where('model_type', Order::class)
            ->where('action', 'order_status_updated')
            ->orderBy('created_at', 'desc');
    }

    /**
     * 最新のステータス変更日時とステータス名を取得
     */
    public function getLastStatusChangeAttribute()
    {
        $lastStatusChange = $this->statusChangeLogs()->first();
        
        if (!$lastStatusChange) {
            return null;
        }

        $statusText = $this->getStatusText($lastStatusChange->new_status);
        
        return [
            'date' => $lastStatusChange->created_at,
            'text' => $statusText . ': ' . $lastStatusChange->created_at->format('Y/m/d H:i')
        ];
    }

    private function getStatusText($status)
    {
        switch ($status) {
            case 1: return '代理店未処理';
            case 2: return '代理店処理済み';
            case 3: return '本部処理済み';
            case 4: return '保留';
            case 5: return '発送待ち';
            case 6: return '発送済み';
            case 9: return 'キャンセル';
            default: return '不明なステータス';
        }
    }

    /**
     * 注文が緊急対応必要かどうかを判定
     * 当日14時を過ぎていれば、それ以前のステータス変更が対象。
     * 14時前であれば、前日14時以前のステータス変更が対象。
     */
    public function isUrgent()
    {
        if ($this->status !== 2) {
            return false;
        }

        $lastLog = $this->statusChangeLogs()->first();

        // 最新のログが「1→2」の変更でなければ対象外
        if (!$lastLog || $lastLog->old_status !== 1 || $lastLog->new_status !== 2) {
            return false;
        }
        
        $changeDate = $lastLog->created_at;

        // 14時を基準に締め切り時間を設定
        if (now()->hour >= 14) {
            $deadline = now()->startOfDay()->addHours(14);
        } else {
            $deadline = now()->subDay()->startOfDay()->addHours(14);
        }

        return $changeDate <= $deadline;
    }
}
