@extends('admin.layouts.app')

@section('title', '管理画面 [会社情報]')

@push('head')
<style>
    .company-info-fields {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .company-info-fields .row {
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }
    .company-info-fields .label {
        width: 90px;
        padding-top: 8px;
        font-weight: bold;
    }
    .company-info-fields .field {
        flex: 1;
    }
    .company-info-fields input[type="text"],
    .company-info-fields textarea {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .company-info-fields textarea {
        min-height: 110px;
        resize: vertical;
    }
    .company-info-fields .seal-preview {
        margin-top: 8px;
    }
    .company-info-fields .seal-preview img {
        max-width: 120px;
        height: auto;
        border: 1px solid #eee;
        padding: 4px;
        background: #fff;
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

        <form action="{{ route('admin.settings.update_company_info') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'company_info'])
                </dt>
                <dd>
                    <div class="company-info-fields">
                        <div class="row">
                            <div class="label">会社名</div>
                            <div class="field">
                                <input type="text" name="company_name" value="{{ old('company_name', $companyName) }}">
                                @if($errors->has('company_name'))
                                    <p style="color:red; margin-top:4px;">{{ $errors->first('company_name') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <div class="label">会社詳細</div>
                            <div class="field">
                                <textarea name="company_detail">{{ old('company_detail', $companyDetail) }}</textarea>
                                @if($errors->has('company_detail'))
                                    <p style="color:red; margin-top:4px;">{{ $errors->first('company_detail') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <div class="label">印章</div>
                            <div class="field">
                                <input type="file" name="company_seal" accept="image/*">
                                @if($companySeal)
                                    <div class="seal-preview">
                                        <img src="{{ asset('storage/' . $companySeal) }}" alt="印章">
                                    </div>
                                @endif
                                @if($errors->has('company_seal'))
                                    <p style="color:red; margin-top:4px;">{{ $errors->first('company_seal') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <div class="label">銀行情報</div>
                            <div class="field">
                                <textarea name="company_bank_info">{{ old('company_bank_info', $companyBankInfo) }}</textarea>
                                @if($errors->has('company_bank_info'))
                                    <p style="color:red; margin-top:4px;">{{ $errors->first('company_bank_info') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <div class="label">お支払い<br>について</div>
                            <div class="field">
                                <textarea name="company_payment_note" placeholder="・支払期限：当月15日まで&#10;・15日が土日祝日の場合は、直前の営業日まで&#10;・振込手数料はご負担頂きますようお願い致します。">{{ old('company_payment_note', $companyPaymentNote) }}</textarea>
                                <p style="margin:6px 0 0; font-size:12px; color:#666;">請求書の右下に表示されます。空欄の場合は非表示になります。</p>
                                @if($errors->has('company_payment_note'))
                                    <p style="color:red; margin-top:4px;">{{ $errors->first('company_payment_note') }}</p>
                                @endif
                            </div>
                        </div>
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
