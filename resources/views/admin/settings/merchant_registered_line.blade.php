@extends('admin.layouts.app')

@section('title', '管理画面 [加盟店登録LINE通知]')

@push('head')
<style>
    .mrl-help {
        margin: 0 0 12px;
        padding: 8px 12px;
        background: #f8f9fa;
        border-left: 3px solid #1e6bd6;
        font-size: 12px;
        color: #555;
    }
    .mrl-search {
        width: 100%;
        max-width: 320px;
        padding: 6px 8px;
        margin: 0 0 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .mrl-list {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 480px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .mrl-list li + li {
        border-top: 1px solid #eee;
    }
    .mrl-list label {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        cursor: pointer;
    }
    .mrl-list label:hover {
        background: #f5f8ff;
    }
    .mrl-merchant {
        font-size: 12px;
        color: #666;
    }
    .mrl-empty {
        color: #666;
    }
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
        @if(session('success'))
            <div class="alert alert-success" style="background: #d4edda; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.update_merchant_registered_line') }}" method="POST">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'merchant_registered_line'])
                </dt>
                <dd>
                    <p class="mrl-help">
                        加盟店が新規登録されると、チェックしたユーザーのLINEへ「加盟店が登録されました。」と、その加盟店の管理画面へのリンクを送ります。<br>
                        LINE IDが登録されているユーザーだけを表示しています。
                    </p>

                    @if($users->isEmpty())
                        <p class="mrl-empty">LINE IDが登録されているユーザーがいません。</p>
                    @else
                        <input type="search" class="mrl-search" id="mrl-search" placeholder="名前・加盟店名で絞り込み">
                        <ul class="mrl-list" id="mrl-list">
                            @foreach($users as $user)
                                <li data-search="{{ mb_strtolower(($user->name ?? '') . ' ' . ($user->display_name ?? '') . ' ' . optional($user->merchant)->name) }}">
                                    <label>
                                        <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" {{ $user->is_notify_target ? 'checked' : '' }}>
                                        <span>
                                            {{ $user->name ?: ($user->display_name ?: 'ID:' . $user->id) }}
                                            @if($user->merchant)
                                                <span class="mrl-merchant">（{{ $user->merchant->name }}）</span>
                                            @endif
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </dd>
            </dl>

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>

<script>
    // 絞り込みは表示を隠すだけ。隠れたチェック済みの行もそのまま送信される
    (function () {
        var search = document.getElementById('mrl-search');
        if (!search) return;
        // 絞り込み欄で Enter を押してもフォームを送信しない
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            document.querySelectorAll('#mrl-list li').forEach(function (li) {
                li.style.display = !q || li.dataset.search.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    })();
</script>
@endsection
