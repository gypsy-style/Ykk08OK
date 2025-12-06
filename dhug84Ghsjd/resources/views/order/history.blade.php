@extends('layouts.app')
@section('title', '注文履歴')
@section('content')
<div class="lmf-container">
	<div class="lmf-title_block tall">
		<h1 class="title">注文履歴</h1>
	</div>
	<main class="lmf-main_contents">
		<section class="lmf-content" id="order-history">
			<input type="hidden" name="access_token" id="access_token">
		</section>
	</main>
</div><!-- /.lmf-container -->
@endsection
@push('scripts')
<script>
	window.LIFF_ID = "{{ config('app.order_history_liff_id') }}";
</script>
@vite(['resources/js/liff.js'])
<script>
	// liff.js の初期化や設定をここに記述
	// LIFF initialization code
</script>
@endpush