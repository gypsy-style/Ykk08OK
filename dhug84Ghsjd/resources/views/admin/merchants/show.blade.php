{{-- resources/views/admin/agencies/show.blade.php --}}
@extends('admin.layouts.app')

@section('content')
    <h1>代理店詳細</h1>
    <p><strong>代理店名:</strong> {{ $agency->name }}</p>
    <p><strong>郵便番号:</strong> {{ $agency->postal_code1 }}-{{ $agency->postal_code2 }}</p>
    <p><strong>電話番号:</strong> {{ $agency->phone }}</p>
    <p><strong>メールアドレス:</strong> {{ $agency->email }}</p>
    @if($agency->logo_image)
        <p><strong>ロゴ画像:</strong><br><img src="{{ asset('storage/app/public/' . $agency->logo_image) }}" width="200"></p>
    @endif
    <a href="{{ route('admin.agencies.index') }}" class="btn btn-secondary">一覧に戻る</a>
@endsection