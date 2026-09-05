<script>
    $(function () {
        // 差し替え後も効くよう、ブロック自身にイベントを持たせる
        $('.analytics_async').on('click', '.lma-pnavi_list a', function (e) {
            e.preventDefault();
            var $block = $(this).closest('.analytics_async');
            $.get($block.data('url'), { month: $(this).data('month') })
                .done(function (html) {
                    $block.html(html);
                });
        });
    });
</script>
