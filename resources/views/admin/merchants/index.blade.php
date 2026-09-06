@extends('admin.layouts.app')

@section('title', '管理画面 [加盟店一覧]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>加盟店一覧</h2>
        </div>
    </div>

    <!-- フィルタリングフォーム -->
    <div class="lma-content_block log">
        <form method="GET" action="{{ route('admin.merchants.index') }}" class="filter-form">
            <div class="lma-filter">
                <div class="lma-filter__item">
                    <label for="keyword">ふりがな:</label>
                    <input type="text" name="keyword" id="keyword" value="{{ request('keyword') }}" placeholder="ひらがなで入力" autocomplete="off">
                </div>
                <div class="lma-filter__item">
                    <label for="sort">並び順:</label>
                    <select name="sort" id="sort">
                        @foreach($sorts as $value => $label)
                        <option value="{{ $value }}" {{ $sort === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lma-filter__item">
                    <label for="agency_id">代理店:</label>
                    <select name="agency_id" id="agency_id">
                        <option value="">すべて</option>
                        @foreach($agencies as $agency)
                        <option value="{{ $agency->id }}" {{ request('agency_id') == $agency->id ? 'selected' : '' }}>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lma-filter__item">
                    <label for="status">ステータス:</label>
                    <select name="status" id="status">
                        <option value="">すべて</option>
                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>有効</option>
                        <option value="2" {{ request('status') == '2' ? 'selected' : '' }}>無効</option>
                    </select>
                </div>
                <div class="lma-filter__item">
                    <label for="member_rank">会員ランク:</label>
                    <select name="member_rank" id="member_rank">
                        <option value="">すべて</option>
                        @foreach([1, 2, 3] as $rank)
                        <option value="{{ $rank }}" {{ request('member_rank') == $rank ? 'selected' : '' }}>{{ $rank }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lma-filter__item">
                    <button type="submit" class="btn btn-primary">フィルター</button>
                    <a href="{{ route('admin.merchants.index') }}" class="btn btn-secondary">リセット</a>
                </div>
            </div>
        </form>
    </div>

    <div class="lma-content_block staff nobg" id="merchant_list" data-url="{{ route('admin.merchants.list') }}">
        @include('admin.merchants._list')
    </div>
</section>
@endsection

@push('head')
<script>
    $(function () {
        var $form = $('.filter-form');
        var $list = $('#merchant_list');
        var $keyword = $('#keyword');
        var timer = null;
        var composing = false;

        // カタカナで打たれても拾えるようひらがなに寄せ、それ以外の文字は落とす
        function toHiragana(value) {
            return value
                .replace(/[ァ-ヶ]/g, function (c) {
                    return String.fromCharCode(c.charCodeAt(0) - 0x60);
                })
                .replace(/[^ぁ-ゖー]/g, '');
        }

        function reload() {
            $.get($list.data('url'), $form.serialize()).done(function (html) {
                $list.html(html);
            });
        }

        // IME 変換中は値を書き換えない。書き換えると入力中の文字が消える
        $keyword.on('compositionstart', function () {
            composing = true;
        }).on('compositionend', function () {
            composing = false;
            $(this).trigger('input');
        }).on('input', function () {
            if (composing) {
                return;
            }
            var cleaned = toHiragana(this.value);
            if (cleaned !== this.value) {
                this.value = cleaned;
            }
            clearTimeout(timer);
            timer = setTimeout(reload, 300);
        });

        $form.find('select').on('change', reload);

        $form.on('submit', function (e) {
            e.preventDefault();
            reload();
        });
    });
</script>
@endpush
