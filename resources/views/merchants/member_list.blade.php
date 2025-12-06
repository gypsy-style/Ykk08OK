@extends('layouts.app')
@section('title', '登録スタッフ')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block">
        <h1 class="title">登録スタッフ</h1>
    </div>

    <main class="lmf-main_contents">
        <section class="lmf-content">
            <div id="staff-list">

            </div>
            <!-- <div class="lmf-staff_block lmf-white_block">
				<dl class="lmf-info_list">
					<dt>名前</dt>
					<dd class="name">YUKA</dd>
					<dt>LINE ID</dt>
					<dd class="id">U45336016f2898df141870e212bf3f7f6</dd>
				</dl>
				<p class="lmf-btn_box btn_pk btn_min"><button type="button" data-href="#modal_delete" class="modal_open">削除する</button></p>
			</div>
			<div class="lmf-staff_block lmf-white_block">
				<dl class="lmf-info_list">
					<dt>名前</dt>
					<dd class="name">YUKA</dd>
					<dt>LINE ID</dt>
					<dd class="id">U45336016f2898df141870e212bf3f7f6</dd>
				</dl>
				<p class="lmf-btn_box btn_pk btn_min"><button type="button" data-href="#modal_delete" class="modal_open">削除する</button></p>
			</div> -->

            <input type="hidden" name="access_token" id="access_token">

        </section>
    </main>
</div><!-- /.lmf-container -->

<div class="lmf-modal_wrap">
    <div class="lmf-modal_layer"></div>
    <div class="lmf-modal_content staff" id="modal_add">
        <div class="modal_close_btn"><button>&times;</button></div>
        <div class="inner">
            <div class="text_block">
                <p> スタッフを追加するには下記URLをスタッフに送信し追加するスタッフ自身で登録をしてください。</p>
                <div style="width:100%;text-align:center;margin:20px auto;">
                    <canvas id="qrcode" style="margin: 0 auto;"></canvas>
                </div>
            </div>
            <p class="lmf-btn_box btn_small">
                <a href="#" onclick="copyToClipboard()">URLをコピーする</a>
            </p>
        </div>
    </div>
    <div class="lmf-modal_content staff" id="modal_delete">
        <div class="modal_close_btn"><button>&times;</button></div>
        <div class="inner">
            <div class="text_block">
                <p>名前：<span id="delete_staff_name"></span><br>
                    のスタッフ情報を削除します</p>
                <p>※削除すると戻すことはできません。ご注意ください。</p>
            </div>
            <input type="hidden" name="delete_staff_user_id" value="">
            <p class="lmf-btn_box btn_small"><a href="#" id="member-delete-btn">スタッフを削除する</a></p>
        </div>
    </div>
</div><!-- /.modal_wrap -->
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
<script>
    window.LIFF_ID_REGISTER = "{{ config('app.register_liff_id') }}";
    window.LIFF_ID = "{{ config('app.member_list_liff_id') }}";
</script>
@vite(['resources/js/liff_member_list.js'])
<script type="text/javascript">
    function generateQRCode(merchantId) {
        const liffIdAddMember = window.LIFF_ID_REGISTER; // これが QR 用の LIFF ID
        const url = `https://liff.line.me/${liffIdAddMember}?merchant_id=${merchantId}`;

        const qrContainer = document.getElementById('qrcode');
        if (!qrContainer) {
            console.error('QRコードの表示先 (#qrcode) が見つかりません');
            return;
        }

        const qr = new QRious({
            element: qrContainer,
            value: url,
            size: 200
        });

        console.log('QRコード生成完了:', url);
    }
    $(function() {
        $(document).on('click', '.modal_open', function() {
            id = $($(this).data('href'));
            $(id).addClass("active");
            $(id).parent().addClass("active");
            // QRコード生成（merchantIdを保持しておく必要あり）
            const merchantId = $('#merchant_id').val();
            if (merchantId) {
                generateQRCode(merchantId);
            } else {
                console.error('merchant_idが取得できませんでした');
            }
        });
        $(document).on('click', '.modal_close_btn', function() {
            $(this).parent().removeClass("active");
            $(this).parent().parent().removeClass("active");
        });
        $(document).on('click', '.lmf-modal_layer', function() {
            $(this).parent().removeClass("active");
            $(this).parent().find(".active").removeClass("active");
        });
        $(document).on('click', '.modal_delete', function() {
            let userID = $(this).attr('data-user_id');
            if (!userID) {
                alert('LINE IDが取得できませんでした');
                return false;
            }
            let deleteStaffName = $(this).attr('data-delete_staff_name');
            console.log(deleteStaffName)
            $('#delete_staff_name').html(deleteStaffName);
            $('input[name=delete_staff_user_id]').val(userID);

            $('#modal_delete').addClass("active");
            $('#modal_delete').parent().addClass("active");
            // $(id).parent().addClass("active");
        });

        $("#member-delete-btn").on("click", function(e) {
            e.preventDefault();
            let deleteUserID = $('input[name=delete_staff_user_id]').val();

            if (!deleteUserID) {
                alert("削除するユーザーのIDが取得できませんでした");
                return;
            }

            if (!confirm("本当に削除しますか？この操作は元に戻せません。")) {
                return;
            }

            $.ajax({
                type: "DELETE",
                url: `{{ route('merchant.member.destroy', ':id') }}`.replace(':id', deleteUserID),
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"), // CSRF対策
                },
                success: function(response) {
                    alert(response.message);
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert("削除に失敗しました: " + xhr.responseJSON.error);
                }
            });
        });

        // $('#member-delete-btn').on('click', function() {
        //     let deleteUserID = $('input[name=delete_staff_user_id]').val();
        //     let post = {
        //         user_id: deleteUserID
        //     };

        //     $.ajax({
        //         type: "GET",
        //         url: "/wp-json/wp/v2/delete_store_member",
        //         dataType: "text",
        //         data: post
        //     }).done(function(data) {
        //         console.log(data);
        //         alert('スタッフを削除しました');
        //         liff.closeWindow();


        //     }).fail(function(XMLHttpRequest, textStatus, errorThrown) {
        //         alert('削除に失敗しました');
        //         liff.closeWindow();

        //     });

        // })
    });
</script>
<script>
    // スタッフ紹介URLをコピー
    function copyToClipboard() {
        const merchantId = document.getElementById("merchant_id").value;
        const liffIdAddMember = "{{ config('app.add_member_liff_id') }}"; // Replace this with your actual PHP variable
        const url = `https://liff.line.me/${liffIdAddMember}?merchant_id=${merchantId}`;

        // Create a temporary input element to hold the URL
        const tempInput = document.createElement("input");
        document.body.appendChild(tempInput);
        tempInput.value = url;

        // Select the text in the input element and copy it to the clipboard
        tempInput.select();
        tempInput.setSelectionRange(0, 99999); // For mobile devices
        document.execCommand("copy");

        // Remove the temporary input element
        document.body.removeChild(tempInput);

        // Show an alert to notify the user
        alert("クリップボードにコピーしました");
    }
    document.addEventListener('DOMContentLoaded', function() {
        const qr = new QRious({
            element: document.getElementById('qrcode'),
            value: 'https://example.com',
            size: 200
        });
    });
</script>

@endpush