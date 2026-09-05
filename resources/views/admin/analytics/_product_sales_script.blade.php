<script>
    $(function () {
        var $block = $('#product_sales_block');
        $block.on('click', '.lma-pnavi_list a', function (e) {
            e.preventDefault();
            $.get($block.data('url'), { month: $(this).data('month') })
                .done(function (html) {
                    $block.html(html);
                });
        });
    });
</script>
