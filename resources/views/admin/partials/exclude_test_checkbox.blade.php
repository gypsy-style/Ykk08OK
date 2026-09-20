{{--
    テストデータ除外のチェックボックス
    $route       ... 送信先のルート名
    $excludeTest ... 現在の状態（bool）
    $params      ... 一緒に引き継ぐGETパラメータの連想配列（省略可）
--}}
<div class="lma-content_block nobg" style="width:100%;">
    <form method="GET" action="{{ route($route) }}" class="lma-checkbox_box">
        @foreach ($params ?? [] as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="hidden" name="exclude_test" value="0">
        <label>
            <input type="checkbox" name="exclude_test" value="1" onchange="this.form.submit();" {{ $excludeTest ? 'checked' : '' }}>
            テストを含めない
        </label>
    </form>
</div>
