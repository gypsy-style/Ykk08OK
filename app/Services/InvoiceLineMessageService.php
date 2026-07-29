<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * 請求書LINE通知の本文テンプレートを組み立てる
 */
class InvoiceLineMessageService
{
    /** 有効フラグの設定キー */
    public const KEY_ENABLED = 'invoice_line_enabled';
    /** 本文テンプレートの設定キー */
    public const KEY_MESSAGE = 'invoice_line_message';
    /** 送信対象を絞り込む加盟店IDの設定キー（空なら全加盟店が対象） */
    public const KEY_TARGET_IDS = 'invoice_line_target_merchant_ids';

    /**
     * 差し込み可能なプレースホルダ（管理画面のヘルプ表示にも使う）
     *
     * @return array key => 説明
     */
    public static function placeholders()
    {
        return [
            '{merchant_name}' => '加盟店名',
            '{month_label}' => '請求対象月（例: 2026年6月分）',
            '{month}' => '請求対象月（例: 2026-06）',
            '{order_count}' => '対象月の注文件数',
            '{subtotal}' => '小計（税抜）',
            '{tax}' => '消費税',
            '{shipping_fee}' => '送料',
            '{total}' => 'ご請求金額（税込・送料込）',
            '{invoice_url}' => '請求書ページのURL',
        ];
    }

    /**
     * 初期テンプレート
     *
     * @return string
     */
    public static function defaultTemplate()
    {
        return "{merchant_name} 様\n\n"
            . "いつもご利用いただきありがとうございます。\n"
            . "{month_label}のご請求書を発行いたしました。\n\n"
            . "ご請求金額：{total}円（税込）\n"
            . "ご注文件数：{order_count}件\n\n"
            . "詳細は下記よりご確認いただけます。\n"
            . "{invoice_url}\n\n"
            . "引き続きよろしくお願いいたします。";
    }

    /**
     * 通知が有効か
     *
     * @return bool
     */
    public function isEnabled()
    {
        return (string) Setting::getValue(self::KEY_ENABLED, '0') === '1';
    }

    /**
     * 送信対象を絞り込む加盟店IDの一覧
     *
     * 段階公開のための安全装置。空配列＝絞り込みなし（全加盟店が対象）。
     *
     * @return array<int>
     */
    public function targetMerchantIds()
    {
        $raw = (string) Setting::getValue(self::KEY_TARGET_IDS, '');
        if (trim($raw) === '') {
            return [];
        }

        $ids = array_filter(array_map('trim', explode(',', $raw)), function ($id) {
            return $id !== '' && ctype_digit($id);
        });

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * 保存済みのテンプレート（未設定なら初期テンプレート）
     *
     * @return string
     */
    public function template()
    {
        $template = Setting::getValue(self::KEY_MESSAGE, '');
        return $template !== '' && $template !== null ? $template : self::defaultTemplate();
    }

    /**
     * 実データを差し込んで本文を生成する
     *
     * @param string $template
     * @param Merchant $merchant
     * @param array $invoice InvoiceService の集計結果
     * @param string $invoiceUrl
     * @return string
     */
    public function render($template, Merchant $merchant, array $invoice, $invoiceUrl = '')
    {
        return $this->replace($template, [
            '{merchant_name}' => $merchant->name,
            '{month_label}' => $invoice['label'],
            '{month}' => $invoice['month'],
            '{order_count}' => number_format($invoice['order_count']),
            '{subtotal}' => number_format($invoice['subtotal']),
            '{tax}' => number_format($invoice['tax']),
            '{shipping_fee}' => number_format($invoice['shipping_fee']),
            '{total}' => number_format($invoice['grand_total']),
            '{invoice_url}' => $invoiceUrl,
        ]);
    }

    /**
     * 管理画面のプレビュー用にサンプル値を差し込む
     *
     * @param string $template
     * @param string $invoiceUrl
     * @return string
     */
    public function renderSample($template, $invoiceUrl = '')
    {
        $lastMonth = Carbon::now()->subMonth();

        return $this->replace($template, [
            '{merchant_name}' => 'サンプル商店',
            '{month_label}' => $lastMonth->format('Y年n月分'),
            '{month}' => $lastMonth->format('Y-m'),
            '{order_count}' => '12',
            '{subtotal}' => '120,000',
            '{tax}' => '12,000',
            '{shipping_fee}' => '3,000',
            '{total}' => '135,000',
            '{invoice_url}' => $invoiceUrl ?: 'https://liff.line.me/xxxxxxxxxx-yyyyyyyy',
        ]);
    }

    /**
     * @param string $template
     * @param array $values
     * @return string
     */
    private function replace($template, array $values)
    {
        return str_replace(array_keys($values), array_values($values), (string) $template);
    }
}
