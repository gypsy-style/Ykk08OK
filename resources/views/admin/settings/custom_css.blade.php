@extends('admin.layouts.app')

@section('title', '管理画面 [カスタमCSS]')

@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/theme/monokai.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/lint/lint.min.css">
<style>
    .CodeMirror {
        border: 1px solid #ccc;
        border-radius: 4px;
        height: 500px;
        font-size: 14px;
        line-height: 1.2;
    }
    .css-error-list {
        margin-top: 10px;
        padding: 10px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        display: none;
    }
    .css-error-list ul {
        margin: 0;
        padding-left: 20px;
        list-style: disc;
    }
    .css-error-list li {
        color: #856404;
        font-size: 13px;
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

        <form action="{{ route('admin.settings.update_custom_css') }}" method="POST" id="cssForm">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'custom_css'])
                </dt>
                <dd>
                    <label for="custom_css" style="display:block; margin-bottom:6px; font-weight:bold;">カスタムCSS</label>
                    <textarea class="form-control" id="custom_css" name="custom_css">{{ old('custom_css', $customCss) }}</textarea>
                </dd>
            </dl>

            <div class="css-error-list" id="cssErrors">
                <strong>CSS エラー:</strong>
                <ul id="cssErrorList"></ul>
            </div>

            @if($errors->has('custom_css'))
                <p style="color: red;">{{ $errors->first('custom_css') }}</p>
            @endif

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/css-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/lint/lint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/lint/css-lint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/csslint/1.0.5/csslint.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var editor = CodeMirror.fromTextArea(document.getElementById('custom_css'), {
        mode: 'css',
        theme: 'monokai',
        lineNumbers: true,
        autoCloseBrackets: true,
        matchBrackets: true,
        indentUnit: 4,
        tabSize: 4,
        indentWithTabs: false,
        lineWrapping: true,
        lint: true,
        gutters: ['CodeMirror-lint-markers', 'CodeMirror-linenumbers'],
        extraKeys: {
            'Ctrl-Space': 'autocomplete',
            'Tab': function(cm) {
                if (cm.somethingSelected()) {
                    cm.indentSelection('add');
                } else {
                    cm.replaceSelection('    ', 'end');
                }
            }
        }
    });

    // フォーム送信前にエディタの内容をtextareaに反映
    document.getElementById('cssForm').addEventListener('submit', function() {
        editor.save();
    });
});
</script>
@endsection