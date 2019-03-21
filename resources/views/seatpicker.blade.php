@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Seat Picker</h1>
    <div class="row tribune_selector">
        <div class="col-md-6 col-sm-6 col-xs-12">
            <div class="row">
                <h4>tribune:</h4>
            </div>
            <div class="row">
                <select id="tribunes" name="tribunes">
                    <?php
                        $tet = 1;
                        if(isset($tribunes)){
                            foreach($tribunes as $row){
                                echo '<option value="' . $row->id . '">' . $row->name . '</option>';
                            }
                        }
                    ?>
                </select>
            </div>
        </div>
        <div class="col-md-6 col-sm-6 col-xs-12">
            <div class="row">
                <h4>amount:</h4>
            </div>
            <div class="row">
                <select id="amount">
                    <?php
                        $max = 5;
                        for($i = 1; $i<=$max;$i++){
                            echo '<option value="' . $i. '">' . $i . '</option>';
                        }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="selectMove"></div>
    <input hidden type="seat" name="seatpicker_seat" id="seatpicker_seat" value="<?php if(isset($old_seat)) echo $old_seat ?>"/>
    <input hidden type="tribune" name="seatpicker_tribune" id="seatpicker_tribune" value="<?php if(isset($old_tribune)) echo $old_tribune ?>"/>
    <div class="modal"><!-- Place at bottom of page --></div>
</div>

@endsection