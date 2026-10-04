<!-- オーナー -->
 <div class="lmf-staff_block lmf-white_block">
    <dl class="lmf-info_list">
        <dt>名前</dt>
        <dd class="name">{{ optional($owner)->name }}</dd>
        <dt>LINE ID</dt>
        <dd class="id">{{ optional($owner)->line_id }}</dd>
    </dl>
</div>
<!-- 登録スタッフ -->
@if ($members->isNotEmpty())
    @foreach ($members as $member)
        <div class="lmf-staff_block lmf-white_block">
            <dl class="lmf-info_list">
                <dt>名前</dt>
                <dd class="name">{{ $member->user->name }}</dd>
                <dt>LINE ID</dt>
                <dd class="id">{{ $member->user->line_id }}</dd>
            </dl>
            {{-- 権限はオーナーだけが変えられる。スタッフ管理の権限を持つスタッフには見るだけで出す --}}
            <div style="margin: 10px 0; font-size: 14px;">
                <p style="margin: 0 0 6px; font-weight: bold;">権限</p>
                @foreach (\App\Services\MerchantAccess::LABELS as $column => $label)
                    <label style="display: block; margin: 4px 0;">
                        <input type="checkbox" class="member-permission" data-user_id="{{ $member->user_id }}" data-permission="{{ $column }}" {{ $member->{$column} ? 'checked' : '' }} {{ $canEditPermissions ? '' : 'disabled' }}>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            {{-- 自分自身は削除できない --}}
            @if ((int) $member->user_id !== (int) $viewerUserId)
            <p class="lmf-btn_box btn_pk btn_min">
                <button type="button" data-href="#modal_delete" class="modal_open modal_delete" data-user_id="{{ $member->user->id }}" data-delete_staff_name="{{ $member->user->name }}">削除する</button>
            </p>
            @endif
        </div>
    @endforeach
@else
    <p class="lmf-no-staff">スタッフは登録されていません</p>
@endif
<p class="lmf-btn_box"><button type="button" data-href="#modal_add" class="modal_open" data-merchant_id="">スタッフを追加する</button></p>
<input type="hidden" name="merchant_id" id="merchant_id" value="{{ $merchant_id }}">
<input type="hidden" name="invite_token" id="invite_token" value="{{ $inviteToken }}">
