@extends('agencies.layouts.app')

@section('title', '注文完了')

@section('content')
<section class="lma-content">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>注文完了</h2>
        </div>
    </div>
    <div class="lma-content_block">
        <p>注文が正常に完了しました。ありがとうございました。</p>
        <a href="{{ route('agencies.orders.index') }}" class="btn">注文一覧に戻る</a>
    </div>
</section>
@endsection