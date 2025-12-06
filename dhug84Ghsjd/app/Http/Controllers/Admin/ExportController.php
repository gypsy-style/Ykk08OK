<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function exportOrders(Request $request)
    {

        // ヘッダー行の設定
        $headers = [
            '',
            '売上日付',
            '伝票No.',
            '得意先コード',
            '得意先名１',
            '商品コード',
            '商品名',
            '数量',
            '売上金額',
            'プロジェクトコード',
            '摘要',
            '摘要２',
            '摘要３',
            '伝票区分',
            '請求日付',
            '受注ID',
            '受注明細ID',
            '得意先名２',
            '請求先コード',
            '税額通知',
            '得意先担当者',
            '部門コード',
            '担当者コード',
            '回収予定日',
            '回収種別',
            '回収方法',
            '信販会社コード',
            '伝票フラグ',
            '直送先コード',
            '直送先名１',
            '直送先名２',
            '直送先担当者',
            '直送先敬称',
            '直送先略称',
            '直送先郵便番号',
            '直送先住所１',
            '直送先住所２',
            '直送先電話番号',
            '直送先FAX番号',
            '回収期日',
            '信販手数料',
            '入金摘要',
            '売上区分',
            '出荷区分',
            '商品コード種類',
            '注文No.',
            '倉庫コード',
            '入数',
            '箱数',
            '単位',
            '単価',
            '単位原価',
            '売単価',
            '売上原価',
            '売価金額',
            '課税区分',
            '取引状態区分',
            '税率種別',
            '税率',
            '税込区分',
            '原価税込区分',
            '入数小数桁',
            '箱数小数桁',
            '数量小数桁',
            '単価小数桁',
            '消費税',
            '原価消費税',
            '同時処理',
            '仕入先コード',
            '備考',
            '付箋色',
            '付箋メモ'
        ];
        // $orders = Order::with('details.product')->get();
        // dd($orders->toArray());
        // CSVのストリームレスポンスを作成
        $response = new StreamedResponse(function () use ($headers) {
            $handle = fopen('php://output', 'w');

            // ヘッダーを書き込み
            fputcsv($handle, array_map(fn($h) => mb_convert_encoding($h, 'SJIS-win', 'UTF-8'), $headers));

            // データ取得
            $orders = Order::with(['merchant', 'agency', 'details.product'])
                ->where('status', 3)
                ->get();
            // dd($orders);

            $previousOrderId = null;  // 前回のorder_idを保持する変数
            foreach ($orders as $order) {
                $salon_code = isset($order->merchant->merchant_code) ? $order->merchant->merchant_code : $order->agency->agency_code;
                $salon_name = isset($order->merchant->name) ? $order->merchant->name : $order->agency->name;
                $project_code = $order->is_staff_sale == 1 ? '003' : '001';
                foreach ($order->details as $detail) {
                    // order_idが変わったら先頭列に * をつける
                    $orderMarker = ($order->id !== $previousOrderId) ? '*' : '';
                    $row = [
                        $orderMarker,
                        $order->created_at->format('Y/m/d'),
                        str_pad($order->id, 5, '0', STR_PAD_LEFT),
                        $salon_code,
                        $salon_name,
                        $detail->product->product_code,
                        $detail->product->product_name,
                        $detail->quantity,
                        ($detail->product->wholesale_price * $detail->quantity),
                        $project_code,
                        $order->memo,
                        '',
                        '',
                        '0',
                        $order->created_at->format('Y/m/d'),
                        '',
                        '',
                        '',
                        $order->agency->agency_code,
                        '6',
                        '',
                        '0',
                        '0',
                        '',
                        '2',
                        '0',
                        '',
                        '0',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '0',
                        '',
                        '0',
                        '0',
                        '0',
                        '',
                        '0',
                        '0',
                        '',
                        '',
                        '0',
                        $detail->product->wholesale_price,
                        '0',
                        '',
                        '0',
                        '',
                        '1',
                        '1',
                        '',
                        $detail->product->tax_rate,
                        '1',
                        '1',
                        '0',
                        '0',
                        '0',
                        '2',
                        '',
                        '0',
                        '0',
                        '',
                        '',
                        '',
                        ''
                    ];
    
                    // Shift-JIS に変換して書き込み
                    fputcsv($handle, array_map(fn($field) => mb_convert_encoding($field, 'SJIS-win', 'UTF-8'), $row));
                    // 現在のorder_idを前回のorder_idとして保存
                    $previousOrderId = $order->id;
                }
                // 送料がある場合は商品として登録
                if($order->shipping_fee > 0) {
                    $row = [
                        '',
                        $order->created_at->format('Y/m/d'),
                        str_pad($order->id, 5, '0', STR_PAD_LEFT),
                        $salon_code,
                        $salon_name,
                        'z0001',
                        '送料',
                        1,
                        '700',
                        '001',
                        '',
                        '',
                        '',
                        '0',
                        $order->created_at->format('Y/m/d'),
                        '',
                        '',
                        '',
                        $order->agency->agency_code,
                        '6',
                        '',
                        '0',
                        '0',
                        '',
                        '2',
                        '0',
                        '',
                        '0',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '0',
                        '',
                        '0',
                        '0',
                        '0',
                        '',
                        '0',
                        '0',
                        '',
                        '',
                        '0',
                        700,
                        '0',
                        '',
                        '0',
                        '',
                        '1',
                        '1',
                        '',
                        10,
                        '1',
                        '1',
                        '0',
                        '0',
                        '0',
                        '2',
                        '',
                        '0',
                        '0',
                        '',
                        '',
                        '',
                        ''
                    ];
    
                    // Shift-JIS に変換して書き込み
                    fputcsv($handle, array_map(fn($field) => mb_convert_encoding($field, 'SJIS-win', 'UTF-8'), $row));
                }
            }

            fclose($handle);
        });

        // HTTPレスポンスヘッダーを設定
        $response->headers->set('Content-Type', 'text/csv; charset=Shift_JIS');
        $response->headers->set('Content-Disposition', 'attachment; filename="orders.csv"');

        return $response;
    }
}
