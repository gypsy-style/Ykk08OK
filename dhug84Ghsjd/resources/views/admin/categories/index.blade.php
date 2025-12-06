<!-- resources/views/categories/index.blade.php -->
@extends('admin.layouts.app')

@section('content')
    <h1>Categories</h1>
    <a href="{{ route('admin.categories.create') }}">Add New Category</a>

    @if(session('success'))
        <p>{{ session('success') }}</p>
    @endif

    <table class="table">
        <thead>
            <tr>

                <th>商品名</th>
                <th>アクション</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $category)
                <tr>

                    <td>{{ $category->name }}</td>
                    <td>
                    <a href="{{ route('admin.categories.show', $category->id) }}" class="btn btn-info">詳細</a>
                        <a href="{{ route('admin.categories.edit', $category->id) }}" class="btn btn-warning">編集</a>
                        <form action="{{ route('admin.categories.destroy', $category->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('本当に削除しますか？')">削除</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection