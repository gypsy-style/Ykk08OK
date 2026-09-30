<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\User;
use App\Services\LineRichMenuService;
use App\Services\RichMenuSlots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * 設定 > リッチメニュー
 *   ・枠（登録段階）ごとのリッチメニュー割り当てと、既存ユーザーへの再適用
 *   ・リッチメニューの作成（画像＋タップ領域）・複製・削除
 * line-members（WordPress版）の「リッチメニュー作成」「ステータス別リッチメニュー」「診断」から移植。
 */
class RichMenuController extends Controller
{
    /** @var LineRichMenuService */
    private $richMenu;

    public function __construct(LineRichMenuService $richMenu)
    {
        $this->richMenu = $richMenu;
    }

    public static function actionTypes(): array
    {
        return [
            'message'      => 'テキスト送信',
            'uri'          => 'URLを開く（LINE内）',
            'uri_external' => 'URLを開く（外部ブラウザ）',
            'postback'     => 'ポストバック（高度）',
        ];
    }

    public function index(Request $request)
    {
        $slots = RichMenuSlots::labels();
        $assignments = RichMenuSlots::all();
        $definitions = RichMenuSlots::definitions();

        $lineMenus = [];
        $defaultId = null;
        $apiError = null;
        try {
            $lineMenus = $this->richMenu->list();
            $defaultId = $this->richMenu->getDefaultId();
        } catch (RuntimeException $e) {
            $apiError = $e->getMessage();
        }
        $lineMenuIds = array_column($lineMenus, 'richMenuId');

        // 枠ごとのユーザー数（users.richmenu_id）
        $slotCounts = User::whereNotNull('line_id')
            ->selectRaw('richmenu_id, COUNT(*) as cnt')
            ->groupBy('richmenu_id')
            ->pluck('cnt', 'richmenu_id');

        // 代理店ごとの設定（④承認済み）
        $agencies = Agency::orderBy('id')->get(['id', 'name', 'is_test']);
        $agencySlot = RichMenuSlots::AGENCY_SLOTS[0];
        $agencyAssignments = RichMenuSlots::agencyAssignments($agencySlot);
        $agencyCounts = [];
        $agencyMap = $this->richMenu->agencyMapForUsers();
        foreach (User::where('richmenu_id', $agencySlot)->whereNotNull('line_id')->pluck('id') as $uid) {
            $aid = $agencyMap[$uid] ?? 0;
            $agencyCounts[$aid] = ($agencyCounts[$aid] ?? 0) + 1;
        }

        // 複製して作成：作成済みの定義をフォームの初期値に使う
        $dup = null;
        if ($request->query('dup')) {
            $dup = RichMenuSlots::definition($request->query('dup'));
            if ($dup && isset($dup['menu']['name'])) {
                $dup['menu']['name'] .= ' のコピー';
            }
        }

        return view('admin.settings.richmenu', [
            'slots' => $slots,
            'assignments' => $assignments,
            'definitions' => $definitions,
            'lineMenus' => $lineMenus,
            'lineMenuIds' => $lineMenuIds,
            'defaultId' => $defaultId,
            'apiError' => $apiError,
            'slotCounts' => $slotCounts,
            'actionTypes' => self::actionTypes(),
            'dup' => $dup,
            'isHarness' => $this->richMenu->isHarness(),
            'agencies' => $agencies,
            'agencySlot' => $agencySlot,
            'agencyAssignments' => $agencyAssignments,
            'agencyCounts' => $agencyCounts,
        ]);
    }

    /**
     * 枠の割り当て（共通・代理店ごと）を保存する。
     * すべて保存してから、変わった枠／代理店の既存ユーザーに再適用する（順番が逆だと古い設定で適用してしまうため）
     */
    public function updateAssignments(Request $request)
    {
        $messages = [];
        $errors = [];
        $applyExisting = $request->boolean('apply_existing');
        $changedSlots = [];     // 共通の設定を変えた枠
        $changedAgencies = [];  // [[slot, agency_id], ...]

        foreach (RichMenuSlots::keys() as $slot) {
            $new = trim((string) $request->input("slot.{$slot}", ''));
            if ($new === '' || $new === RichMenuSlots::id($slot)) {
                continue;
            }
            RichMenuSlots::save($slot, $new);
            $changedSlots[] = $slot;
            $messages[] = RichMenuSlots::label($slot) . ' の割り当てを変更しました。';
        }

        // 代理店ごとの設定（空＝共通の設定を使う）
        foreach ((array) $request->input('agency', []) as $slot => $byAgency) {
            if (!RichMenuSlots::isSlot($slot) || !RichMenuSlots::supportsAgency($slot)) {
                continue;
            }
            foreach ((array) $byAgency as $agencyId => $value) {
                $agencyId = (int) $agencyId;
                $value = trim((string) $value);
                $agencyName = $agencyId > 0 ? Agency::whereKey($agencyId)->value('name') : null;
                if ($agencyName === null || $value === (RichMenuSlots::agencyId($slot, $agencyId) ?? '')) {
                    continue;
                }
                RichMenuSlots::saveAgency($slot, $agencyId, $value);
                $changedAgencies[] = [$slot, $agencyId];
                $messages[] = RichMenuSlots::label($slot) . "（{$agencyName}）を" . ($value === '' ? '共通の設定に戻しました。' : '変更しました。');
            }
        }

        if ($applyExisting) {
            $results = [];
            foreach ($changedSlots as $slot) {
                $results[] = $this->richMenu->reapplySlot($slot);
            }
            foreach ($changedAgencies as [$slot, $agencyId]) {
                // 共通の設定も変えた枠は、上で枠全体に再適用済み（代理店の設定も反映される）
                if (!in_array($slot, $changedSlots, true)) {
                    $results[] = $this->richMenu->reapplySlot($slot, $agencyId);
                }
            }
            foreach ($results as $r) {
                [$msg, $errs] = $this->describeReapply($r);
                $messages[] = $msg;
                $errors = array_merge($errors, $errs);
            }
        }

        if (!$messages) {
            $messages[] = '変更はありませんでした。';
        }

        return redirect()->route('admin.settings.richmenu')
            ->with('rm_messages', $messages)
            ->with('rm_errors', $errors);
    }

    /** 枠のメニューを、その枠のユーザーに紐付け直す（?agency=ID でその代理店のユーザーだけ） */
    public function reapply(Request $request, string $slot)
    {
        abort_unless(RichMenuSlots::isSlot($slot), 404);
        $agencyId = $request->query('agency') !== null ? (int) $request->query('agency') : null;
        [$msg, $errs] = $this->describeReapply($this->richMenu->reapplySlot($slot, $agencyId));

        return redirect()->route('admin.settings.richmenu')
            ->with('rm_messages', [$msg])
            ->with('rm_errors', $errs);
    }

    /** 登録状況から全員の枠を判定し直して適用 */
    public function recalculate()
    {
        $messages = ['登録状況からユーザー全員の枠を判定し直しました。'];
        $errors = [];
        foreach ($this->richMenu->recalculateAll() as $result) {
            [$msg, $errs] = $this->describeReapply($result);
            $messages[] = $msg;
            $errors = array_merge($errors, $errs);
        }

        return redirect()->route('admin.settings.richmenu')
            ->with('rm_messages', $messages)
            ->with('rm_errors', $errors);
    }

    /** リッチメニューを作成 */
    public function store(Request $request)
    {
        [$menu, $errors] = $this->buildMenuFromRequest($request);

        // 画像：新しくアップロード、または複製元の画像を引き継ぐ
        $file = $request->file('image');
        $dupImage = (string) $request->input('dup_image', '');
        if ($file) {
            if (!in_array($file->getMimeType(), ['image/png', 'image/jpeg'], true)) {
                $errors[] = '画像はPNGまたはJPEGにしてください。';
            }
            if ($file->getSize() > 1024 * 1024) {
                $errors[] = '画像は1MB以下にしてください（LINEの制限）。';
            }
        } elseif ($dupImage === '' || !Storage::disk('public')->exists($dupImage)) {
            $errors[] = '背景画像を選んでください。';
        }

        $assignSlot = (string) $request->input('assign_slot', '');
        if ($assignSlot !== '' && !RichMenuSlots::isSlot($assignSlot)) {
            $assignSlot = '';
        }

        if ($errors) {
            return back()->withInput()->with('rm_errors', $errors);
        }

        if ($file) {
            $imagePath = $file->getRealPath();
            $contentType = $file->getMimeType();
        } else {
            $imagePath = Storage::disk('public')->path($dupImage);
            $contentType = mime_content_type($imagePath) ?: 'image/png';
        }

        try {
            $richMenuId = $this->richMenu->create($menu, $imagePath, $contentType);
        } catch (RuntimeException $e) {
            Log::error('RichMenu create failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('rm_errors', [$e->getMessage()]);
        }

        // 管理用に画像と定義を保存（一覧のサムネイル・複製に使う）
        $ext = $contentType === 'image/jpeg' ? 'jpg' : 'png';
        $storedPath = "richmenus/{$richMenuId}.{$ext}";
        Storage::disk('public')->put($storedPath, file_get_contents($imagePath));
        RichMenuSlots::saveDefinition($richMenuId, [
            'name' => $menu['name'],
            'image' => $storedPath,
            'menu' => $menu,
            'created_at' => now()->toDateTimeString(),
        ]);

        $messages = ["リッチメニュー「{$menu['name']}」を作成しました。（{$richMenuId}）"];
        $errs = [];
        if ($assignSlot !== '') {
            RichMenuSlots::save($assignSlot, $richMenuId);
            $messages[] = RichMenuSlots::label($assignSlot) . ' に割り当てました。';
            [$msg, $errs] = $this->describeReapply($this->richMenu->reapplySlot($assignSlot));
            $messages[] = $msg;
        }

        return redirect()->route('admin.settings.richmenu')
            ->with('rm_messages', $messages)
            ->with('rm_errors', $errs);
    }

    /** リッチメニューを削除（枠に割り当て中のものは削除できない） */
    public function destroy(string $richMenuId)
    {
        $using = RichMenuSlots::slotsUsing($richMenuId);
        $agencyNames = Agency::whereIn('id', RichMenuSlots::agenciesUsing($richMenuId))->pluck('name')->all();
        if ($using || $agencyNames) {
            $labels = implode('、', array_merge(
                array_map([RichMenuSlots::class, 'label'], $using),
                array_map(function ($n) { return "代理店「{$n}」の④"; }, $agencyNames)
            ));
            return redirect()->route('admin.settings.richmenu')
                ->with('rm_errors', ["このメニューは {$labels} に割り当て中のため削除できません。先に別のメニューに切り替えてください。"]);
        }

        try {
            $this->richMenu->delete($richMenuId);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.settings.richmenu')->with('rm_errors', [$e->getMessage()]);
        }

        $def = RichMenuSlots::definition($richMenuId);
        if ($def && !empty($def['image'])) {
            Storage::disk('public')->delete($def['image']);
        }
        RichMenuSlots::removeDefinition($richMenuId);

        return redirect()->route('admin.settings.richmenu')
            ->with('rm_messages', ['リッチメニューを削除しました。このメニューが個別に紐付いていた人には、既定メニューが表示されます。']);
    }

    // ------------------------------------------------------------------

    private function describeReapply(array $r): array
    {
        $label = RichMenuSlots::label($r['slot']);
        if (!empty($r['agency_id'])) {
            $label .= '（' . (Agency::whereKey($r['agency_id'])->value('name') ?? '代理店') . '）';
        }
        $msg = "{$label}：対象 {$r['users']}人のうち {$r['sent']}人に適用しました。";
        if ($r['slot'] === RichMenuSlots::DEFAULT_SLOT) {
            $msg .= '（既定メニューにも設定）';
        }
        $errors = array_map(function ($e) use ($label) {
            return "{$label}：{$e}";
        }, $r['errors']);
        return [$msg, $errors];
    }

    /**
     * フォームの入力から LINE API に送るリッチメニューの定義を組み立てる
     *
     * @return array [$menu, $errors]
     */
    private function buildMenuFromRequest(Request $request): array
    {
        $errors = [];
        $name = trim((string) $request->input('name', ''));
        $chatBar = trim((string) $request->input('chat_bar_text', ''));
        $width = (int) $request->input('width', 2500);
        $height = (int) $request->input('height', 1686);

        if ($name === '') {
            $errors[] = 'メニュー名を入力してください。';
        } elseif (mb_strlen($name) > 300) {
            $errors[] = 'メニュー名は300文字以内にしてください。';
        }
        if (mb_strlen($chatBar) > 14) {
            $errors[] = 'メニューバーの文言は14文字以内にしてください。';
        }
        if ($width < 800 || $width > 2500) {
            $errors[] = '幅は800〜2500の範囲で指定してください（推奨2500）。';
        }
        if ($height < 250) {
            $errors[] = '高さは250以上で指定してください（大サイズ1686、小サイズ843）。';
        }

        $ax = (array) $request->input('area_x', []);
        $ay = (array) $request->input('area_y', []);
        $aw = (array) $request->input('area_w', []);
        $ah = (array) $request->input('area_h', []);
        $types = (array) $request->input('area_type', []);
        $v1s = (array) $request->input('area_val1', []);
        $v2s = (array) $request->input('area_val2', []);

        $areas = [];
        foreach (array_keys($ax) as $i) {
            $num = function ($arr) use ($i) {
                return isset($arr[$i]) && $arr[$i] !== '' && $arr[$i] !== null ? (int) $arr[$i] : null;
            };
            [$x, $y, $w, $h] = [$num($ax), $num($ay), $num($aw), $num($ah)];
            if ($x === null && $y === null && $w === null && $h === null) {
                continue; // 空行
            }
            $label = 'エリア' . ($i + 1) . '：';
            if (!$w || !$h || $w <= 0 || $h <= 0) {
                $errors[] = $label . '幅・高さは正の数で指定してください。';
                continue;
            }
            $x = $x ?? 0;
            $y = $y ?? 0;
            if ($x + $w > $width || $y + $h > $height) {
                $errors[] = $label . "領域が画像サイズ（{$width}×{$height}）をはみ出しています。";
            }

            $type = array_key_exists($types[$i] ?? '', self::actionTypes()) ? $types[$i] : 'message';
            $action = $this->buildAction($type, trim((string) ($v1s[$i] ?? '')), trim((string) ($v2s[$i] ?? '')), $label, $errors);

            $areas[] = [
                'bounds' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
                'action' => $action,
            ];
        }

        if (!$areas) {
            $errors[] = 'タップ領域（エリア）を1つ以上設定してください。';
        } elseif (count($areas) > 20) {
            $errors[] = 'エリアは20個までです（LINEの制限）。';
        }

        $menu = [
            'size' => ['width' => $width, 'height' => $height],
            'selected' => $request->boolean('selected'),
            'name' => $name,
            'chatBarText' => $chatBar !== '' ? $chatBar : 'メニュー',
            'areas' => $areas,
        ];

        return [$menu, $errors];
    }

    private function buildAction(string $type, string $v1, string $v2, string $label, array &$errors): array
    {
        switch ($type) {
            case 'uri':
            case 'uri_external':
                $this->checkUri($v1, $label, $errors);
                $uri = self::stripExternalParam($v1);
                if ($type === 'uri_external') {
                    $uri .= (strpos($uri, '?') === false ? '?' : '&') . 'openExternalBrowser=1';
                }
                return ['type' => 'uri', 'uri' => $uri];

            case 'postback':
                if ($v1 === '') {
                    $errors[] = $label . 'ポストバックのdataを入力してください。';
                }
                $action = ['type' => 'postback', 'data' => $v1];
                if ($v2 !== '') {
                    $action['displayText'] = $v2;
                }
                return $action;

            case 'message':
            default:
                if ($v1 === '') {
                    $errors[] = $label . '送信するテキストを入力してください。';
                }
                return ['type' => 'message', 'text' => $v1];
        }
    }

    /** LINEに送る前に、よくあるURLの間違いを日本語で止める */
    private function checkUri(string $url, string $label, array &$errors): void
    {
        if ($url === '') {
            $errors[] = $label . 'URLを入力してください。';
        } elseif (preg_match('/\s/u', $url)) {
            $errors[] = $label . 'URLに空白（スペースや改行）が入っています。';
        } elseif (!preg_match('#^(https?|line|tel):#i', $url)) {
            $errors[] = $label . 'URLは https:// から始めてください（電話は tel:）。';
        } elseif (preg_match('/[^\x20-\x7E]/', $url)) {
            $errors[] = $label . 'URLに日本語などの全角文字が含まれています。';
        } elseif (preg_match('#^https?://liff\.line\.me/?(\?|$)#i', $url)) {
            $errors[] = $label . 'LIFF IDが入っていません（https://liff.line.me/ のうしろにLIFF IDが必要です）。';
        }
    }

    public static function stripExternalParam(string $url): string
    {
        $url = preg_replace('/([?&])openExternalBrowser=1(&|$)/', '$1', $url);
        return rtrim($url, '?&');
    }

    /** 保存済みのアクションを、フォームの [種類, 値1, 値2] に戻す（複製用） */
    public static function actionToFields(array $action): array
    {
        switch ($action['type'] ?? 'message') {
            case 'uri':
                $uri = $action['uri'] ?? '';
                if (preg_match('/[?&]openExternalBrowser=1(&|$)/', $uri)) {
                    return ['uri_external', self::stripExternalParam($uri), ''];
                }
                return ['uri', $uri, ''];
            case 'postback':
                return ['postback', $action['data'] ?? '', $action['displayText'] ?? ''];
            default:
                return ['message', $action['text'] ?? '', ''];
        }
    }
}
