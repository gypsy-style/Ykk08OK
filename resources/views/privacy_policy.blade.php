@extends('layouts.app')
@section('title', 'プライバシーポリシー')
@section('content')
<style>
    .privacy-policy-content h1 {
        font-size: 1.6em;
        font-weight: 700;
        line-height: 1.4;
        margin: 1em 0 0.6em;
    }
    .privacy-policy-content h2 {
        font-size: 1.35em;
        font-weight: 700;
        line-height: 1.4;
        margin: 1em 0 0.5em;
    }
    .privacy-policy-content h3 {
        font-size: 1.15em;
        font-weight: 700;
        line-height: 1.45;
        margin: 0.9em 0 0.4em;
    }
    .privacy-policy-content h4 {
        font-size: 1.05em;
        font-weight: 700;
        line-height: 1.5;
        margin: 0.8em 0 0.4em;
    }
    .privacy-policy-content p {
        font-size: 1em;
        line-height: 1.8;
        margin: 0.5em 0;
    }
    .privacy-policy-content ul,
    .privacy-policy-content ol {
        padding-left: 1.5em;
        margin: 0.5em 0;
    }
    .privacy-policy-content ul,
    .privacy-policy-content ul li {
        list-style: disc outside;
    }
    .privacy-policy-content ol,
    .privacy-policy-content ol li {
        list-style: decimal outside;
    }
    .privacy-policy-content ul ul,
    .privacy-policy-content ul ul li {
        list-style: circle outside;
    }
    .privacy-policy-content ul ul ul,
    .privacy-policy-content ul ul ul li {
        list-style: square outside;
    }
    .privacy-policy-content ol ol,
    .privacy-policy-content ol ol li {
        list-style: lower-alpha outside;
    }
    .privacy-policy-content ol ol ol,
    .privacy-policy-content ol ol ol li {
        list-style: lower-roman outside;
    }
    .privacy-policy-content li {
        line-height: 1.8;
        margin: 0.2em 0;
    }
    .privacy-policy-content blockquote {
        border-left: 4px solid #ccc;
        margin: 1em 0;
        padding: 0.5em 1em;
        color: #555;
    }
    .privacy-policy-content a {
        color: #1e6bd6;
        text-decoration: underline;
    }
    .privacy-policy-content table {
        border-collapse: collapse;
        margin: 1em 0;
        width: 100%;
    }
    .privacy-policy-content table th,
    .privacy-policy-content table td {
        border: 1px solid #ccc;
        padding: 0.5em 0.75em;
    }
    .privacy-policy-content table th {
        background: #f5f5f5;
        font-weight: 700;
    }
</style>
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">プライバシーポリシー</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">
            <div class="lmf-info_block lmf-white_block">
                <div class="privacy-policy-content">
                    @if (trim(strip_tags($privacyPolicy)) !== '')
                        {!! $privacyPolicy !!}
                    @else
                        <p>プライバシーポリシーは現在準備中です。</p>
                    @endif
                </div>
            </div>
        </section>
    </main>
</div>
@endsection
