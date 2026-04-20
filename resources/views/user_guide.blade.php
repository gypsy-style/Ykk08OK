@extends('layouts.app')
@section('title', 'ご利用ガイド')
@section('content')
<style>
    .user-guide-content h1 {
        font-size: 1.6em;
        font-weight: 700;
        line-height: 1.4;
        margin: 1em 0 0.6em;
    }
    .user-guide-content h2 {
        font-size: 1.35em;
        font-weight: 700;
        line-height: 1.4;
        margin: 1em 0 0.5em;
    }
    .user-guide-content h3 {
        font-size: 1.15em;
        font-weight: 700;
        line-height: 1.45;
        margin: 0.9em 0 0.4em;
    }
    .user-guide-content h4 {
        font-size: 1.05em;
        font-weight: 700;
        line-height: 1.5;
        margin: 0.8em 0 0.4em;
    }
    .user-guide-content p {
        font-size: 1em;
        line-height: 1.8;
        margin: 0.5em 0;
    }
    .user-guide-content ul,
    .user-guide-content ol {
        padding-left: 1.5em;
        margin: 0.5em 0;
    }
    .user-guide-content ul,
    .user-guide-content ul li {
        list-style: disc outside;
    }
    .user-guide-content ol,
    .user-guide-content ol li {
        list-style: decimal outside;
    }
    .user-guide-content ul ul,
    .user-guide-content ul ul li {
        list-style: circle outside;
    }
    .user-guide-content ul ul ul,
    .user-guide-content ul ul ul li {
        list-style: square outside;
    }
    .user-guide-content ol ol,
    .user-guide-content ol ol li {
        list-style: lower-alpha outside;
    }
    .user-guide-content ol ol ol,
    .user-guide-content ol ol ol li {
        list-style: lower-roman outside;
    }
    .user-guide-content li {
        line-height: 1.8;
        margin: 0.2em 0;
    }
    .user-guide-content blockquote {
        border-left: 4px solid #ccc;
        margin: 1em 0;
        padding: 0.5em 1em;
        color: #555;
    }
    .user-guide-content a {
        color: #1e6bd6;
        text-decoration: underline;
    }
    .user-guide-content table {
        border-collapse: collapse;
        margin: 1em 0;
        width: 100%;
    }
    .user-guide-content table th,
    .user-guide-content table td {
        border: 1px solid #ccc;
        padding: 0.5em 0.75em;
    }
    .user-guide-content table th {
        background: #f5f5f5;
        font-weight: 700;
    }
</style>
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">ご利用ガイド</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">
            <div class="lmf-info_block lmf-white_block">
                <div class="user-guide-content">
                    @if (trim(strip_tags($userGuide)) !== '')
                        {!! $userGuide !!}
                    @else
                        <p>ご利用ガイドは現在準備中です。</p>
                    @endif
                </div>
            </div>
        </section>
    </main>
</div>
@endsection
