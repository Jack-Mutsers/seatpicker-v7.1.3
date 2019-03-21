//*
$body = $("body");

$(document).on({
    ajaxStart: function() { $body.addClass("loading");    },
     ajaxStop: function() { $body.removeClass("loading"); }    
});

$(document).ready(function () {

    // check if select box tribunes changes
    $('#tribunes').change(function (e) {
        // get value of the select box
        var id = $(e.target).val();
        // request data of the tribune
        if(id != "" && id != undefined && id != null){
            $.ajax({
                url: '/reservations/gettribune',
                type: "post",
                data: {
                    tribune: id
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response){ // What to do if we succeed
                    var seatData = response;

                    // send data to loadGrid to build the seatpicker
                    loadGrid(seatData, id);

                    // modify chosen seats to show up
                    fix_temp_reserved(id);
                },
                error: function(data){
                    console.log('Error:', data);
                }
            });
        }
    });

    $('#tribunes').trigger('change');

    function loadGrid(Data, id) {
        
        seatData = JSON.parse(Data);

        $('.selectMove').seatLayout({
            data: seatData,
            showActionButtons:true,
            classes : {
                doneBtn : '',
                cancelBtn : '',
                row:'',
                area:'',
                seat:''
            },
            callOnSeatRender: function (Obj) {

                // modify seat object if require and return it;
                return Obj;
            },
            callOnSeatSelect: function (_event, _data, _selected, _element) {

                // remove the current-selected class from all seats that have been set by the library
                $(".current-selected").removeClass("current-selected");

                // count the childeren of the 1st ul element with the class seat-area-row
                var maximum = $( "ul.seat-area-row" ).eq( 0 ).children().length;
                
                // get all seats to check and modify
                var container = $("ul.seat-area-row").children();
                
                // get the amount of seats requested
                var nuberOfSeat = $('#amount option:selected').val();
                
                // get the previously selected seats
                var oldseats = $('#seatpicker_seat').val();
                
                // get the previously selected tribune
                var oldtribune = $('#seatpicker_tribune').val();

                // unpack and store all seats with their position in an array
                var seats = [];
                for (var i=0; i< container.length;i++) {
                    var seat_defenition = container[i].dataset.seatdefination;
                    if(seat_defenition !== undefined){
                        var _json = JSON.parse(seat_defenition);
                        seats.push({'seat': container[i], 'row': _json['PhyRowId'], 'col': _json['GridSeatNum']});
                    }
                }

                // send clicked seat to the controller to check and possibly change its position, before setting the seats that came back as selected.
                $.ajax({
                    url: '/reservations/checkseats',
                    type: "post",
                    dataType:"json",
                    data: {
                        id: id,
                        data: _data,
                        max: maximum,
                        amount: nuberOfSeat,
                        oldseats: oldseats,
                        oldtribune: oldtribune
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response){ // What to do if we succeed
                        var count = 0;
                        if(response != 'unavailible'){
                            // loop though every seat to check if they are the selected seat, if so they get the class current-selected and become selected.
                            response.forEach(function(res) {
                                for (var i=0; i< seats.length;i++) {
                                    if(seats[i].col == res['GridSeatNum'] && seats[i].row == res['PhyRowId']){
                                        $(seats[i].seat).addClass("current-selected");
                                        count++;
                                    }
                                }
                            });

                            // modify done button to add/remove the disabled feature 
                            if(count == nuberOfSeat){
                                $(".layout-btn-done").removeAttr("disabled");
                            }else{
                                $(".layout-btn-done").attr("disabled");
                            }
                        }else{
                            // show message if there are not enough availible seats
                            alert('not enough seats availible');
                        }
                    },
                    error: function(data){
                        console.log('Error:', data);
                    }
                });
            },
            selectionDone: function (_array) {
                // get all selected seats
                var object = $('.current-selected');

                // create an object with the tribune id and an empty array to fill with the chosen seats
                var obj = {
                    tribune_id: id,
                    seats: []
                };

                // loop through the selected seats
                $.each( object, function( index, value ){
                    // get the seat defenition
                    var _json = JSON.parse(value.dataset.seatdefination);

                    // add all values to a temp object array
                    var temp_arr = {};
                    $.each(_json, function(key, val){
                        temp_arr[key] = val;
                    });
                    
                    // add the temp object array to the seats array in obj
                    obj['seats'].push(temp_arr);
                });

                // set the variable for storing the previously selected seat + tribune
                var old_seat = "";
                var old_tribune = "";

                // get the previously selected seat + tribune
                old_seat = $('#seatpicker_seat').val();
                old_tribune = $('#seatpicker_tribune').val();

                // get the amount of colomns in the first row
                var maximum = $( "ul.seat-area-row" ).eq( 0 ).children().length;

                // add the selected seats to the database as an temperary reservation
                $.ajax({
                    url: 'reservations/add_reservation',
                    type: "post",
                    data: {
                        id: id,
                        data: obj['seats'],
                        max: maximum,
                        old_seat: old_seat,
                        old_tribune: old_tribune
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response){
                        // check if the response in not empty
                        if(response === null || response === "" || response === undefined){
                            
                            // create a string containing all chosen seat names
                            var seat_content = "";
                            $.each(obj['seats'], function(key, val){
                                seat_content += val['seatName'] + ',';
                            });

                            // remove the last , at the end of the string
                            seat_content = seat_content.slice(0,-1);         
                            
                            // set the values in the hidden input boxes
                            document.getElementById("seatpicker_seat").value = seat_content;
                            document.getElementById("seatpicker_tribune").value = id;

                            // reload the tribune
                            $('#tribunes').trigger('change');
                            // alert("uw reservering is tijdelijk vast gezet voor de komende 20 minuten");
                        }else{
                            // reload the tribune
                            $('#tribunes').trigger('change');
                            alert(response);
                        }
                    }
                });
            },
            cancel: function () {
                return false;
            }
        });
    }
    
    function fix_temp_reserved(id){
        // get all seats to check and modify
        var container = $("ul.seat-area-row").children();
        
        // get the previously selected seats
        var oldseats = $('#seatpicker_seat').val();

        // split all seat names of the previously chosen seats into an array
        var arr = oldseats.split(",");
        
        // get the previously selected tribune
        var oldtribune = $('#seatpicker_tribune').val();

        // check if the user already chose a seat
        if(oldtribune == id){
            // loop through all items in the seatpicker
            for (var i=0; i< container.length;i++) {
                // get the seatdefenition
                var seat_defenition = container[i].dataset.seatdefination;

                // see if the item had a seat defenition
                if(seat_defenition !== undefined){
                    // convert the seatdefenition to an array
                    var _json = JSON.parse(seat_defenition);
                    
                    // loop through all previously chosen seats
                    arr.forEach(function(result) {
                        // check if the seat name matches a value in the old seats array
                        if(_json['seatName'] == result){
                            // console.log(result);
                            // add the folowing classes to the previously selected seats
                            $(container[i]).addClass("current-selected");
                            $(container[i]).addClass("can-select");
                        }
                    });
                }
            }
        }
    }
});
//*/