<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantMember;
use App\Models\User;

/**
 * サロンでの権限を判定する
 *
 * オーナー（merchants.user_id が本人）は全権限を持つ。
 * スタッフ（merchant_members.user_id が本人）は、その行の権限列の値に従う。
 * どちらでもない人は何もできない。
 *
 * 画面のボタンを出し分けるだけでなく、LIFF の裏の API でも必ずここで判定する。
 * API は URL さえ分かれば誰でも呼べるため。
 */
class MerchantAccess
{
    const PERMISSIONS = [
        'can_order',
        'can_view_order_history',
        'can_view_invoice',
        'can_edit_merchant',
        'can_manage_staff',
    ];

    const LABELS = [
        'can_order' => '注文する',
        'can_view_order_history' => '注文履歴を見る',
        'can_view_invoice' => '請求書確認',
        'can_edit_merchant' => '登録情報の修正',
        'can_manage_staff' => 'スタッフ管理',
    ];

    /** @var User|null */
    public $user;

    /** @var Merchant|null */
    public $merchant;

    /** @var MerchantMember|null */
    public $member;

    /** @var bool */
    public $isOwner = false;

    /**
     * @param User|null $user
     * @return self
     */
    public static function forUser(?User $user)
    {
        $access = new self();
        $access->user = $user;

        if (!$user) {
            return $access;
        }

        $merchant = Merchant::where('user_id', $user->id)->first();
        if ($merchant) {
            $access->merchant = $merchant;
            $access->isOwner = true;
            return $access;
        }

        // 削除済みサロンのスタッフ行が残っていても所属扱いにしない
        $member = MerchantMember::where('user_id', $user->id)->whereHas('merchant')->first();
        if ($member) {
            $access->member = $member;
            $access->merchant = $member->merchant;
        }

        return $access;
    }

    /**
     * @param string $permission PERMISSIONS のどれか
     * @return bool
     */
    public function can($permission)
    {
        if (!in_array($permission, self::PERMISSIONS, true) || !$this->merchant) {
            return false;
        }

        if ($this->isOwner) {
            return true;
        }

        return (bool) $this->member->{$permission};
    }

    /**
     * @return array 列名 => bool
     */
    public function permissions()
    {
        $result = [];
        foreach (self::PERMISSIONS as $permission) {
            $result[$permission] = $this->can($permission);
        }
        return $result;
    }

    /**
     * スタッフ追加用 QR に入れる招待トークン
     *
     * LIFF を通すとクエリが liff.state に包まれ、Laravel の署名付き URL を
     * そのまま検証できないため、merchant_id に対する HMAC を使う。
     * 紙に印刷して使うこともあるので有効期限は付けない。
     *
     * @param int $merchantId
     * @return string
     */
    public static function inviteToken($merchantId)
    {
        return hash_hmac('sha256', 'staff-invite:' . (int) $merchantId, config('app.key'));
    }

    /**
     * @param mixed $merchantId
     * @param mixed $token
     * @return bool
     */
    public static function isValidInviteToken($merchantId, $token)
    {
        if (!is_string($token) || $token === '' || !is_numeric($merchantId)) {
            return false;
        }

        return hash_equals(self::inviteToken((int) $merchantId), $token);
    }
}
