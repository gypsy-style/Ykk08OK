<?php

namespace App\Services;

use App\Models\Setting;

/**
 * リッチメニューの「枠」（登録段階ごとのメニュー）の定義と、各枠に割り当てたリッチメニューIDの管理。
 *
 * 枠のキー（RICHMENU_ID_1〜4）は users.richmenu_id に保存されている値と同じ。
 * 割り当ては settings テーブル（richmenu_slot_RICHMENU_ID_n）に保存し、
 * 未設定の枠は従来どおり .env（config('app.richmenus')）の値を使う。
 */
class RichMenuSlots
{
    /** 既定メニュー（友だち追加しただけの人に表示される）の枠 */
    public const DEFAULT_SLOT = 'RICHMENU_ID_1';

    /** 代理店ごとに別のメニューを設定できる枠 */
    public const AGENCY_SLOTS = ['RICHMENU_ID_4'];

    /** 作成したリッチメニューの管理用情報（名前・画像など）の保存キー */
    private const DEFINITIONS_KEY = 'richmenu_definitions';

    public static function labels(): array
    {
        return [
            'RICHMENU_ID_1' => '① 未登録（友だち追加のみ）※既定メニュー',
            'RICHMENU_ID_2' => '② ユーザー登録済み',
            'RICHMENU_ID_3' => '③ サロン登録済み（承認待ち）',
            'RICHMENU_ID_4' => '④ 承認済み・スタッフ（注文できる）',
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    public static function isSlot($slot): bool
    {
        return is_string($slot) && array_key_exists($slot, self::labels());
    }

    public static function label(?string $slot): string
    {
        return self::labels()[$slot] ?? (string) $slot;
    }

    /** 枠に割り当てているリッチメニューID（管理画面の設定 → なければ .env） */
    public static function id(string $slot): ?string
    {
        $saved = Setting::getValue(self::settingKey($slot));
        if ($saved !== null && $saved !== '') {
            return $saved;
        }
        $env = config("app.richmenus.{$slot}");
        return $env !== '' ? $env : null;
    }

    /** 管理画面で設定されているか（false なら .env の値を使っている） */
    public static function isConfigured(string $slot): bool
    {
        $saved = Setting::getValue(self::settingKey($slot));
        return $saved !== null && $saved !== '';
    }

    public static function all(): array
    {
        $result = [];
        foreach (self::keys() as $slot) {
            $result[$slot] = self::id($slot);
        }
        return $result;
    }

    public static function save(string $slot, ?string $richMenuId): void
    {
        Setting::updateOrCreate(
            ['key' => self::settingKey($slot)],
            ['value' => $richMenuId]
        );
    }

    public static function supportsAgency(string $slot): bool
    {
        return in_array($slot, self::AGENCY_SLOTS, true);
    }

    /** 代理店ごとの設定（未設定なら null。共通の枠の値にはフォールバックしない） */
    public static function agencyId(string $slot, $agencyId): ?string
    {
        if (!$agencyId || !self::supportsAgency($slot)) {
            return null;
        }
        $v = Setting::getValue(self::agencySettingKey($slot, $agencyId));
        return $v !== null && $v !== '' ? $v : null;
    }

    /** 代理店ごとの設定を保存。空なら共通の枠に戻す */
    public static function saveAgency(string $slot, $agencyId, ?string $richMenuId): void
    {
        $key = self::agencySettingKey($slot, $agencyId);
        if ($richMenuId === null || $richMenuId === '') {
            Setting::where('key', $key)->delete();
            return;
        }
        Setting::updateOrCreate(['key' => $key], ['value' => $richMenuId]);
    }

    /** 枠の代理店ごとの設定一覧 [agency_id => richMenuId] */
    public static function agencyAssignments(string $slot): array
    {
        $prefix = self::agencySettingKey($slot, '');
        $result = [];
        foreach (Setting::where('key', 'like', $prefix . '%')->pluck('value', 'key') as $key => $value) {
            if ($value !== null && $value !== '') {
                $result[(int) substr($key, strlen($prefix))] = $value;
            }
        }
        return $result;
    }

    /** このリッチメニューIDを代理店ごとの設定で使っている [agency_id, ...] */
    public static function agenciesUsing(string $richMenuId): array
    {
        $ids = [];
        foreach (self::AGENCY_SLOTS as $slot) {
            foreach (self::agencyAssignments($slot) as $agencyId => $id) {
                if ($id === $richMenuId) {
                    $ids[] = $agencyId;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    private static function agencySettingKey(string $slot, $agencyId): string
    {
        return self::settingKey($slot) . '@agency_' . $agencyId;
    }

    /** このリッチメニューIDを割り当てている枠の一覧 */
    public static function slotsUsing(string $richMenuId): array
    {
        return array_keys(array_filter(self::all(), function ($id) use ($richMenuId) {
            return $id === $richMenuId;
        }));
    }

    private static function settingKey(string $slot): string
    {
        return 'richmenu_slot_' . $slot;
    }

    // ---- 作成したリッチメニューの管理用情報 ----

    public static function definitions(): array
    {
        $json = Setting::getValue(self::DEFINITIONS_KEY, '');
        $data = $json ? json_decode($json, true) : [];
        return is_array($data) ? $data : [];
    }

    public static function definition(string $richMenuId): ?array
    {
        return self::definitions()[$richMenuId] ?? null;
    }

    public static function saveDefinition(string $richMenuId, array $data): void
    {
        $all = self::definitions();
        $all[$richMenuId] = $data;
        Setting::updateOrCreate(
            ['key' => self::DEFINITIONS_KEY],
            ['value' => json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }

    public static function removeDefinition(string $richMenuId): void
    {
        $all = self::definitions();
        unset($all[$richMenuId]);
        Setting::updateOrCreate(
            ['key' => self::DEFINITIONS_KEY],
            ['value' => json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }
}
