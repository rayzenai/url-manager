{{-- Default view for displaying URL content --}}
@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>{{ $model->name ?? $model->title ?? 'Page' }}</h1>
        
        @if($model->description ?? false)
            <div class="description">
                {{ $model->description }}
            </div>
        @endif
        
        @if($model->content ?? false)
            <div class="content">
                {!! $model->content !!}
            </div>
        @endif
    </div>
@endsection