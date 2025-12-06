{{-- resources/views/admin/agencies/show.blade.php --}}
@extends('admin.layouts.app')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>代理店詳細</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <dl class="lma-form_box">
            <dt>代理店コード</dt>
            <dd>
                <p>{{ $agency->agency_code }}</p>
            </dd>
            <dt>代理店名</dt>
            <dd>
                <p>{{ $agency->name }}</p>
            </dd>
            <dt>住所</dt>
            <dd>
                <p>〒{{ $agency->postal_code1 }}-{{ $agency->postal_code2 }}<br>{{ $agency->address }}</p>
            </dd>
            <dt>電話番号</dt>
            <dd>
                <p>{{ $agency->phone }}</p>
            </dd>
            <dt>担当者名</dt>
            <dd>
                <p>{{ $agency->contact_person }}</p>
            </dd>
            <dt>メールアドレス</dt>
            <dd>
                <p>{{ $agency->email }}</p>
            </dd>
            @if($agency->logo_image)
            <dt>ロゴ画像</dt>
            <dd>
                <p><img src="{{ asset('storage/app/public/' . $agency->logo_image) }}" width="200"></p>
            </dd>
            @endif
        </dl>
        <a href="{{ route('admin.agencies.index') }}" class="btn btn-secondary">一覧に戻る</a>
    </div>



</section>

@endsection