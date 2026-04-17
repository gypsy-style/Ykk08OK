@extends('admin.layouts.app')

@section('title', '管理画面 [カスタムCSS]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>設定</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <div class="lma-setting_nav" style="margin-bottom: 20px;">
            <ul style="display: flex; gap: 10px; list-style: none; padding: 0;">
                <li><a href="{{ route('admin.settings.custom_css') }}" style="font-weight: bold;">カスタムCSS</a></li>
            </ul>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="background: #d4edda; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.update_custom_css') }}" method="POST">
            @csrf
            <dl class="lma-form_box">
                <dt><label for="custom_css">カスタムCSS</label></dt>
                <dd>
                    <textarea class="form-control" id="custom_css" name="custom_css" rows="20" style="font-family: monospace; font-size: 13px;">{{ old('custom_css', $customCss) }}</textarea>
                </dd>
            </dl>

            @if($errors->has('custom_css'))
                <p style="color: red;">{{ $errors->first('custom_css') }}</p>
            @endif

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>
@endsection