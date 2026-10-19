<div class="analytics_dormant">
    @foreach ($orderedSalons as $group)
    <div class="analytics_dormant_col">
        <div class="records_caption">
            <h2 class="lma-title_bar sky"><em class="label">{{ $group['label'] }}</em></h2>
        </div>
        <p class="analytics_rate">
            購入率 <strong>{{ $group['rate'] === null ? '-' : number_format($group['rate'], 1) . '%' }}</strong>
            （{{ number_format($group['orderedCount']) }} / {{ number_format($group['activeCount']) }} サロン）
        </p>
        <div class="records_table">
            <table class="lma-detail_tbl analytics_tbl">
                <tbody>
                    <tr>
                        <th>サロン名</th>
                    </tr>
                    @forelse ($group['salons'] as $salon)
                    <tr>
                        <th><a href="{{ route('admin.analytics.salon', ['merchant' => $salon['id'], 'month' => $group['month']]) }}">{{ $salon['name'] }}</a>@if ($salon['deleted'])（削除済み）@endif<span class="analytics_sub">{{ number_format($salon['shipments']) }}件</span></th>
                    </tr>
                    @empty
                    <tr>
                        <th>該当なし</th>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>
