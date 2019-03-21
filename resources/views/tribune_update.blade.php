@extends('layouts.app')

@section('content')
<div class="container">
    <h1> Tribunes </h1>
    <div class="table">
        @if(count($tribunes)>0)
            @foreach ($tribunes as $tribune)
                <div class="row">
                    <div class="col-md-10">
                        {{{$tribune->name}}}
                    </div>
                    <div class="col-md-2">
                        <a href="#" onclick="removeRow({{{$tribune->id}}})" class="delete" role="button"> &#10006; </a>
                        <form action="/tribune/change" method="POST" id="change{{{$tribune->id}}}" class="change">
                            {{ csrf_field() }}
                            <input type="hidden" id="identifier" name="identifier" value="{{{$tribune->id}}}">
                            <a href="#" onclick="$('#change{{{$tribune->id}}}').submit();"> &#9998;</a>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
    
    <div class="modal"><!-- Place at bottom of page --></div>
</div>

@endsection