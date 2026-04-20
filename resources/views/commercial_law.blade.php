@extends('layouts.app')
@section('title', '特定商取引法')
@section('content')
<style>
    .commercial-law-content h1 {
        font-size: 1.6em;
        font-weight: 700;
        line-height: 1.4;
        margin: 1em 0 0.6em;
    }
    .commercial-law-content h2 {
        font-size: 1.35em;
        font-weight: 700;
        line-height: 1.4;
        margin: 1em 0 0.5em;
    }
    .commercial-law-content h3 {
        font-size: 1.15em;
        font-weight: 700;
        line-height: 1.45;
        margin: 0.9em 0 0.4em;
    }
    .commercial-law-content h4 {
        font-size: 1.05em;
        font-weight: 700;
        line-height: 1.5;
        margin: 0.8em 0 0.4em;
    }
    .commercial-law-content p {
        font-size: 1em;
        line-height: 1.8;
        margin: 0.5em 0;
    }
    .commercial-law-content ul,
    .commercial-law-content ol {
        padding-left: 1.5em;
        margin: 0.5em 0;
    }
    .commercial-law-content ul,
    .commercial-law-content ul li {
        list-style: disc outside;
    }
    .commercial-law-content ol,
    .commercial-law-content ol li {
        list-style: decimal outside;
    }
    .commercial-law-content ul ul,
    .commercial-law-content ul ul li {
        list-style: circle outside;
    }
    .commercial-law-content ul ul ul,
    .commercial-law-content ul ul ul li {
        list-style: square outside;
    }
    .commercial-law-content ol ol,
    .commercial-law-content ol ol li {
        list-style: lower-alpha outside;
    }
    .commercial-law-content ol ol ol,
    .commercial-law-content ol ol ol li {
        list-style: lower-roman outside;
    }
    .commercial-law-content li {
        line-height: 1.8;
        margin: 0.2em 0;
    }
    .commercial-law-content blockquote {
        border-left: 4px solid #ccc;
        margin: 1em 0;
        padding: 0.5em 1em;
        color: #555;
    }
    .commercial-law-content a {
        color: #1e6bd6;
        text-decoration: underline;
    }
    .commercial-law-content table {
        border-collapse: collapse;
        margin: 1em 0;
        width: 100%;
    }
    .commercial-law-content table th,
    .commercial-law-content table td {
        border: 1px solid #ccc;
        padding: 0.5em 0.75em;
    }
    .commercial-law-content table th {
        background: #f5f5f5;
        font-weight: 700;
    }
</style>
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">特定商取引法</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">
            <div class="lmf-info_block lmf-white_block">
                <div class="commercial-law-content">
                    @if (trim(strip_tags($commercialLaw)) !== '')
                        {!! $commercialLaw !!}
                    @else
                        <p>特定商取引法に基づく表記は現在準備中です。</p>
                    @endif
                </div>
            </div>
        </section>
    </main>
</div>
@endsection
