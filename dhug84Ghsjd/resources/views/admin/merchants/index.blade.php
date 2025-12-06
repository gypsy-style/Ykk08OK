@extends('admin.layouts.app')

@section('title', '管理画面 [加盟店一覧]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>加盟店一覧</h2>
        </div>
    </div>
    <div class="lma-content_block staff nobg">
        <ul class="lma-user_list store">
            @foreach($merchants as $merchant)
            <li>
                <div class="lma-user_box">
                    <div class="user_info">
                        <h3 class="name">{{ $merchant->name }}</h3>
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