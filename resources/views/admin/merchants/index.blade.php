@extends('admin.layouts.app')

@section('title', '管理画面 [加盟店一覧]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>加盟店一覧</h2>
        </div>
    </div>

    <!-- フィルタリングフォーム -->
    <div class="lma-content_block log">
        <form method="GET" action="{{ route('admin.merchants.index') }}" class="filter-form">
            <div class="lma-filter">
                <div class="lma-filter__item">
                    <label for="keyword">キーワード:</label>
                    <input type="text" name="keyword" id="keyword" value="{{ request('keyword') }}" placeholder="店舗名・サロンコード">
                </div>
                <div class="lma-filter__item">
                    <label for="agency_id">代理店:</label>
                    <select name="agency_id" id="agency_id">
                        <option value="">すべて</option>
                        @foreach($agencies as $agency)
                        <option value="{{ $agency->id }}" {{ request('agency_id') == $agency->id ? 'selected' : '' }}>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lma-filter__item">
                    <label for="status">ステータス:</label>
                    <select name="status" id="status">
                        <option value="">すべて</option>
                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>有効</option>
                        <option value="2" {{ request('status') == '2' ? 'selected' : '' }}>無効</option>
                    </select>
                </div>
                <div class="lma-filter__item">
                    <label for="member_rank">会員ランク:</label>
                    <select name="member_rank" id="member_rank">
                        <option value="">すべて</option>
                        @foreach([1, 2, 3] as $rank)
                        <option value="{{ $rank }}" {{ request('member_rank') == $rank ? 'selected' : '' }}>{{ $rank }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lma-filter__item">
                    <button type="submit" class="btn btn-primary">フィルター</button>
                    <a href="{{ route('admin.merchants.index') }}" class="btn btn-secondary">リセット</a>
                </div>
            </div>
        </form>
    </div>

    <div class="lma-content_block staff nobg">
        <ul class="lma-user_list store">
            @foreach($merchants as $merchant)
            <li>
                <div class="lma-user_box {{ $merchant->status == 2 ? 'tbd' : '' }}">
                    <div class="user_info">
                        <h3 class="name">{{ $merchant->name }}</h3>
                        <p class="sub" style="font-size: 0.8em; color: #888;">{{ $merchant->agency->name ?? '代理店未設定' }}　会員ランク{{ $merchant->member_rank ?? '-' }}</p>
                        @if($merchant->bank_account_name)
                        <p class="sub" style="font-size: 0.8em; color: #888; white-space: pre-line;">振込み口座名: {{ $merchant->bank_account_name }}</p>
                        @endif
                    </div>
                    <div class="lma-btn_box btn_list">
                        <a href="{{ route('admin.merchants.edit', $merchant->id) }}" class="btn btn-primary btn-sm">編集</a>
                        <form action="{{ route('admin.merchants.destroy', $merchant->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="gy">削除</button>
                        </form>
                        <!-- <a href="{{ route('admin.merchants.destroy', $merchant->id) }}" class="gy">削除</a> -->
                    </div>

                </div>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endsection