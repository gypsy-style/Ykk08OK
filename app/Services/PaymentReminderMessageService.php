<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * 振込督促LINEの本文テンプレートを組み立てる
 *
 * 請求書LINE通知（InvoiceLineMessageService）と対になるクラス。
 * 自動送信が無いため、有効フラグと段階公開の絞り込みは持たない。
 */
class PaymentReminderMessageService
{
    /** 本文テンプレートの設定キー */
    public const KEY_MESSAGE = 'payment_reminder_message';

    /**
     * 差し込み可能なプレースホルダ（画面のヘルプ表示にも使う）
     *
     * @return array key => 説明
     */
    public static function placeholders()
    {
        return [
            '{merchant_name}' => '加盟店名',
            '{month_label}' => '請求対象月（例: 2026年7月分）',
            '{month}' => '請求対象月（例: 2026-07）',
            '{total}' => 'ご請求金額（税込・送料込）',
            '{payment_due_date}' => 'お支払期限（例: 2026年8月15日）',
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
            . "{month_label}のご請求につきまして、本日時点でご入金の確認が取れておりません。\n\n"
            . "ご請求金額：{total}円（税込）\n"
            . "お支払期限：{payment_due_date}\n\n"
            . "お手数ですが、ご確認のうえお振込みをお願いいたします。\n"
            . "請求書は下記よりご確認いただけます。\n"
            . "{invoice_url}\n\n"
            . "行き違いでお振込みいただいておりましたら、何卒ご容赦ください。";
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
     * @param array $invoice InvoiceService::forMonth の結果
     * @param string $invoiceUrl
     * @return string
     */
    public function render($template, Merchant $merchant, array $invoice, $invoiceUrl = '')
    {
        return $this->replace($template, [
            '{merchant_name}' => $merchant->name,
            '{month_label}' => $invoice['label'],
            '{month}' => $invoice['month'],
            '{total}' => number_format($invoice['grand_total']),
            '{payment_due_date}' => $this->dueDate($invoice['invoice_date'])->format('Y年n月j日'),
            '{invoice_url}' => $invoiceUrl,
        ]);
    }

    /**
     * プレビュー用にサンプル値を差し込む
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
            '{total}' => '135,000',
            '{payment_due_date}' => $this->dueDate($lastMonth->copy()->addMonth())->format('Y年n月j日'),
            '{invoice_url}' => $invoiceUrl ?: 'https://liff.line.me/xxxxxxxxxx-yyyyyyyy',
        ]);
    }

    /**
     * 支払期限（請求日と同じ月の15日）。請求書PDFと同じ計算に揃える
     *
     * @param Carbon $invoiceDate
     * @return Carbon
     */
    private function dueDate(Carbon $invoiceDate)
    {
        return $invoiceDate->copy()->day(15);
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
