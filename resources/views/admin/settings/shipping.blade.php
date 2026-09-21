@extends('admin.layouts.app')

@section('title', '管理画面 [送料設定]')

@push('head')
<style>
    .shipping-fields {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .shipping-threshold {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .shipping-threshold .label {
        font-weight: bold;
    }
    .shipping-fields input[type="text"] {
        width: 110px;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        text-align: right;
        box-sizing: border-box;
    }
    .shipping-table {
        border-collapse: collapse;
    }
    .shipping-table th {
        padding: 0 12px 8px 0;
        text-align: left;
        font-weight: bold;
        white-space: nowrap;
    }
    .shipping-table td {
        padding: 3px 12px 3px 0;
        vertical-align: top;
        white-space: nowrap;
    }
    .shipping-table td.pref {
        padding-top: 10px;
        font-weight: bold;
    }
    .shipping-unit {
        font-size: 12px;
        color: #666;
    }
    .shipping-error {
        margin: 4px 0 0;
        color: red;
        font-size: 12px;
        white-space: normal;
    }
    @media only screen and (max-width: 678px) {
        .shipping-fields input[type="text"] {
            width: 70px;
        }
        .shipping-table th,
        .shipping-table td {
            padding-right: 6px;
        }
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

        <form action="{{ route('admin.settings.update_shipping') }}" method="POST">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'shipping'])
                </dt>
                <dd>
                    <div class="shipping-fields">
                        <div>
                            <div class="shipping-threshold">
                                <span class="label">送料設定金額</span>
                                <input type="text" name="threshold" inputmode="numeric" value="{{ old('threshold', $threshold) }}">
                                <span class="shipping-unit">円（税込）</span>
                            </div>
                            @error('threshold')
                                <p class="shipping-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <table class="shipping-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th colspan="2">送料設定</th>
                                    <th colspan="2">送料設定金額を超えた場合</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($prefectures as $pref)
                                    <tr>
                                        <td class="pref">{{ $pref }}</td>
                                        <td>
                                            <input type="text" name="fees[{{ $pref }}][under]" inputmode="numeric" value="{{ old('fees.' . $pref . '.under', $fees[$pref]['under']) }}">
                                            @error('fees.' . $pref . '.under')
                                                <p class="shipping-error">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="pref"><span class="shipping-unit">円（税込）</span></td>
                                        <td>
                                            <input type="text" name="fees[{{ $pref }}][over]" inputmode="numeric" value="{{ old('fees.' . $pref . '.over', $fees[$pref]['over']) }}">
                                            @error('fees.' . $pref . '.over')
                                                <p class="shipping-error">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="pref"><span class="shipping-unit">円（税込）</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </dd>
            </dl>

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>
@endsection
