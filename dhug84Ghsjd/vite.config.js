import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/admin.css', 
                'resources/css/app.css', 
                'resources/css/default.css', 
                'resources/css/front.css', 
                'resources/css/line_form.css', 
                'resources/css/splide.min.css',
                'resources/js/bootstrap.js',
                'resources/js/app.js',
                'resources/js/common.js',
                'resources/js/liff.js',
                'resources/js/liff_member_list.js',
                'resources/js/liff_order_register.js',
                'resources/js/liff_merchant.js',
                'resources/js/liff_information.js',
                'resources/js/liff_merchant_add_member.js',
                'resources/js/line_form_order.js',
                'resources/js/splide.min.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        outDir: 'public/build', // 出力先ディレクトリ
        assetsDir: 'assets',   // アセットファイルのディレクトリ
        manifest: true,
        assetsInclude: ['**/*.jpg', '**/*.png', '**/*.jpeg', '**/*.gif', '**/*.svg']
    },
    base: '/dhug84Ghsjd/public/build/', // 環境変数を使用
});
