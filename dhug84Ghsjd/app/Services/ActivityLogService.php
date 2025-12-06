<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * アクティビティログを記録
     *
     * @param string $action アクション名
     * @param Model|null $model 対象モデル
     * @param array|null $oldValues 変更前の値
     * @param array|null $newValues 変更後の値
     * @param string|null $description 詳細な説明
     * @return ActivityLog
     */
    public function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * 注文作成時のログを記録
     *
     * @param Model $order 注文モデル
     * @return ActivityLog
     */
    public function logOrderCreated(Model $order): ActivityLog
    {
        return $this->log(
            'order_created',
            $order,
            null,
            $order->toArray(),
            '注文が作成されました'
        );
    }

    /**
     * 注文ステータス更新時のログを記録
     *
     * @param Model $order 注文モデル
     * @param int $oldStatus 変更前のステータス
     * @param int $newStatus 変更後のステータス
     * @return ActivityLog
     */
    public function logOrderStatusUpdated(Model $order, int $oldStatus, int $newStatus): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'order_status_updated',
            'model_type' => get_class($order),
            'model_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'description' => '注文ステータスが更新されました',
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * 注文更新時のログを記録
     *
     * @param Model $order 注文モデル
     * @param array $oldValues 変更前の値
     * @param array $newValues 変更後の値
     * @return ActivityLog
     */
    public function logOrderUpdated(Model $order, array $oldValues, array $newValues): ActivityLog
    {
        return $this->log(
            'order_updated',
            $order,
            $oldValues,
            $newValues,
            '注文が更新されました'
        );
    }

    /**
     * 注文削除時のログを記録
     *
     * @param Model $order 注文モデル
     * @return ActivityLog
     */
    public function logOrderDeleted(Model $order): ActivityLog
    {
        return $this->log(
            'order_deleted',
            $order,
            $order->toArray(),
            null,
            '注文が削除されました'
        );
    }

    /**
     * 商品作成時のログを記録
     *
     * @param Model $product 商品モデル
     * @return ActivityLog
     */
    public function logProductCreated(Model $product): ActivityLog
    {
        return $this->log(
            'product_created',
            $product,
            null,
            $product->toArray(),
            '商品が作成されました'
        );
    }

    /**
     * 商品更新時のログを記録
     *
     * @param Model $product 商品モデル
     * @param array $oldValues 変更前の値
     * @param array $newValues 変更後の値
     * @return ActivityLog
     */
    public function logProductUpdated(Model $product, array $oldValues, array $newValues): ActivityLog
    {
        return $this->log(
            'product_updated',
            $product,
            $oldValues,
            $newValues,
            '商品が更新されました'
        );
    }
} 