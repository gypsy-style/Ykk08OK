<ul class="lma-user_list store">
    @forelse($merchants as $merchant)
    <li>
        <div class="lma-user_box {{ $merchant->status == 2 ? 'tbd' : '' }}">
            <div class="user_info">
                @if($merchant->name_kana)
                <p class="sub" style="font-size: 0.8em; color: #888;">{{ $merchant->name_kana }}</p>
                @endif
                <h3 class="name">{{ $merchant->name }}</h3>
                <p class="sub" style="font-size: 0.8em; color: #888;">{{ $merchant->agency->name ?? '代理店未設定' }}　会員ランク{{ $merchant->member_rank ?? '-' }}</p>
                @if($merchant->bank_account_name)
                <p class="sub" style="font-size: 0.8em; color: #888; white-space: pre-line;">振込み口座名: {{ $merchant->bank_account_name }}</p>
                @endif
            </div>
            <div class="lma-btn_box btn_list">
                <a href="{{ route('admin.merchants.edit', $merchant->id) }}" class="btn btn-primary btn-sm">編集</a>
                <form action="{{ route('admin.merchants.destroy', $merchant->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="gy">削除</button>
                </form>
            </div>

        </div>
    </li>
    @empty
    <li>
        <div class="lma-user_box">
            <div class="user_info">
                <h3 class="name">該当する加盟店がありません</h3>
            </div>
        </div>
    </li>
    @endforelse
</ul>
