{{-- 入力チェックで弾かれた理由を表示（これが無いと、何も表示されずに元の画面へ戻るだけになる） --}}
@if ($errors->any())
    <div style="background:#fdecea; color:#7a1f1f; padding:10px 14px; margin-bottom:15px; border-radius:4px; font-size:14px; line-height:1.7;">
        <b>登録できませんでした。以下を確認してください。</b>
        <ul style="margin:6px 0 0; padding-left:20px; list-style:disc;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
