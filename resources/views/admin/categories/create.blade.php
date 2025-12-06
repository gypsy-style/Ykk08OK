<!-- resources/views/categories/create.blade.php -->
@extends('admin.layouts.app')

@section('content')
    <h1>カテゴリー</h1>

    <form action="{{ route('admin.categories.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="name">カテゴリー名</label>
            <input type="text" name="name" id="name" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">登録</button>
    </form>
@endsection