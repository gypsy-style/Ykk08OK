@extends('admin.layouts.app')

@section('title', '管理画面 [リッチメニュー]')

@push('head')
<style>
    .rm-section { margin-bottom: 36px; }
    .rm-section h3 { font-size: 16px; margin: 0 0 8px; padding-bottom: 6px; border-bottom: 2px solid #e5e5e5; }
    .rm-desc { font-size: 13px; color: #555; line-height: 1.7; margin: 0 0 12px; }
    .rm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rm-table th, .rm-table td { border: 1px solid #ddd; padding: 8px; vertical-align: middle; text-align: left; }
    .rm-table th { background: #f6f7f9; white-space: nowrap; }
    .rm-table select, .rm-table input[type="text"], .rm-table input[type="number"] { width: 100%; box-sizing: border-box; padding: 5px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
    .rm-id { font-family: monospace; font-size: 11px; color: #666; word-break: break-all; user-select: all; }
    .rm-badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 11px; margin: 1px 2px 1px 0; background: #e6f0ff; color: #1e6bd6; white-space: nowrap; }
    .rm-badge.default { background: #e3f7ea; color: #0a7a3a; }
    .rm-badge.warn { background: #fdecea; color: #b32d2e; }
    .rm-thumb { width: 140px; height: auto; border: 1px solid #ddd; border-radius: 4px; display: block; }
    .rm-btn { display: inline-block; padding: 6px 12px; border: 1px solid #bbb; border-radius: 4px; background: #fff; font-size: 12px; cursor: pointer; text-decoration: none; color: #333; white-space: nowrap; }
    .rm-btn:hover { background: #f3f3f3; }
    .rm-btn.primary { background: #1e6bd6; border-color: #1e6bd6; color: #fff; }
    .rm-btn.danger { border-color: #d9534f; color: #b32d2e; }
    .rm-btn[disabled] { opacity: .45; cursor: not-allowed; }
    .rm-notice { padding: 10px 14px; border-radius: 4px; margin-bottom: 12px; font-size: 13px; line-height: 1.7; }
    .rm-notice.ok { background: #d4edda; }
    .rm-notice.ng { background: #fdecea; color: #7a1f1f; }
    .rm-notice.info { background: #fff8e1; }
    .rm-notice ul { margin: 0; padding-left: 18px; list-style: disc; }
    .rm-form-grid { display: grid; grid-template-columns: 11em 1fr; gap: 10px 14px; align-items: center; font-size: 13px; margin-bottom: 16px; }
    .rm-form-grid .hint { font-size: 12px; color: #777; }
    .rm-preview { position: relative; display: inline-block; max-width: 100%; margin-top: 8px; }
    .rm-preview img { display: block; max-width: 420px; width: 100%; height: auto; border: 1px solid #ccc; }
    .rm-preview .box { position: absolute; border: 2px solid rgba(30,107,214,.9); background: rgba(30,107,214,.18); color: #fff; font-size: 12px; font-weight: bold; display: flex; align-items: center; justify-content: center; text-shadow: 0 0 3px #000; box-sizing: border-box; }
    .rm-hint { font-size: 11px; color: #777; margin: 3px 0 0; }
</style>
@endpush

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>設定</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <dl class="lma-form_box">
            <dt>
                @include('admin.settings._nav', ['active' => 'richmenu'])
            </dt>
            <dd>
                @foreach ((array) session('rm_messages', []) as $m)
                    <div class="rm-notice ok">{{ $m }}</div>
                @endforeach
                @if (session('rm_errors'))
                    <div class="rm-notice ng">
                        <ul>
                            @foreach ((array) session('rm_errors') as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($isHarness)
                    <div class="rm-notice info">リッチメニューは LINE Harness 側で管理する設定になっているため（LINE_RICHMENU_DRIVER=harness）、この画面からは操作できません。</div>
                @elseif ($apiError)
                    <div class="rm-notice ng">LINEからリッチメニューの情報を取得できませんでした：{{ $apiError }}</div>
                @endif

                {{-- ============ 1. 割り当て ============ --}}
                <div class="rm-section">
                    <h3>登録段階ごとのリッチメニュー</h3>
                    <p class="rm-desc">
                        ユーザーの登録段階（ユーザー登録・サロン登録・承認）に応じて表示するメニューを選びます。<br>
                        メニューを変えたときは「既存ユーザーにも適用する」にチェックを入れて保存すると、その段階の人全員のメニューが切り替わります。
                        LINE側の反映には数分かかることがあります。
                    </p>

                    <form method="POST" action="{{ route('admin.settings.richmenu.assign') }}">
                        @csrf
                        <table class="rm-table">
                            <thead>
                                <tr>
                                    <th>段階</th>
                                    <th style="width:70px;">人数</th>
                                    <th>リッチメニュー</th>
                                    <th style="width:110px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($slots as $slot => $label)
                                    @php
                                        $current = $assignments[$slot] ?? null;
                                        $exists = $current && in_array($current, $lineMenuIds, true);
                                    @endphp
                                    <tr>
                                        <td>
                                            <b>{{ $label }}</b>
                                            @if (!\App\Services\RichMenuSlots::isConfigured($slot) && $current)
                                                <br><span class="rm-badge">.env の設定を使用中</span>
                                            @endif
                                            @if ($slot === \App\Services\RichMenuSlots::DEFAULT_SLOT && $current && $defaultId && $defaultId !== $current)
                                                <br><span class="rm-badge warn">LINEの既定メニューと不一致</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right;">{{ number_format($slotCounts[$slot] ?? 0) }}人</td>
                                        <td>
                                            <select name="slot[{{ $slot }}]">
                                                @if ($current && !$exists)
                                                    <option value="{{ $current }}" selected>（LINE側に見つかりません）{{ $current }}</option>
                                                @elseif (!$current)
                                                    <option value="" selected>（未設定）</option>
                                                @endif
                                                @foreach ($lineMenus as $m)
                                                    <option value="{{ $m['richMenuId'] }}" {{ $current === $m['richMenuId'] ? 'selected' : '' }}>
                                                        {{ $m['name'] }}（{{ $m['size']['width'] }}×{{ $m['size']['height'] }}）@if ($m['richMenuId'] === $defaultId) ★既定 @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if ($current)
                                                <div class="rm-id">{{ $current }}</div>
                                            @endif
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="submit" class="rm-btn"
                                                formaction="{{ route('admin.settings.richmenu.reapply', $slot) }}"
                                                onclick="return confirm('{{ $label }} の人全員に、いま割り当てているメニューを適用し直します。よろしいですか？');"
                                                @if (!$current || $isHarness) disabled @endif>再適用</button>
                                        </td>
                                    </tr>
                                    @if ($slot === $agencySlot)
                                        <tr class="rm-agency-head">
                                            <td colspan="4" style="background:#fafbfc; font-size:12px; color:#555;">
                                                ▼ 代理店ごとに変える場合（「共通の設定を使う」の代理店には、上の ④ のメニューが表示されます）
                                            </td>
                                        </tr>
                                        @foreach ($agencies as $agency)
                                            @php $agencyCurrent = $agencyAssignments[$agency->id] ?? ''; @endphp
                                            <tr class="rm-agency-row">
                                                <td style="padding-left: 24px;">
                                                    └ {{ $agency->name }}
                                                    @if ($agency->is_test)<span class="rm-badge warn">テスト</span>@endif
                                                </td>
                                                <td style="text-align:right;">{{ number_format($agencyCounts[$agency->id] ?? 0) }}人</td>
                                                <td>
                                                    <select name="agency[{{ $slot }}][{{ $agency->id }}]">
                                                        <option value="">共通の設定を使う</option>
                                                        @if ($agencyCurrent && !in_array($agencyCurrent, $lineMenuIds, true))
                                                            <option value="{{ $agencyCurrent }}" selected>（LINE側に見つかりません）{{ $agencyCurrent }}</option>
                                                        @endif
                                                        @foreach ($lineMenus as $m)
                                                            <option value="{{ $m['richMenuId'] }}" {{ $agencyCurrent === $m['richMenuId'] ? 'selected' : '' }}>
                                                                {{ $m['name'] }}（{{ $m['size']['width'] }}×{{ $m['size']['height'] }}）
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td style="text-align:center;">
                                                    <button type="submit" class="rm-btn"
                                                        formaction="{{ route('admin.settings.richmenu.reapply', ['slot' => $slot, 'agency' => $agency->id]) }}"
                                                        onclick="return confirm('{{ $agency->name }} の承認済みの人全員に、メニューを適用し直します。よろしいですか？');"
                                                        @if ($isHarness || !($agencyCounts[$agency->id] ?? 0)) disabled @endif>再適用</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                        @if (!empty($agencyCounts[0]))
                                            <tr class="rm-agency-row">
                                                <td style="padding-left: 24px; color:#777;">└ 代理店未設定のサロン</td>
                                                <td style="text-align:right;">{{ number_format($agencyCounts[0]) }}人</td>
                                                <td style="font-size:12px; color:#777;">共通の設定を使います</td>
                                                <td></td>
                                            </tr>
                                        @endif
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                        <p style="margin: 12px 0 0; font-size: 13px;">
                            <label><input type="checkbox" name="apply_existing" value="1" checked> 変更した段階の既存ユーザーにも適用する</label>
                        </p>
                        <p class="lma-btn_box" style="margin-top: 12px;">
                            <button type="submit" class="btn btn-primary" @if ($isHarness) disabled @endif>割り当てを保存</button>
                        </p>
                    </form>

                    <form method="POST" action="{{ route('admin.settings.richmenu.recalculate') }}" style="margin-top: 16px;"
                          onsubmit="return confirm('サロンの登録状況・承認状況から、ユーザー全員の段階を判定し直してメニューを適用します。\nユーザー一覧で手動で変えたメニューも上書きされます。よろしいですか？');">
                        @csrf
                        <p class="rm-desc" style="margin-bottom: 6px;">
                            段階の記録がずれている人がいる場合（例：承認済みなのに承認待ちのメニューのまま）は、こちらで全員を登録状況から判定し直せます。
                        </p>
                        <button type="submit" class="rm-btn" @if ($isHarness) disabled @endif>登録状況から全員を判定し直して適用</button>
                    </form>
                </div>

                {{-- ============ 2. 作成済み一覧 ============ --}}
                <div class="rm-section">
                    <h3>LINEに登録されているリッチメニュー</h3>
                    <p class="rm-desc">
                        古いメニューを削除すると、そのメニューが紐付いていた人には既定メニューが表示されるようになります。段階に割り当て中のメニューは削除できません。
                    </p>
                    @if (!$lineMenus)
                        <p class="rm-desc">まだありません。</p>
                    @else
                        <table class="rm-table">
                            <thead>
                                <tr>
                                    <th style="width:150px;">画像</th>
                                    <th>名前 / ID</th>
                                    <th>使用中</th>
                                    <th style="width:170px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lineMenus as $m)
                                    @php
                                        $def = $definitions[$m['richMenuId']] ?? null;
                                        $using = \App\Services\RichMenuSlots::slotsUsing($m['richMenuId']);
                                        $usingAgencies = $agencies->whereIn('id', array_keys(array_filter($agencyAssignments, fn ($id) => $id === $m['richMenuId'])));
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($def && !empty($def['image']))
                                                <img class="rm-thumb" src="{{ asset('storage/' . $def['image']) }}" alt="">
                                            @else
                                                <span class="rm-desc">この画面以外で作成</span>
                                            @endif
                                        </td>
                                        <td>
                                            <b>{{ $m['name'] }}</b><br>
                                            <span style="font-size:12px;color:#666;">{{ $m['size']['width'] }}×{{ $m['size']['height'] }} ／ メニューバー「{{ $m['chatBarText'] }}」／ エリア{{ count($m['areas'] ?? []) }}個</span>
                                            <div class="rm-id">{{ $m['richMenuId'] }}</div>
                                        </td>
                                        <td>
                                            @if ($m['richMenuId'] === $defaultId)
                                                <span class="rm-badge default">既定メニュー</span><br>
                                            @endif
                                            @foreach ($using as $s)
                                                <span class="rm-badge">{{ $slots[$s] }}</span><br>
                                            @endforeach
                                            @foreach ($usingAgencies as $ua)
                                                <span class="rm-badge">④ {{ $ua->name }}</span><br>
                                            @endforeach
                                        </td>
                                        <td style="text-align:center;">
                                            @if ($def)
                                                <a class="rm-btn" href="{{ route('admin.settings.richmenu', ['dup' => $m['richMenuId']]) }}#rm-create">複製して作成</a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.settings.richmenu.destroy', $m['richMenuId']) }}" style="display:inline;"
                                                  onsubmit="return confirm('「{{ $m['name'] }}」をLINEから削除します。元に戻せません。よろしいですか？');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rm-btn danger" @if ($using || $usingAgencies->isNotEmpty() || $isHarness) disabled title="割り当て中のため削除できません" @endif>削除</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- ============ 3. 新規作成 ============ --}}
                @php
                    $menu = $dup['menu'] ?? null;
                    if (old('area_x') !== null) {
                        $rows = [];
                        foreach ((array) old('area_x') as $i => $_) {
                            $rows[] = [
                                'x' => old("area_x.$i"), 'y' => old("area_y.$i"), 'w' => old("area_w.$i"), 'h' => old("area_h.$i"),
                                'type' => old("area_type.$i", 'message'), 'v1' => old("area_val1.$i"), 'v2' => old("area_val2.$i"),
                            ];
                        }
                    } elseif ($menu) {
                        $rows = [];
                        foreach ($menu['areas'] as $a) {
                            [$t, $v1, $v2] = \App\Http\Controllers\Admin\RichMenuController::actionToFields($a['action']);
                            $rows[] = ['x' => $a['bounds']['x'], 'y' => $a['bounds']['y'], 'w' => $a['bounds']['width'], 'h' => $a['bounds']['height'], 'type' => $t, 'v1' => $v1, 'v2' => $v2];
                        }
                    } else {
                        $rows = [['x' => '', 'y' => '', 'w' => '', 'h' => '', 'type' => 'message', 'v1' => '', 'v2' => '']];
                    }
                    $dupImage = old('dup_image', $dup['image'] ?? '');
                @endphp
                <div class="rm-section" id="rm-create">
                    <h3>{{ $dup ? '複製して新規作成' : 'リッチメニューを作成' }}</h3>
                    <p class="rm-desc">背景画像とタップ領域（エリア）を指定して作成します。座標は画像の左上が (0, 0) です。画像を選ぶと、エリアの位置が画像の上に表示されます。</p>

                    <form method="POST" action="{{ route('admin.settings.richmenu.store') }}" enctype="multipart/form-data" id="rmCreateForm">
                        @csrf
                        <div class="rm-form-grid">
                            <label>メニュー名（管理用）</label>
                            <input type="text" name="name" required value="{{ old('name', $menu['name'] ?? '') }}">

                            <label>メニューバーの文言</label>
                            <div>
                                <input type="text" name="chat_bar_text" maxlength="14" value="{{ old('chat_bar_text', $menu['chatBarText'] ?? 'メニュー') }}">
                                <div class="hint">トーク画面の下に表示される文言（14文字以内）</div>
                            </div>

                            <label>サイズ（px）</label>
                            <div>
                                幅 <input type="number" name="width" id="rmWidth" value="{{ old('width', $menu['size']['width'] ?? 2500) }}" style="width:90px;">
                                × 高さ <input type="number" name="height" id="rmHeight" value="{{ old('height', $menu['size']['height'] ?? 1686) }}" style="width:90px;">
                                <button type="button" class="rm-btn" onclick="rmSetSize(2500,1686)">大 2500×1686</button>
                                <button type="button" class="rm-btn" onclick="rmSetSize(2500,843)">小 2500×843</button>
                            </div>

                            <label>初期表示</label>
                            <label><input type="checkbox" name="selected" value="1" {{ old('selected', $menu['selected'] ?? false) ? 'checked' : '' }}> トークを開いたときにメニューを開いた状態にする</label>

                            <label>背景画像</label>
                            <div>
                                <input type="file" name="image" id="rmImage" accept="image/png,image/jpeg">
                                <div class="hint">サイズと同じ縦横比のPNGまたはJPEG、1MB以下。</div>
                                @if ($dupImage)
                                    <input type="hidden" name="dup_image" value="{{ $dupImage }}">
                                    <div class="hint">画像を選ばなければ、複製元の画像を使います。</div>
                                @endif
                                <div class="rm-preview" id="rmPreview" @if (!$dupImage) style="display:none;" @endif>
                                    <img id="rmPreviewImg" src="{{ $dupImage ? asset('storage/' . $dupImage) : '' }}" alt="">
                                </div>
                            </div>
                        </div>

                        <table class="rm-table" id="rmAreaTable">
                            <thead>
                                <tr>
                                    <th style="width:36px;">#</th>
                                    <th style="width:84px;">X</th>
                                    <th style="width:84px;">Y</th>
                                    <th style="width:84px;">幅</th>
                                    <th style="width:84px;">高さ</th>
                                    <th style="width:170px;">タップしたとき</th>
                                    <th>設定値</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $r)
                                    <tr class="rm-area-row">
                                        <td class="rm-no"></td>
                                        <td><input type="number" name="area_x[]" value="{{ $r['x'] }}"></td>
                                        <td><input type="number" name="area_y[]" value="{{ $r['y'] }}"></td>
                                        <td><input type="number" name="area_w[]" value="{{ $r['w'] }}"></td>
                                        <td><input type="number" name="area_h[]" value="{{ $r['h'] }}"></td>
                                        <td>
                                            <select name="area_type[]" class="rm-type">
                                                @foreach ($actionTypes as $tv => $tl)
                                                    <option value="{{ $tv }}" {{ $r['type'] === $tv ? 'selected' : '' }}>{{ $tl }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="area_val1[]" class="rm-v1" value="{{ $r['v1'] }}">
                                            <input type="text" name="area_val2[]" class="rm-v2" value="{{ $r['v2'] }}" style="margin-top:4px;">
                                            <p class="rm-hint"></p>
                                        </td>
                                        <td style="text-align:center;"><button type="button" class="rm-btn rm-remove">削除</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p style="margin: 8px 0 0;">
                            <button type="button" class="rm-btn" id="rmAddArea">＋ エリアを追加</button>
                            <button type="button" class="rm-btn" onclick="rmGrid(3,2)">6分割（3×2）で並べる</button>
                            <button type="button" class="rm-btn" onclick="rmGrid(2,2)">4分割（2×2）</button>
                            <button type="button" class="rm-btn" onclick="rmGrid(3,1)">3分割（横）</button>
                        </p>

                        <div class="rm-form-grid" style="margin-top: 18px;">
                            <label>作成後に割り当てる</label>
                            <div>
                                <select name="assign_slot">
                                    <option value="">割り当てない（あとで選ぶ）</option>
                                    @foreach ($slots as $slot => $label)
                                        <option value="{{ $slot }}" {{ old('assign_slot') === $slot ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="hint">選ぶと、作成後にその段階へ割り当て、既存ユーザーにも適用します。</div>
                            </div>
                        </div>

                        <p class="lma-btn_box">
                            <button type="submit" class="btn btn-primary" @if ($isHarness) disabled @endif>作成する</button>
                        </p>
                    </form>
                </div>
            </dd>
        </dl>
    </div>
</section>

<template id="rmAreaTemplate">
    <tr class="rm-area-row">
        <td class="rm-no"></td>
        <td><input type="number" name="area_x[]"></td>
        <td><input type="number" name="area_y[]"></td>
        <td><input type="number" name="area_w[]"></td>
        <td><input type="number" name="area_h[]"></td>
        <td>
            <select name="area_type[]" class="rm-type">
                @foreach ($actionTypes as $tv => $tl)
                    <option value="{{ $tv }}">{{ $tl }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="text" name="area_val1[]" class="rm-v1">
            <input type="text" name="area_val2[]" class="rm-v2" style="margin-top:4px;">
            <p class="rm-hint"></p>
        </td>
        <td style="text-align:center;"><button type="button" class="rm-btn rm-remove">削除</button></td>
    </tr>
</template>

<script>
(function () {
    var tbody = document.querySelector('#rmAreaTable tbody');
    var widthEl = document.getElementById('rmWidth');
    var heightEl = document.getElementById('rmHeight');

    window.rmSetSize = function (w, h) {
        widthEl.value = w;
        heightEl.value = h;
        drawPreview();
    };

    // 動作の種類に応じて入力欄の案内を切り替える
    function updateHint(row) {
        var type = row.querySelector('.rm-type').value;
        var v1 = row.querySelector('.rm-v1');
        var v2 = row.querySelector('.rm-v2');
        var hint = row.querySelector('.rm-hint');
        v2.style.display = 'none';
        if (type === 'message') {
            v1.placeholder = '送信するテキスト';
            hint.textContent = 'タップするとこのテキストがトークに送信されます。';
        } else if (type === 'uri') {
            v1.placeholder = 'https://liff.line.me/... など';
            hint.textContent = 'LINE内のブラウザで開きます。注文画面などのLIFFはこちら。';
        } else if (type === 'uri_external') {
            v1.placeholder = 'https://...（LIFF以外の通常のページ）';
            hint.innerHTML = v1.value.indexOf('liff.line.me') >= 0
                ? '<b style="color:#b32d2e;">LIFFは外部ブラウザでは正しく動きません。「URLを開く（LINE内）」にしてください。</b>'
                : 'Safari・Chromeなどで開きます（openExternalBrowser=1 を自動で付けます）。';
        } else if (type === 'postback') {
            v1.placeholder = 'data（例: action=menu&id=1）';
            v2.placeholder = 'トークに表示するテキスト（任意）';
            v2.style.display = 'block';
            hint.textContent = 'Webhookで受け取って処理する場合に使います。';
        }
    }

    function renumber() {
        tbody.querySelectorAll('.rm-area-row').forEach(function (row, i) {
            row.querySelector('.rm-no').textContent = i + 1;
        });
    }

    function bindRow(row) {
        row.querySelector('.rm-type').addEventListener('change', function () { updateHint(row); });
        row.querySelector('.rm-v1').addEventListener('input', function () { updateHint(row); });
        row.querySelectorAll('input[type="number"]').forEach(function (el) { el.addEventListener('input', drawPreview); });
        row.querySelector('.rm-remove').addEventListener('click', function () {
            if (tbody.querySelectorAll('.rm-area-row').length > 1) {
                row.remove();
                renumber();
                drawPreview();
            }
        });
        updateHint(row);
    }

    function addRow(values) {
        var tpl = document.getElementById('rmAreaTemplate');
        var row = tpl.content.firstElementChild.cloneNode(true);
        if (values) {
            row.querySelector('[name="area_x[]"]').value = values.x;
            row.querySelector('[name="area_y[]"]').value = values.y;
            row.querySelector('[name="area_w[]"]').value = values.w;
            row.querySelector('[name="area_h[]"]').value = values.h;
        }
        tbody.appendChild(row);
        bindRow(row);
        renumber();
        return row;
    }

    // 画像サイズを cols×rows に均等分割してエリアを並べる（既存の動作設定はできるだけ残す）
    window.rmGrid = function (cols, rows) {
        var W = parseInt(widthEl.value, 10) || 2500;
        var H = parseInt(heightEl.value, 10) || 1686;
        var existing = Array.prototype.slice.call(tbody.querySelectorAll('.rm-area-row'));
        var n = cols * rows;
        for (var i = 0; i < n; i++) {
            var c = i % cols, r = Math.floor(i / cols);
            var x = Math.round(W * c / cols), y = Math.round(H * r / rows);
            var v = {
                x: x, y: y,
                w: Math.round(W * (c + 1) / cols) - x,
                h: Math.round(H * (r + 1) / rows) - y
            };
            var row = existing[i];
            if (row) {
                row.querySelector('[name="area_x[]"]').value = v.x;
                row.querySelector('[name="area_y[]"]').value = v.y;
                row.querySelector('[name="area_w[]"]').value = v.w;
                row.querySelector('[name="area_h[]"]').value = v.h;
            } else {
                addRow(v);
            }
        }
        existing.slice(n).forEach(function (row) { row.remove(); });
        renumber();
        drawPreview();
    };

    // 画像の上にエリアを重ねて表示
    var preview = document.getElementById('rmPreview');
    var previewImg = document.getElementById('rmPreviewImg');
    function drawPreview() {
        preview.querySelectorAll('.box').forEach(function (b) { b.remove(); });
        if (!previewImg.getAttribute('src')) return;
        var W = parseInt(widthEl.value, 10) || 2500;
        var H = parseInt(heightEl.value, 10) || 1686;
        tbody.querySelectorAll('.rm-area-row').forEach(function (row, i) {
            var x = parseFloat(row.querySelector('[name="area_x[]"]').value) || 0;
            var y = parseFloat(row.querySelector('[name="area_y[]"]').value) || 0;
            var w = parseFloat(row.querySelector('[name="area_w[]"]').value) || 0;
            var h = parseFloat(row.querySelector('[name="area_h[]"]').value) || 0;
            if (!w || !h) return;
            var box = document.createElement('div');
            box.className = 'box';
            box.style.left = (x / W * 100) + '%';
            box.style.top = (y / H * 100) + '%';
            box.style.width = (w / W * 100) + '%';
            box.style.height = (h / H * 100) + '%';
            box.textContent = i + 1;
            preview.appendChild(box);
        });
    }
    document.getElementById('rmImage').addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            preview.style.display = '';
            previewImg.onload = function () {
                // 画像の実サイズをサイズ欄に反映
                widthEl.value = previewImg.naturalWidth;
                heightEl.value = previewImg.naturalHeight;
                drawPreview();
            };
        };
        reader.readAsDataURL(file);
    });
    widthEl.addEventListener('input', drawPreview);
    heightEl.addEventListener('input', drawPreview);

    tbody.querySelectorAll('.rm-area-row').forEach(bindRow);
    renumber();
    document.getElementById('rmAddArea').addEventListener('click', function () { addRow(); drawPreview(); });
    if (previewImg.getAttribute('src')) {
        previewImg.onload = drawPreview;
        if (previewImg.complete) drawPreview();
    }
})();
</script>
@endsection
