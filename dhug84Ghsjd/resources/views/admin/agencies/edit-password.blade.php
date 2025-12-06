@extends('admin.layouts.app')
@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>{{ $agency->name }} のパスワード変更</h2>
        </div>
    </div>
    @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
    <div class="lma-content_block store_edit">
        <form action="{{ route('admin.agencies.update-password', $agency) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <dl class="lma-form_box">
                <dt><label for="password">新しいパスワード</label></dt>
                <dd><input type="text" name="password" id="password" class="form-control"></dd>
            </dl>

            <p class="lma-btn_box">
                <input type="hidden" class="form-control" id="user_id" name="user_id" value="{{ old('user_id') }}" required>
                <button type="submit" class="btn btn-primary">更新</button>
            </p>
        </form>
    </div>
</section>

@endsection