<!-- オーナー -->
 <div class="lmf-staff_block lmf-white_block">
    <dl class="lmf-info_list">
        <dt>名前</dt>
        <dd class="name">{{ $user->name }}</dd>
        <dt>LINE ID</dt>
        <dd class="id">{{ $user->line_id }}</dd>
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
            <p class="lmf-btn_box btn_pk btn_min">
                <button type="button" data-href="#modal_delete" class="modal_open modal_delete" data-user_id="{{ $member->user->id }}" data-delete_staff_name="{{ $member->user->name }}">削除する</button>
            </p>
        </div>
    @endforeach
@else
    <p class="lmf-no-staff">スタッフは登録されていません</p>
@endif
<p class="lmf-btn_box"><button type="button" data-href="#modal_add" class="modal_open" data-merchant_id="">スタッフを追加する</button></p>
<input type="hidden" name="merchant_id" id="merchant_id" value="{{ $merchant_id }}">