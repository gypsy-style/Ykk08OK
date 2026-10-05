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
            {{-- フォームと同じ組み方にして front.css のチェックボックス・ラベルの指定を効かせる --}}
            <dl class="lmf-form_box" style="margin: 10px 0;">
                <dt>権限</dt>
                <dd>
                    <ul class="form_ctrl">
                        @foreach (\App\Services\MerchantAccess::LABELS as $column => $label)
                            <li>
                                <label>
                                    <input type="checkbox" class="member-permission" data-user_id="{{ $member->user_id }}" data-permission="{{ $column }}" {{ $member->{$column} ? 'checked' : '' }} {{ $canEditPermissions ? '' : 'disabled' }}>
                                    {{ $label }}
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </dd>
            </dl>
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
