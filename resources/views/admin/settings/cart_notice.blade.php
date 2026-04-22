@extends('admin.layouts.app')

@section('title', '管理画面 [カート画面のお知らせ]')

@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/theme/monokai.min.css">
<style>
    .CodeMirror {
        border: 1px solid #ccc;
        border-radius: 4px;
        height: 500px;
        font-size: 14px;
        line-height: 1.4;
    }
    .cart-notice-help {
        margin: 8px 0 0;
        padding: 8px 12px;
        background: #f8f9fa;
        border-left: 3px solid #1e6bd6;
        font-size: 12px;
        color: #555;
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

        <form action="{{ route('admin.settings.update_cart_notice') }}" method="POST" id="cartNoticeForm">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'cart_notice'])
                </dt>
                <dd>
                    <label for="cart_notice" style="display:block; margin-bottom:6px; font-weight:bold;">カート画面のお知らせ（HTML入力）</label>
                    <textarea class="form-control" id="cart_notice" name="cart_notice">{{ old('cart_notice', $cartNotice) }}</textarea>
                    <p class="cart-notice-help">
                        HTMLタグをそのまま入力できます（例: <code>&lt;p&gt;</code>, <code>&lt;a href="..."&gt;</code>, <code>&lt;div class="..."&gt;</code> など）。<br>
                        入力内容はカート画面の <code>.lmf-order_postage</code> 内にそのまま出力されます。
                    </p>
                </dd>
            </dl>

            @if($errors->has('cart_notice'))
                <p style="color: red;">{{ $errors->first('cart_notice') }}</p>
            @endif

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/edit/closetag.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/edit/matchtags.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var editor = CodeMirror.fromTextArea(document.getElementById('cart_notice'), {
        mode: 'htmlmixed',
        theme: 'monokai',
        lineNumbers: true,
        autoCloseTags: true,
        matchTags: { bothTags: true },
        indentUnit: 2,
        tabSize: 2,
        indentWithTabs: false,
        lineWrapping: true
    });

    document.getElementById('cartNoticeForm').addEventListener('submit', function() {
        editor.save();
    });
});
</script>
@endsection
