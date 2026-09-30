<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantMember;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * LINE リッチメニューの操作（Messaging API）
 *
 * 表示の優先順位（LINEの仕様）：
 *   ① ユーザー個別の紐付け（POST /v2/bot/user/{userId}/richmenu）
 *   ② 既定メニュー（POST /v2/bot/user/all/richmenu/{richMenuId}）
 * 個別に紐付けたユーザーには既定メニューを差し替えても効かないため、
 * 枠のメニューを変えたときは、その枠のユーザーに紐付け直す（reapplySlot）。
 */
class LineRichMenuService
{
    private const API = 'https://api.line.me/v2/bot';
    private const DATA_API = 'https://api-data.line.me/v2/bot';
    /** 一括紐付けAPIの1回あたりの上限 */
    private const BULK_LIMIT = 500;

    private $lineApiUrl = 'https://api.line.me/v2/bot/user';
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.line.channel_access_token');
    }

    /** リッチメニュー制御を LINE Harness 側へ寄せている場合は true（本アプリからは操作しない） */
    public function isHarness(): bool
    {
        return config('services.line.richmenu_driver') === 'harness';
    }

    /**
     * 指定したユーザーのリッチメニューを切り替える
     *
     * @param string $userId
     * @param string $richMenuId
     * @return array
     */
    public function switchRichMenu($userId, $richMenuId)
    {
        if ($this->isHarness()) {
            Log::info('RichMenu switch skipped (harness driver)', ['userId' => $userId, 'richMenuId' => $richMenuId]);
            return ['status' => 'skipped', 'message' => 'リッチメニュー制御はLINE Harness側に委譲されています'];
        }
        if (!$userId || !$richMenuId) {
            return ['status' => 'error', 'message' => 'LINE IDまたはリッチメニューIDが空です'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post("{$this->lineApiUrl}/{$userId}/richmenu/{$richMenuId}");

        if ($response->successful()) {
            return ['status' => 'success', 'message' => 'リッチメニューが切り替えられました'];
        } else {
            return ['status' => 'error', 'message' => 'リッチメニューの変更に失敗しました', 'details' => $response->json()];
        }
    }

    /**
     * ユーザーを枠（RICHMENU_ID_n）に割り当てる：users.richmenu_id を更新し、その枠のメニューを紐付ける
     */
    public function assignSlot(?User $user, string $slot): array
    {
        if (!$user) {
            return ['status' => 'error', 'message' => 'ユーザーが見つかりません'];
        }
        $user->update(['richmenu_id' => $slot]);
        return $this->switchRichMenu($user->line_id, $this->slotMenuIdFor($slot, $user));
    }

    /**
     * ユーザーに表示する枠のメニューID。
     * 代理店ごとに設定できる枠（④承認済み）は、その人の代理店の設定 → なければ共通の設定。
     */
    public function slotMenuIdFor(string $slot, ?User $user): ?string
    {
        if ($user && RichMenuSlots::supportsAgency($slot)) {
            $agencyMenu = RichMenuSlots::agencyId($slot, $this->agencyIdForUser($user));
            if ($agencyMenu) {
                return $agencyMenu;
            }
        }
        return RichMenuSlots::id($slot);
    }

    /** ユーザーの代理店：オーナーならそのサロンの代理店、スタッフなら所属サロンの代理店 */
    public function agencyIdForUser(User $user): ?int
    {
        $agencyId = Merchant::where('user_id', $user->id)->value('agency_id');
        if (!$agencyId) {
            $merchantId = MerchantMember::where('user_id', $user->id)->whereHas('merchant')->value('merchant_id');
            $agencyId = $merchantId ? Merchant::whereKey($merchantId)->value('agency_id') : null;
        }
        return $agencyId ? (int) $agencyId : null;
    }

    /** 全ユーザーの代理店 [user_id => agency_id]（オーナーを優先） */
    public function agencyMapForUsers(): array
    {
        $map = Merchant::whereNotNull('agency_id')->pluck('agency_id', 'user_id')->all();
        $members = MerchantMember::query()
            ->join('merchants', 'merchants.id', '=', 'merchant_members.merchant_id')
            ->whereNull('merchants.deleted_at')
            ->whereNotNull('merchants.agency_id')
            ->pluck('merchants.agency_id', 'merchant_members.user_id')
            ->all();
        return $map + $members;
    }

    /** ユーザー個別の紐付けを外す（既定メニューが表示されるようになる） */
    public function unlinkUser(?string $lineId): array
    {
        if ($this->isHarness() || !$lineId) {
            return ['status' => 'skipped'];
        }
        $response = $this->http()->delete(self::API . "/user/{$lineId}/richmenu");
        return $response->successful()
            ? ['status' => 'success']
            : ['status' => 'error', 'details' => $response->json()];
    }

    // ------------------------------------------------------------------
    // 管理画面用
    // ------------------------------------------------------------------

    /** LINE側に存在するリッチメニューの一覧 */
    public function list(): array
    {
        $this->guardHarness();
        $response = $this->http()->get(self::API . '/richmenu/list');
        $this->throwIfFailed($response, 'リッチメニュー一覧の取得');
        return $response->json('richmenus') ?? [];
    }

    /** 既定メニューのID（未設定なら null） */
    public function getDefaultId(): ?string
    {
        $this->guardHarness();
        $response = $this->http()->get(self::API . '/user/all/richmenu');
        if ($response->status() === 404) {
            return null;
        }
        $this->throwIfFailed($response, '既定メニューの取得');
        return $response->json('richMenuId');
    }

    public function setDefault(string $richMenuId): void
    {
        $this->guardHarness();
        $response = $this->http()->post(self::API . "/user/all/richmenu/{$richMenuId}");
        $this->throwIfFailed($response, '既定メニューの設定');
    }

    /**
     * リッチメニューを作成して画像をアップロードする。画像のアップロードに失敗したら作成分を削除する。
     *
     * @return string 作成したリッチメニューID
     */
    public function create(array $menu, string $imagePath, string $contentType): string
    {
        $this->guardHarness();

        $validate = $this->http()->post(self::API . '/richmenu/validate', $menu);
        $this->throwIfFailed($validate, '入力内容のチェック');

        $response = $this->http()->post(self::API . '/richmenu', $menu);
        $this->throwIfFailed($response, 'リッチメニューの作成');
        $richMenuId = $response->json('richMenuId');

        $upload = Http::withToken($this->accessToken)
            ->withBody(file_get_contents($imagePath), $contentType)
            ->post(self::DATA_API . "/richmenu/{$richMenuId}/content");

        if (!$upload->successful()) {
            $this->http()->delete(self::API . "/richmenu/{$richMenuId}");
            $this->throwIfFailed($upload, '画像のアップロード');
        }

        return $richMenuId;
    }

    public function delete(string $richMenuId): void
    {
        $this->guardHarness();
        $response = $this->http()->delete(self::API . "/richmenu/{$richMenuId}");
        $this->throwIfFailed($response, 'リッチメニューの削除');
    }

    /**
     * 複数ユーザーに一括で紐付ける（500人ずつ。LINE側で非同期に処理される）
     *
     * @return array ['sent' => 送信した人数, 'errors' => エラーメッセージの配列]
     */
    public function bulkLink(string $richMenuId, array $lineIds): array
    {
        $this->guardHarness();
        $lineIds = array_values(array_unique(array_filter($lineIds)));
        $sent = 0;
        $errors = [];

        foreach (array_chunk($lineIds, self::BULK_LIMIT) as $chunk) {
            $response = $this->http()->post(self::API . '/richmenu/bulk/link', [
                'richMenuId' => $richMenuId,
                'userIds' => $chunk,
            ]);
            if ($response->successful()) {
                $sent += count($chunk);
            } else {
                $errors[] = $this->errorMessage($response);
                Log::error('RichMenu bulk link failed', ['richMenuId' => $richMenuId, 'response' => $response->json()]);
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * 枠のメニューを、その枠のユーザー全員に紐付け直す。既定枠の場合は既定メニューも設定する。
     *
     * @return array ['slot', 'richMenuId', 'users', 'sent', 'errors']
     */
    public function reapplySlot(string $slot, ?int $onlyAgencyId = null): array
    {
        $richMenuId = RichMenuSlots::id($slot);
        $result = ['slot' => $slot, 'agency_id' => $onlyAgencyId, 'richMenuId' => $richMenuId, 'users' => 0, 'sent' => 0, 'errors' => []];

        if (RichMenuSlots::supportsAgency($slot)) {
            return $this->reapplyAgencySlot($slot, $onlyAgencyId, $result);
        }

        if (!$richMenuId) {
            $result['errors'][] = 'リッチメニューが割り当てられていません。';
            return $result;
        }

        if ($slot === RichMenuSlots::DEFAULT_SLOT) {
            try {
                $this->setDefault($richMenuId);
            } catch (RuntimeException $e) {
                $result['errors'][] = $e->getMessage();
            }
        }

        $lineIds = User::where('richmenu_id', $slot)->whereNotNull('line_id')->pluck('line_id')->all();
        $result['users'] = count($lineIds);
        if ($lineIds) {
            $bulk = $this->bulkLink($richMenuId, $lineIds);
            $result['sent'] = $bulk['sent'];
            $result['errors'] = array_merge($result['errors'], $bulk['errors']);
        }

        return $result;
    }

    /**
     * 代理店ごとに設定できる枠：代理店の設定 → なければ共通の設定、でメニューごとにまとめて紐付ける
     * $onlyAgencyId を指定すると、その代理店のユーザーだけを対象にする
     */
    private function reapplyAgencySlot(string $slot, ?int $onlyAgencyId, array $result): array
    {
        $agencyMenus = RichMenuSlots::agencyAssignments($slot);
        $agencyMap = $this->agencyMapForUsers();
        $common = RichMenuSlots::id($slot);

        $groups = []; // richMenuId => [line_id, ...]
        $missing = 0;
        User::where('richmenu_id', $slot)->whereNotNull('line_id')->select('id', 'line_id')
            ->chunkById(1000, function ($users) use ($agencyMap, $agencyMenus, $common, $onlyAgencyId, &$groups, &$missing, &$result) {
                foreach ($users as $user) {
                    $agencyId = isset($agencyMap[$user->id]) ? (int) $agencyMap[$user->id] : null;
                    if ($onlyAgencyId !== null && $agencyId !== $onlyAgencyId) {
                        continue;
                    }
                    $result['users']++;
                    $menuId = ($agencyId && isset($agencyMenus[$agencyId])) ? $agencyMenus[$agencyId] : $common;
                    if ($menuId) {
                        $groups[$menuId][] = $user->line_id;
                    } else {
                        $missing++;
                    }
                }
            });

        if ($missing) {
            $result['errors'][] = "{$missing}人はリッチメニューが割り当てられていないため適用できませんでした。";
        }
        foreach ($groups as $menuId => $lineIds) {
            $bulk = $this->bulkLink($menuId, $lineIds);
            $result['sent'] += $bulk['sent'];
            $result['errors'] = array_merge($result['errors'], $bulk['errors']);
        }
        return $result;
    }

    /**
     * 登録状況からユーザー全員の枠を判定し直し、users.richmenu_id を更新して紐付ける
     *   サロンのオーナー：承認済み(status=1) → ④ ／ それ以外 → ③
     *   スタッフ（merchant_members）→ ④
     *   それ以外（ユーザー登録のみ）→ ②
     *
     * @return array 枠ごとの reapplySlot の結果
     */
    public function recalculateAll(): array
    {
        $ownerStatus = Merchant::whereNotNull('user_id')->pluck('status', 'user_id');
        $memberUserIds = MerchantMember::whereHas('merchant')->pluck('user_id')->flip();

        $bySlot = [];
        User::select('id', 'richmenu_id')->chunkById(500, function ($users) use ($ownerStatus, $memberUserIds, &$bySlot) {
            foreach ($users as $user) {
                if ($ownerStatus->has($user->id)) {
                    $slot = (int) $ownerStatus[$user->id] === 1 ? 'RICHMENU_ID_4' : 'RICHMENU_ID_3';
                } elseif ($memberUserIds->has($user->id)) {
                    $slot = 'RICHMENU_ID_4';
                } else {
                    $slot = 'RICHMENU_ID_2';
                }
                $bySlot[$slot][] = $user->id;
            }
        });

        foreach ($bySlot as $slot => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                User::whereIn('id', $chunk)->update(['richmenu_id' => $slot]);
            }
        }

        $results = [];
        foreach (['RICHMENU_ID_2', 'RICHMENU_ID_3', 'RICHMENU_ID_4'] as $slot) {
            $results[] = $this->reapplySlot($slot);
        }
        return $results;
    }

    // ------------------------------------------------------------------

    private function http()
    {
        return Http::withToken($this->accessToken)->acceptJson()->timeout(30);
    }

    private function guardHarness(): void
    {
        if ($this->isHarness()) {
            throw new RuntimeException('リッチメニューはLINE Harness側で管理する設定になっています（LINE_RICHMENU_DRIVER=harness）。');
        }
    }

    private function throwIfFailed($response, string $what): void
    {
        if (!$response->successful()) {
            throw new RuntimeException("{$what}に失敗しました：" . $this->errorMessage($response));
        }
    }

    private function errorMessage($response): string
    {
        $message = $response->json('message') ?? ('HTTP ' . $response->status());
        $details = collect($response->json('details') ?? [])
            ->map(function ($d) {
                return trim(($d['property'] ?? '') . ' ' . ($d['message'] ?? ''));
            })
            ->filter()
            ->implode(' / ');
        return $details !== '' ? "{$message}（{$details}）" : $message;
    }
}
