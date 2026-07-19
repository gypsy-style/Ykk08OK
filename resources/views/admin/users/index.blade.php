@extends('admin.layouts.app')

@section('title', '管理画面 [ユーザー一覧]')


@section('content')
@php
$richmenuOptions = config('app.richmenus');
@endphp
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>ユーザー一覧</h2>
        </div>
    </div>

    <!-- フィルタリングフォーム -->
    <div class="lma-content_block log">
        <form method="GET" action="{{ route('admin.users.index') }}" class="filter-form">
            <div class="lma-filter">
                <div class="lma-filter__item">
                    <label for="keyword">キーワード:</label>
                    <input type="text" name="keyword" id="keyword" value="{{ request('keyword') }}" placeholder="名前・LINE ID">
                </div>
                <div class="lma-filter__item">
                    <label for="richmenu_id">リッチメニュー:</label>
                    <select name="richmenu_id" id="richmenu_id">
                        <option value="">すべて</option>
                        @foreach($richmenuOptions as $key => $value)
                        <option value="{{ $key }}" {{ request('richmenu_id') == $key ? 'selected' : '' }}>{{ $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lma-filter__item">
                    <button type="submit" class="btn btn-primary">フィルター</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">リセット</a>
                </div>
            </div>
        </form>
    </div>

    <div class="lma-content_block staff nobg">
        <ul class="lma-user_list store">
            @foreach($users as $user)
            <li>
                <div class="lma-user_box">
                    <div class="user_info">
                        <h3 class="name">{{ $user->name }}</h3>
                        <p class="line_id">LINE ID: {{ $user->line_id }}</p>
                    </div>
                    <div class="lma-select_box">
                        リッチメニュー：
                        <select class="form-control richmenu-select" data-user-id="{{ $user->id }}">
                            @foreach($richmenuOptions as $key => $value)
                            <option value="{{ $key }}" {{ $user->richmenu_id == $key ? 'selected' : '' }}>
                                {{ $key }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lma-btn_box btn_list">
                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="gy">削除</button>
                        </form>
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
    </div>

    {{ $users->links('vendor.pagination.lma') }}
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.richmenu-select').forEach(select => {
        select.addEventListener('change', function () {
            let userId = this.dataset.userId;
            let selectedRichmenu = this.value;

            fetch(`${BASE_URL}/admin/users/${userId}/update-richmenu`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ richmenu_id: selectedRichmenu })
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                if (data.success) {
                    alert("リッチメニューが更新されました！");
                } else {
                    alert("更新に失敗しました。");
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
});
</script>
@endsection