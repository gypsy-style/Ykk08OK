@extends('admin.layouts.app')

@section('title', '管理画面 [特定商取引法]')

@push('head')
<style>
    .ck-editor__editable_inline {
        min-height: 500px;
        font-size: 14px;
        line-height: 1.6;
    }
    .ck.ck-editor {
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    /* エディタ内で見出し・段落スタイルを実表示に反映 */
    .ck-content h1 {
        font-size: 2em;
        font-weight: 700;
        line-height: 1.3;
        margin: 0.67em 0;
    }
    .ck-content h2 {
        font-size: 1.5em;
        font-weight: 700;
        line-height: 1.3;
        margin: 0.75em 0;
    }
    .ck-content h3 {
        font-size: 1.25em;
        font-weight: 700;
        line-height: 1.35;
        margin: 0.83em 0;
    }
    .ck-content h4 {
        font-size: 1.1em;
        font-weight: 700;
        line-height: 1.4;
        margin: 1em 0;
    }
    .ck-content p {
        font-size: 1em;
        line-height: 1.6;
        margin: 0.5em 0;
    }
    .ck-content ul,
    .ck-content ol {
        padding-left: 1.5em;
        margin: 0.5em 0;
    }
    .ck-content ul,
    .ck-content ul li {
        list-style: disc outside;
    }
    .ck-content ol,
    .ck-content ol li {
        list-style: decimal outside;
    }
    .ck-content ul ul,
    .ck-content ul ul li {
        list-style: circle outside;
    }
    .ck-content ul ul ul,
    .ck-content ul ul ul li {
        list-style: square outside;
    }
    .ck-content ol ol,
    .ck-content ol ol li {
        list-style: lower-alpha outside;
    }
    .ck-content ol ol ol,
    .ck-content ol ol ol li {
        list-style: lower-roman outside;
    }
    .ck-content blockquote {
        border-left: 4px solid #ccc;
        margin: 1em 0;
        padding: 0.5em 1em;
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

        <form action="{{ route('admin.settings.update_commercial_law') }}" method="POST" id="commercialLawForm">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'commercial_law'])
                </dt>
                <dd>
                    <label for="commercial_law" style="display:block; margin-bottom:6px; font-weight:bold;">特定商取引法</label>
                    <textarea class="form-control" id="commercial_law" name="commercial_law">{{ old('commercial_law', $commercialLaw) }}</textarea>
                </dd>
            </dl>

            @if($errors->has('commercial_law'))
                <p style="color: red;">{{ $errors->first('commercial_law') }}</p>
            @endif

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let editorInstance = null;

    ClassicEditor
        .create(document.querySelector('#commercial_law'), {
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'underline', 'link', '|',
                    'bulletedList', 'numberedList', '|',
                    'outdent', 'indent', '|',
                    'blockQuote', 'insertTable', '|',
                    'undo', 'redo'
                ]
            },
            language: 'ja',
            heading: {
                options: [
                    { model: 'paragraph', title: '段落', class: 'ck-heading_paragraph' },
                    { model: 'heading1', view: 'h1', title: '見出し1', class: 'ck-heading_heading1' },
                    { model: 'heading2', view: 'h2', title: '見出し2', class: 'ck-heading_heading2' },
                    { model: 'heading3', view: 'h3', title: '見出し3', class: 'ck-heading_heading3' },
                    { model: 'heading4', view: 'h4', title: '見出し4', class: 'ck-heading_heading4' }
                ]
            },
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
            }
        })
        .then(function(editor) {
            editorInstance = editor;
        })
        .catch(function(error) {
            console.error(error);
        });

    document.getElementById('commercialLawForm').addEventListener('submit', function() {
        if (editorInstance) {
            document.getElementById('commercial_law').value = editorInstance.getData();
        }
    });
});
</script>
@endsection
