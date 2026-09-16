<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * テストデータの除外ロジック
 *
 * 代理店のテストフラグが立っていれば、配下の加盟店は merchants.is_test の値に
 * 関わらずテスト扱いになる。判定式を変えるときは必ずここだけを直すこと。
 *
 * Eloquent のグローバルスコープは使わない。DB::table の生クエリに効かないため。
 */
class TestDataFilter
{
    /**
     * テスト扱いになる加盟店IDのサブクエリ
     *
     * merchants.agency_id は NULL を許すため left join で引く。代理店が未設定の
     * 加盟店は merchants.is_test だけで判定される。
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public static function testMerchantIds()
    {
        return DB::table('merchants as tm')
            ->leftJoin('agencies as ta', 'ta.id', '=', 'tm.agency_id')
            ->where(function ($q) {
                $q->where('tm.is_test', 1)->orWhere('ta.is_test', 1);
            })
            ->select('tm.id');
    }

    /**
     * merchant_id を持つクエリからテスト加盟店の分を除外する
     *
     * @param mixed $query Eloquent または DB::table のクエリビルダ
     * @param string $alias テーブル別名（join で 'o' などを使っている場合に渡す）
     * @return mixed
     */
    public static function excludeMerchants($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->whereNotIn($p . 'merchant_id', self::testMerchantIds());
    }

    /**
     * merchants テーブル自身を引いているクエリからテスト加盟店の行を除外する
     *
     * @param mixed $query
     * @param string $alias
     * @return mixed
     */
    public static function excludeMerchantRows($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->whereNotIn($p . 'id', self::testMerchantIds());
    }

    /**
     * agencies テーブル自身を引いているクエリからテスト代理店の行を除外する
     *
     * @param mixed $query
     * @param string $alias
     * @return mixed
     */
    public static function excludeAgencyRows($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->where($p . 'is_test', 0);
    }

    /**
     * 加盟店1件がテスト扱いかどうか
     *
     * @param \App\Models\Merchant|null $merchant
     * @return bool
     */
    public static function isTestMerchant($merchant)
    {
        if ($merchant === null) {
            return false;
        }

        return (bool) $merchant->is_test || (bool) optional($merchant->agency)->is_test;
    }
}
