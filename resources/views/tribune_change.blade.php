@extends('layouts.app')

@section('content')
<div class="container">
    <h1> Change Tribunes </h1>
    <br /><br />
    @if (session('status'))
        <div class="alert alert-danger">
            <ul>
                @foreach (session('status') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="/tribune/modify" method="POST" enctype="multipart/form-data">
        {{ csrf_field() }}
        @foreach ($tribune_data as $item)
            <input readonly type="hidden" name="id" id="id" value="{{$item->id}}">
            <input readonly type="hidden" name="oldname" id="oldname" value="{{$item->name}}">
            <input readonly type="hidden" name="oldtribune" id="oldtribune" value="{{json_decode($item->tribune)->name}}">
            <div class="row">
                <div class="col-md-2">
                    active:
                </div>
                <div class="col-md-10">
                    <input type="checkbox" name="active" id="active" {{$item->active==1? 'checked':''}}>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    tribune file:
                </div>
                <div class="col-md-10">
                    <input type="file" name="tribune" id="tribune" style="display: none;">
                    <input type="button" value="Browse..." onclick="document.getElementById('tribune').click();" /> 
                    <span id="label">{{json_decode($item->tribune)->name}}</span>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    name:
                </div>
                <div class="col-md-10">
                    <input type="text" name="name" id="name" value="{{$item->name}}">
                </div>
            </div>
            <div class="row">
                <input type="submit" value="update">
            </div>
        @endforeach
    </form>
</div>

@endsection