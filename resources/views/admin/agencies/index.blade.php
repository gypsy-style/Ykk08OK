@extends('admin.layouts.app')

@section('title', '管理画面 [商品]')

@section('content')
<section class="lma-content">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>代理店一覧</h2>
        </div>
    </div>
    <div>
        <p class="lma-btn_box"><a href="{{ route('admin.agencies.create') }}">代理店新規作成</a></p>
    </div>
    <div class="lma-content_block staff nobg">
        <ul class="lma-user_list store">
            @foreach($agencies as $agency)
            <li>
                <div class="lma-user_box">
                    <div class="user_info">
                        <h3 class="name">{{ $agency->name }}</h3>
                    </div>
                    <div class="lma-btn_box btn_list">
                        <a href="{{ route('admin.agencies.edit', $agency->id) }}" class="btn btn-primary btn-sm">編集</a>
                        <form action="{{ route('admin.agencies.destroy', $agency->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="gy">削除</button>
                        </form>
                        <!-- <button class="gy" type="button">スタッフ管理</button>
                        <button class="bu" type="button">削除</button> -->
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endsection