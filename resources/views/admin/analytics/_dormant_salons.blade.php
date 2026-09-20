<div class="analytics_dormant">
    @foreach ($dormantSalons as $group)
    <div class="analytics_dormant_col">
        <div class="records_caption">
            <h2 class="lma-title_bar sky"><em class="label">{{ $group['label'] }}</em></h2>
        </div>
        <div class="records_table">
            <table class="lma-detail_tbl analytics_tbl">
                <tbody>
                    <tr>
                        <th>サロン名</th>
                    </tr>
                    @forelse ($group['salons'] as $salon)
                    <tr>
                        <th><a href="{{ route('admin.analytics.salon', ['merchant' => $salon['id'], 'month' => $month]) }}">{{ $salon['name'] }}</a></th>
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
