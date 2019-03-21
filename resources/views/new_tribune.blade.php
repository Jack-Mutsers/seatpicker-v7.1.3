@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <h1> new tribune </h1>
    </div>
    <div class="row">
        <a href="/download/example" id="download" role="button"><button>download example</button></a>
    </div>
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
    <div class="row">
        <form action="/tribune/save" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="_token" id="csrf-token" value="{{ Session::token() }}" />
            <div class="row">
                <div class="col-md-3">active:</div>
                <div class="col-md-9"><input type="checkbox" name="active" id="active"></div>
            </div>
            <div class="row">
                <div class="col-md-3">tribune:</div>
                <div class="col-md-9"><input type="file" name="tribune" id="tribune"></div>
            </div>
            <div class="row">
                <div class="col-md-3">name:</div>
                <div class="col-md-9"><input type="text" name="name" id="name"></div>
            </div>
            <div class="row">
                <div class="col-md-12" style="color: red; text-align: center;">
                    the option below is only for testing the seatpicker algorithm. <br /> 
                    might cause probems if the tribune file doesn't have enough seats
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">reservations:</div>
                <div class="col-md-9"><input type="checkbox" name="reservations" id="reservations"></div>
            </div>
            <br/>
            <div class="row">
                <input type="submit" value="Submit">
            </div>
        </form> 
    </div>
</div>

@endsection