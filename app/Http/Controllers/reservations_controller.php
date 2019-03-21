<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\reservations;
use App\tribune;
use Carbon\Carbon;
use \PhpOffice\PhpSpreadsheet\IOFactory;  //composer require phpoffice/phpspreadsheet
use App\Http\Requests\seat_validation;

class reservations_controller extends Controller
{
    // function for direct access to the seatpicker (for testing purposes)
    function selector(){
        // get all tribunes
        $tribunes = tribune::select('name', 'id')->where('visible', '=', 1)->where('active', '=', 1)->get();

        // open the seatpicker view and pass all tribunes along
        return view('seatpicker', ['tribunes' => $tribunes]);
    }

    // function for using the view from another page
    function remote_page($data = array()){
        $data["tribunes"] = $this->tribune_model->gettribunes();
        $page = $this->reservations_module->get_page();
        $data["variant"] = $this->variant;
        $data["options"] = $this->options;
        $data["page"] = $page;

        // Sort function
        function sort_objects_by_name($a, $b) {
            return strnatcasecmp($a->name, $b->name);
        }

        $items = array();

        $data['base_url'] = $page->get_url();
        $data["items"] = $items;
        $data["total_rows"] = 0;

        $method = __FUNCTION__;
        if ($alt_method = Module_method_rewriter::get_alt_method_name($this->reservations_module->module,__FUNCTION__,$this->base->language))
        {
            $method = $alt_method;
        }

        $config['uri_segment'] = $this->uri->total_segments();
        $config['use_page_numbers'] = true;
        $config['base_url'] = $data['base_url'] . "/".$method;
        $config['first_url'] = $data['base_url'];
        $config['total_rows'] = $data["total_rows"];


        // load view with data
        $view = $this->reservations_module->return_module_view('seatpicker', $page->is_mobile(), $data);

        $extra_data["overview"] = true;
        return $view;
    }

    // set permanant reservation
    function set_reservation($data = array()){
        $this->reservations_model->set_reservation($data);
    }

    // cut the excess colomns off of the excel file
	function trim_edge($array){
        // rename the indexing for easier usage
        $reorder = array();
        foreach($array as $value){
            $arr_temp = array();
            foreach($value as $subValue){
                array_push($arr_temp, $subValue);
            }
            array_push($reorder, $arr_temp);
        }

		$start = "";
        $end = 0;
        // check to see at what colomn the values start and end in the excel file to cut the exces edges of
		foreach($reorder as $key => $value){
			foreach($value as $subKey => $subValue){
				if($subValue != null && is_numeric($subValue)){
                    // check what the first filled column is in all rows and store the ealiest column index
					if($start > $subKey || !is_numeric($start)){
						$start = $subKey;
					}
                    
                    // check what the last filled column is in all rows and store the latest column index
					if($end < ($subKey-($start -1))){
						$end = ($subKey-($start -1));
					}
				}
			}
		}

        $result = array();
        // cut the excess colomns off 
		foreach($reorder as $key => $value){
			$row = array_slice($value, $start, $end);
			array_push($result, $row);
		}

		return $result;
	}

    // get the max amount of filled colomns on any row of the excel file
    function get_max_columns($excel){
        // trim the columns of the excel file
        $array = $this->trim_edge($excel);

        // count the amount of columns
        $result = count($array[0]);
        
        return $result;
    }

    // convert the excel file to an array
    function json_to_array($excel){
        // trim the columns of the excel file
        $array = $this->trim_edge($excel);

        // add all the excel values into an array
        $tribune = array();
        foreach($array as $value){
            $arr_temp = array();
            foreach($value as $subValue){
                array_push($arr_temp, $subValue);
            }
            array_push($tribune, $arr_temp);
        }

        // remove all empty colomns and rows
        foreach($tribune as $key => $value){
            foreach($value as $subKey => $subValue){
                // if the value inside the colomn is not a number than remove the colomn from the array
                if (!is_numeric($subValue)) {
                    unset($tribune[$key][$subKey]);
                }
            }
            // if the row has no columns in it remove the row
            if (empty($tribune[$key])) {
                unset($tribune[$key]);
            }
        }

        // reindex the array to start from 1 instead of 0 to match the index with the coresponding tribune row
        $result = array_combine(range(1, count($tribune)), array_values($tribune));
        
        return $result;
    }

    // get the excel file using the json from the database
    function get_excel($data){
        // convert the json to array
        $json = json_decode($data)[0];
        $tribune_data = json_decode($json->tribune);

        // get the excel location
        $FileUrl = base_path() . "\\" . $tribune_data->url;
        
        // create a variable the put the final result in
		$result = array();

        //split the name up in a name and an extention
        $name = explode(".", $tribune_data->name);
        
        // check what the extension is of the file 
		$extension = ucfirst(end($name));
        
        // initialisation of the excel reader for the xls/xlsx file
		$reader = IOFactory::createReader($extension);

        // get contents of the excel file and continue if succesfull
		if ( $spreadsheet = $reader->load($FileUrl) ) {
            // convert excel spreadsheet to array and continue if succesfull
			if($excel = $spreadsheet->getActiveSheet()->toArray(null, true, true, true)){
                return $excel;
            }
        }
        return false;
    }

    // create the json that makes up the base to transform into the frontend seatpicker
    function get_json($data){
        // get the tribune array
        $tribune = $data['tribune'];

        // this is the outer shell of the json
        $seatpicker = (object) array(
            'product_id' => 46539040,
            'freeSeating' => false,
            'tempTransId' => '1ecae165f2d86315fea19963d0ded41a',
            'seatLayout' => (object) array(
                'colAreas' => (object) array(
                    'Count' => 2,
                    'intMaxSeatId' => $data['colomns'],
                    'intMinSeatId' => 1,
                    'objArea' => array()
                )
            ),
            'areas' => array(),
            'groupedSeats' => array()
        );

        // this is the second layer of the json
        $objArea = (object) array(
            'AreaDesc' => "EXECUTIVE",
            'AreaCode' => "0000000003",
            'AreaNum' => "1",
            'HasCurrentOrder' => true,
            'objRow' => array()
        );
        
        // create the rows with seats and add them row by row to the second layer
        $count = 0; 
        foreach($data['tribune'] as $key => $value){
            $count++;
            $objRow = (object) array(
                "GridRowId" => ($key+1),
                "PhyRowId" => $count,
                "objSeat" => array()
            );
        
            $subcount = 0;
            foreach($value as $subKey => $subValue){
                $subcount++;
                $reserved = 0;
                
                if(in_array($subValue, $data['reservations'])){
                    $reserved = 1;
                }
        
                array_push($objRow->objSeat, (object) array(
                    "GridSeatNum" => ($subKey+1), 
                    "SeatStatus" => $reserved,
                    "seatNumber" => $subcount,
                    "seatName" => $subValue
                ));
            }
            array_push($objArea->objRow, $objRow);
            
        };
        
        // add the second layer to the outer layer when the second layer is complete
        array_push($seatpicker->seatLayout->colAreas->objArea, $objArea);

        // turn the seatpicker compilation into json to generade the seatpicker
        return json_encode($seatpicker);
    }

    /********************************************************
    *                                                       *
    *             ajax request (seat selector)              *
    *                                                       *
    ********************************************************/

    // get the selected tribune
    function gettribune(Request $request){
        // this is to store the required data for building the selected tribune
        $local_data = array();

        // get the posted tribune id of the selected tribune
        $tribune_id = $request->tribune;

        // get the json from the databse using the tribune id with the excel location
        $tribune_json = tribune::select("tribune")->where("id", "=", $tribune_id)->get();

        $this->validate_prereserve($tribune_id);

        // get the excel file using the json from the database
        $local_data["excel"] = $this->get_excel($tribune_json);

        // check to see if the excel could be successfully read
        if($local_data["excel"] != false){
            // convert the excel file to an array
            $local_data["tribune"] = $this->json_to_array($local_data["excel"]);
    
            // get the max amount of filled colomns on any row of the excel file
            $local_data["colomns"] = $this->get_max_columns($local_data["excel"]);
    
            // get the seat names of the reserved seats
            $local_data["reservations"] = $this->getreservations($tribune_id);

            // create the json that makes up the base to transform into the frontend seatpicker
            $result = $this->get_json($local_data);
        } else{
            $result = "";
        }

        echo $result;
    }

    function validate_prereserve($tribune_id){
        //remove all temp reservations of 20 min or older
        $dt = Carbon::now();   //create object for current date/time
        $dt->modify('1 minutes ago');   //substract 1 minute for testing purpose only (so you dont have to wait too long)
        //$dt->modify('20 minutes ago');   //substract 20 minutes
        $sdt = $dt->format('Y-m-d H:i:s');  //format it into a datetime string

        // get all rows with expired pre reservations
        $pre_res = reservations::where("pre_reserve", "<", $sdt)->where("id", "=", $tribune_id)->get();

        //go through all results and unset the temp reservaion
        foreach ($pre_res as $item)
        {
            $item->pre_reserve = null;
            $item->save();
        }
    }

    // get the seat names of the reserved seats
    function getreservations($id){
        // get the reserved seats from the selected tribune using its id
        $result = reservations::select("seat_name")
        ->where("tribune_id", "=", $id)
        ->where("seat_name", "!=", "stairs")
        ->where(function ($query) {
            $query->whereNotNull("customer_id")
            ->orwhereNotNull("pre_reserve");
        })->get();

        $arr_reserved = array();
        foreach($result as $item){
            array_push($arr_reserved, $item->seat_name);
        }

        return $arr_reserved;
    }

    // get the amount of rows in the selected tribune
    function getrows($id){
        // get the amount of rows of the selected tribune using the id
        $rows = reservations::distinct('row')->count('row');

        return $rows;
    }

    // get the rows and columns of all unavalible positions (reserved seats + stairs)
    function get_unavailible($id, $old_seats=array(), $old_tribune=false){
        // get the rows and columns of the positions that are not seats
        $result= reservations::where("tribune_id", "=", $id)->where("seat_name", "=", "stairs")->get();


        $arr_stairs = array();
        foreach ($result as $item)
        {
            //make a new array item if it dous not yet exist
            if(!isset($arr_stairs[$item->row])){
                $arr_stairs[$item->row] = array();
            }
            
            // add seats to the array
            array_push($arr_stairs[$item->row], $item->colomn);
        }

        if($old_seats === null){
            $old_seats = array();
        }

        if(is_string($old_seats)){
            $old_seats = explode(',', $old_seats);
        }

        // get the rows and columns of the reserved seats
        $result = reservations::whereNotIn("seat_name", $old_seats)
        ->where("tribune_id", "=", $id) 
        ->where(function ($query) {
            $query->whereNotNull("customer_id")
            ->orwhereNotNull("pre_reserve");
        })->get();
		
		$arr_reserved = array();
		foreach ($result as $item)
        {
            //make a new array item if it dous not yet exist
            if(!isset($arr_reserved[$item->row])){
                $arr_reserved[$item->row] = array();
            }
            
            // add seats to the array
            array_push($arr_reserved[$item->row], $item->colomn);
        }

        // get the amount of rows in the selected tribune
        $rows= $this->getrows($id);

        // create a empty array value for each tribune row to fill up with unavilible positions
        $result = array();
        for($i = 1; $i <= $rows; $i++){
            $result[$i] = array();
        }

        // add the stairs to the unavailible array called result
        foreach($arr_stairs as $key => $value){
            foreach($value as $subValue){
                array_push($result[$key], $subValue);
            }
        }

        // add the reserved seats to the unavailible array called result
        foreach($arr_reserved as $key => $value){
            foreach($value as $subValue){
                array_push($result[$key], $subValue);
            }
        }

        return $result;
    }

    // check if the selected seats are availible and if not where they can move to
    function checkseats(Request $request){
        $data            = $request->data; // seat selection
        $max             = $request->max; // number of positions in a row to define out of bounds;
        $amount          = $request->amount; // amount of seats that the user wants te reserve
        $id              = $request->id; // the id of the selected tribune
        $old_seats       = $request->oldseats; // the previously selected seats if any
        $old_tribune     = $request->oldtribune; // the previously selected tribune if any
        $arr_unavailible = $this->get_unavailible($id, $old_seats, $old_tribune); // column numbers of the stairs as well as the reserved seats sorted by row
        $rows            = $this->getrows($id); // the number of active rows in the seat picker
        $no_space        = false; // check to disable check_span

        // move the selected seat to the left to make the selected seat be in the middle 
        if($amount % 2 == 0){
            $subtract = ($amount / 2) -1;
        }else{
            $subtract = ($amount / 2) - 0.5;
        }

        // add seats to the selected seat to match the requested amount of seats for the reservation
        $arr_data = array();
        $sub = $subtract;
        for($i=0;$i<$amount;$i++){
            $safe = false;
            // check if the amount to subtract is not more than what is availible if so lower the amount to subtract
            while($safe == false){
                if(($data['GridSeatNum'] + ($i-$sub))<=0){
                    $sub--;
                }else{
                    $safe = true;
                }
            }
            // add seat to the selected seats called arr_data
            $array = ['PhyRowId'=>$data['PhyRowId'],'GridSeatNum'=>($data['GridSeatNum'] + ($i-$sub))];
            array_push($arr_data, $array);
        }
        
        // check if the position is availible, if not move the selection to the closest possible position
        $arr_chosen = $this->check_reserved($arr_data, $max, $rows, $amount, $arr_unavailible, $subtract);

        // check if there are no spots availible with the check_span funtion, if so than disable the check span function and check for seats again
        if($arr_chosen == 'unavailible'){
            $no_space = true;
            $arr_chosen = $this->check_reserved($arr_data, $max, $rows, $amount, $arr_unavailible, $subtract, $no_space);
        }

        // return the accepted seat position(s)
        echo json_encode($arr_chosen);
    }

    // check if the currently selected seats are availible
    function check_if_valid($arr_check, $maximum, $arr_unavailible, $left_right){
        // loop through the selected seats
        $count_succesful = 0;
        foreach($arr_check as $item){
            // get the row and column of the selected seat
            $row = $item['PhyRowId'];
            $col = $item['GridSeatNum'];

            // check if the selected seat is not in the unavailible positions array
            $condition1 = !in_array($col, $arr_unavailible[$row]);
            // check if the selected seat does not go out of bounds
            $condition2 = $col > 0 && $col < $maximum;

            if($condition1 && $condition2){
                $count_succesful++;
            }else if(!$condition2 && $left_right){
                return "out of bounds";
            }else{
                break;
            }
        }

        return $count_succesful;
    }

    // check how many seats are empty next to the selected seats
    function check_span($arr_unavailible, $arr_check, $maximum, $no_space){
        // check if the span is allowed to be executed
        if($no_space == false){
            // add the values for out of bounds to the unavailible array
            $unavailible = array_merge($arr_unavailible[$arr_check[0]['PhyRowId']], [0, $maximum]);

            // get the begin/end positions
            $begin_position = $arr_check[0]['GridSeatNum'];
            $end_position = $arr_check[count($arr_check) -1]['GridSeatNum'];
            
            // check the empty seats on the left side of the seleted seats
            $count_left = "";
            foreach($unavailible as $item){
                // get the diference between the begin seat and the closest unavailible position
                $dif = abs($begin_position - $item);

                // check if the current $count_left is numeric, if so check if its higher than the value in $dif
                $condition1 = $count_left > $dif || !is_numeric($count_left);
                // check if $begin_position is higher or equal to the unavailible position as well as whether $dif is higher than 0
                $condition2 = $begin_position >= $item && $dif > 0;
                // check if $begin_position is heigher than 0
                $condition3 = $begin_position > 0;

                if($condition1 && $condition2 && $condition3){
                    $count_left = $dif;
                }
            }

            // check if $count_left is a number
            if(is_numeric($count_left)){
                $count_left--;
            }

            // check the empty seats on the right side of the seleted seats
            $count_right = "";
            foreach($unavailible as $item){
                // get the diference between the begin seat and the closest unavailible position
                $dif = abs($end_position - $item);

                // check if the current $count_right is numeric, if so check if its higher than the value in $dif
                $condition1 = $count_right > $dif || !is_numeric($count_right);
                // check if $end_position is lower to the unavailible position as well as whether $dif is higher than 0
                $condition2 = $end_position < $item && $dif > 0;
                // check if $end_position is heigher than 0
                $condition3 = $end_position < $maximum;

                if($condition1 && $condition2 && $condition3){
                    $count_right = $dif;
                }
            }

            // check if $count_right is a number
            if(is_numeric($count_right)){
                $count_right--;
            }
                
        }else{
            // set $count_left + $count_right at 0 when the function is not allowed to be used
            $count_left = 0;
            $count_right = 0;
        }

        return ["left" => $count_left, "right" => $count_right];
    }

    // check to see if the selection can move to the left and return the amount of steps to move
    function check_left($val_reserved, $arr_unavailible, $amount, $maximum, $no_space){
        // get the reserved seat
        $reserved = $val_reserved;

        // set $succes to false so we can use the while loop
        $succes = false;

        // set $amount_to_move to add the final result
        $amount_to_move = 0;

        // keep looping until a availible position is found or until the selection goes out of bounds
        while($succes == false){
            
            // move the selected seats to the left of the reserved seat
            $arr_check = array();
            for($i=$amount;$i>0;$i--){
                $row = $reserved;
                $array = ['PhyRowId'=>$row['PhyRowId'],'GridSeatNum'=>($row['GridSeatNum'] - $i)];
                array_push($arr_check, $array);
            }
            
            // check if the currently selected seats are availible
            $count_succesful = $this->check_if_valid($arr_check, $maximum, $arr_unavailible, true);

            // check if the selection is out of bounds
            if(!is_numeric($count_succesful)){
                $succes = true; 
                return -999;
            }

            // check how many seats are empty next to the selected seats
            $arr_span = $this->check_span($arr_unavailible, $arr_check, $maximum, $no_space);

            // check if the availible seats are of the same amount as there are selected seats
            $condition1 = $count_succesful == $amount;
            // check if there is not just 1 empty seat on the left side
            $condition2 = $arr_span['left'] == 0 || $arr_span['left'] > 1;
            // check if there is not just 1 empty seat on the right side
            $condition3 = $arr_span['right'] == 0 || $arr_span['right'] > 1;
            
            if($condition1 && $condition2 && $condition3){
                $succes = true; 
                // set the column diferance between the original reserved seat and the new selected seat
                $amount_to_move = $val_reserved['GridSeatNum'] - $arr_check[0]['GridSeatNum'];
            }else{
                // set the new reserved seat to move further left
                if(!$condition2 || !$condition3){
                    $reserved = $arr_check[($amount -1)];
                }else{
                    $reserved = $arr_check[$count_succesful];
                }
            }
        }

        return $amount_to_move;
    }

    // check to see if the selection can move to the right and return the amount of steps to move
    function check_right($val_reserved, $arr_unavailible, $amount, $maximum, $no_space){
        // get the reserved seat
        $reserved = $val_reserved;

        // set $succes to false so we can use the while loop
        $succes = false;

        // set $amount_to_move to add the final result
        $amount_to_move = 0;
            
        // keep looping until a availible position is found or until the selection goes out of bounds
        while($succes == false){
            
            // move the selected seats to the right of the reserved seat
            $arr_check = array();
            for($i=1;$i<=$amount;$i++){
                $row = $reserved;
                $array = ['PhyRowId'=>$row['PhyRowId'],'GridSeatNum'=>($row['GridSeatNum'] + $i)];
                array_push($arr_check, $array);
            }
            
            // check if the currently selected seats are availible
            $count_succesful = $this->check_if_valid($arr_check, $maximum, $arr_unavailible, true);

            if(!is_numeric($count_succesful)){
                $succes = true;
                return -999;
            }

            // check how many seats are empty next to the selected seats
            $arr_span = $this->check_span($arr_unavailible, $arr_check, $maximum, $no_space);

            // check if the availible seats are of the same amount as there are selected seats
            $condition1 = $count_succesful == $amount;
            // check if there is not just 1 empty seat on the left side
            $condition2 = $arr_span['left'] == 0 || $arr_span['left'] > 1;
            // check if there is not just 1 empty seat on the right side
            $condition3 = $arr_span['right'] == 0 || $arr_span['right'] > 1;
            
            if($condition1 && $condition2 && $condition3){
                $succes = true; 
                // set the column diferance between the original reserved seat and the new selected seat
                $amount_to_move = $arr_check[0]['GridSeatNum'] - $val_reserved['GridSeatNum'];
            }else{
                // set the new reserved seat to move further right
                if(!$condition2 || !$condition3){
                    $reserved = $arr_check[0];
                }else{
                    $reserved = $arr_check[$count_succesful];
                }
            }
        }

        return $amount_to_move;
    }

    // check to see if the selection can move up + left/right and return the amount of steps to move
    function check_above($data, $unavailible, $amount, $max, $left_max, $right_max,$subtract, $no_space){
        // get the selected seats
        $arr_data = $data;

        // set the array for the final result
        $arr_return_val = ['up'=>0, 'left'=>0, 'right'=>0];

        // set $amount_to_move to add the final result
        $succes = false;

        // count the number of times we go up a row
        $loop_count = 0;

        // check if the $left_max is a number, if not make it the same as $right_max
        if($left_max <= -4){
            $left_max = $right_max;
        }

        // check if the $right_max is a number, if not make it the same as $left_max
        if($right_max <= -4){
            $right_max = $left_max;
        }

        // check if the $right_max and $left_max is a number, if not make it higher number than posible
        if($right_max <= -4 && $left_max <= -4){
            $right_max = 99999;
            $left_max = 99999;
        }

        // keep looping until a availible position is found or until the selection goes out of bounds
        while($succes == false){
            // raise the counter for moving up
            $loop_count++;

            // check if raising the row makes it go out of bounds
            if(($data[0]['PhyRowId'] - $loop_count) > 0){

                // change the row of the selected seats to check if there is space on that row
                for($i = 0; $i<count($arr_data); $i++){
                    $arr_data[$i]['PhyRowId'] = $data[$i]['PhyRowId'] - $loop_count;
                }

                // check if the currently selected seats are availible
                $count_succesful = $this->check_if_valid($arr_data, $max, $unavailible, false);

                // check how many seats are empty next to the selected seats
                $arr_span = $this->check_span($unavailible, $arr_data, $max, $no_space);

                // check if the availible seats are of the same amount as there are selected seats
                $condition1 = $count_succesful != $amount;
                // check if there is not just 1 empty seat on the left side
                $condition2 = $arr_span['left'] == 1 && $arr_span['right'] == 0;
                // check if there is not just 1 empty seat on the right side
                $condition3 = $arr_span['left'] == 0 && $arr_span['right'] == 1;
                
                if($condition1 || $condition2 || $condition3){
                    // set the reserved seat
                    if($condition1){
                        $reserved_seat = $arr_data[$count_succesful];
                    }else{
                        $reserved_seat = $arr_data[0];
                    }
                    
                    // set the new selected seat
                    $selected_seat = $arr_data[0]; 

                    // get the differance between the reserved seat and the selected seat
                    $col_differance = $reserved_seat['GridSeatNum'] - $selected_seat['GridSeatNum'];

                    // set the amount to move left/right variable
                    $left = -9999;
                    $right = -9999; 

                    // check if $left_max is a number and if it's still higher than 0 when we subtract $loop_count
                    if(($left_max - $loop_count) > 0 || $left_max <= -4){
                        // check if there is an availible position to the left in the row
                        $left = $this->check_left($reserved_seat, $unavailible, $amount, $max, $no_space); 
                        // if left is not out of bounds set the amount to move left   
                        if($left > -4){$left = $left - $col_differance;}
                    }

                    // check if $right_max is a number and if it's still higher than 0 when we subtract $loop_count
                    if(($right_max - $loop_count) > 0 || $right_max <= -4){
                        // check if there is an availible position to the right in the row
                        $right = $this->check_right($reserved_seat, $unavailible, $amount, $max, $no_space);  
                        // if right is not out of bounds set the amount to move right   
                        if($right > -4){$right = $right + $col_differance;} 
                    }
                    
                    // check if $left is a number and whether moving left has less steps than moving right does
                    $condition1 = ($left - ($amount - $subtract))<=($right - $subtract) && $left > -4;
                    // check if left is numeric while right is not
                    $condition2 = $right <= -4 && $left > -4;

                    if( $condition1 || $condition2 ){
                        // set the temperary values
                        $arr_temp_val = ['up'=>$loop_count, 'left'=>$left, 'right'=>0];

                        // check if the temp values have less steps to move as the the currently lowest amount of steps
                        if(array_sum($arr_return_val) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                            // set the new lowest amount of steps to move
                            $arr_return_val = $arr_temp_val;
                        }
                    }
                    else if($right > -4){
                        // set the temperary values
                        $arr_temp_val = ['up'=>$loop_count, 'left'=>0, 'right'=>$right];

                        // check if the left value is set in the curent return array
                        if($arr_return_val['left'] > 0){
                            // check if the temp values have less steps to move as the the currently lowest amount of steps
                            if((array_sum($arr_return_val) - ($amount-$subtract) ) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                                // set the new lowest amount of steps to move
                                $arr_return_val = $arr_temp_val;
                            }
                        }else{
                            // check if the temp values have less steps to move as the the currently lowest amount of steps
                            if(array_sum($arr_return_val) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                                // set the new lowest amount of steps to move
                                $arr_return_val = $arr_temp_val;
                            }
                        }
                    }
                }else{
                    // set the temperary values
                    $arr_temp_val = ['up'=>$loop_count, 'left'=>0, 'right'=>0];

                    // check if there is just 1 empty seat on the left side while there is at least 1 seat on the right side
                    if($arr_span['left'] == 1 && $arr_span['right'] > 0){
                        $arr_temp_val['left']++;
                    // check if there is just 1 empty seat on the right side while there is at least 1 seat on the left side
                    }else if($arr_span['right'] == 1 && $arr_span['left'] > 0){
                        $arr_temp_val['right']++;
                    }

                        // check if the left value is set in the curent return array
                    if($arr_return_val['left'] > 0){
                        // check if the temp values have less steps to move as the the currently lowest amount of steps
                        if((array_sum($arr_return_val) - ($amount-$subtract) ) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                            // set the new lowest amount of steps to move
                            $arr_return_val = $arr_temp_val;
                        }
                    }else{
                        // check if the temp values have less steps to move as the the currently lowest amount of steps
                        if((array_sum($arr_return_val) - $subtract) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                            // set the new lowest amount of steps to move
                            $arr_return_val = $arr_temp_val;
                        }
                    }
                }
                    
            }else{
                return $arr_return_val;
            }
        }
    }

    // check to see if the selection can move down + left/right and return the amount of steps to move
    function check_below($data, $unavailible, $amount, $max, $left_max, $right_max, $rows, $subtract, $no_space){
        // get the selected seats
        $arr_data = $data;

        // set the array for the final result
        $arr_return_val = ['down'=>0, 'left'=>0, 'right'=>0];

        // set $amount_to_move to add the final result
        $succes = false;

        // count the number of times we go down a row
        $loop_count = 0;

        // check if the $left_max is a number, if not make it the same as $right_max
        if($left_max <= -4){
            $left_max = $right_max;
        }

        // check if the $right_max is a number, if not make it the same as $left_max
        if($right_max <= -4){
            $right_max = $left_max;
        }

        // check if the $right_max and $left_max is a number, if not make it higher number than posible
        if($right_max <= -4 && $left_max <= -4){
            $right_max = 99999;
            $left_max = 99999;
        }

        // keep looping until a availible position is found or until the selection goes out of bounds
        while($succes == false){
            // raise the counter for moving down
            $loop_count++;

            // check if raising the row makes it go out of bounds
            if(($data[0]['PhyRowId'] + $loop_count) <= $rows){

                // change the row of the selected seats to check if there is space on that row
                for($i = 0; $i<count($arr_data); $i++){
                    $arr_data[$i]['PhyRowId'] = $data[$i]['PhyRowId'] + $loop_count;
                }

                // check if the currently selected seats are availible
                $count_succesful = $this->check_if_valid($arr_data, $max, $unavailible, false);

                // check how many seats are empty next to the selected seats
                $arr_span = $this->check_span($unavailible, $arr_data, $max, $no_space);

                // check if the availible seats are of the same amount as there are selected seats
                $condition1 = $count_succesful != $amount;
                // check if there is not just 1 empty seat on the left side
                $condition2 = $arr_span['left'] == 1 && $arr_span['right'] == 0;
                // check if there is not just 1 empty seat on the right side
                $condition3 = $arr_span['left'] == 0 && $arr_span['right'] == 1;
                
                if($condition1 || $condition2 || $condition3){
                    // set the reserved seat
                    if($condition1){
                        $reserved_seat = $arr_data[$count_succesful];
                    }else{
                        $reserved_seat = $arr_data[0];
                    }

                    // set the new selected seat
                    $selected_seat = $arr_data[0];

                    // get the differance between the reserved seat and the selected seat
                    $col_differance = $reserved_seat['GridSeatNum'] - $selected_seat['GridSeatNum'];

                    // set the amount to move left/right variable
                    $left = -9999;
                    $right = -9999;

                    // check if $left_max is a number and if it's still higher than 0 when we subtract $loop_count
                    if(($left_max - $loop_count) > 0 || $left_max <= -4){
                        // check if there is an availible position to the left in the row
                        $left = $this->check_left($reserved_seat, $unavailible, $amount, $max, $no_space);
                        // if left is not out of bounds set the amount to move left   
                        if($left > -4 ){$left = $left - $col_differance;}
                    }

                    // check if $right_max is a number and if it's still higher than 0 when we subtract $loop_count
                    if(($right_max - $loop_count) > 0 || $right_max <= -4){
                        // check if there is an availible position to the right in the row
                        $right = $this->check_right($reserved_seat, $unavailible, $amount, $max, $no_space);   
                        // if right is not out of bounds set the amount to move right   
                        if($right > -4 ){$right = $right + $col_differance;}
                    }

                    // check if $left is a number and whether moving left has less steps than moving right does
                    $condition1 = ($left - ($amount - $subtract))<=($right - $subtract) && $left > -4;
                    // check if left is numeric while right is not
                    $condition2 = $right <= -4 && $left > -4;
                    
                    if( $condition1 || $condition2 ){
                        // set the temperary values
                        $arr_temp_val = ['down'=>$loop_count, 'left'=>$left, 'right'=>0];

                        // check if the temp values have less steps to move as the the currently lowest amount of steps
                        if(array_sum($arr_return_val) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                            // set the new lowest amount of steps to move
                            $arr_return_val = $arr_temp_val;
                        }
                    }
                    else if($right > -4){
                        // set the temperary values
                        $arr_temp_val = ['down'=>$loop_count, 'left'=>0, 'right'=>$right];

                        // check if the left value is set in the curent return array
                        if($arr_return_val['left'] > 0){
                            // check if the temp values have less steps to move as the the currently lowest amount of steps
                            if((array_sum($arr_return_val) - ($amount-$subtract) ) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                                // set the new lowest amount of steps to move
                                $arr_return_val = $arr_temp_val;
                            }
                        }else{
                            // check if the temp values have less steps to move as the the currently lowest amount of steps
                            if(array_sum($arr_return_val) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                                // set the new lowest amount of steps to move
                                $arr_return_val = $arr_temp_val;
                            }
                        }
                    }
                }else{
                    // set the temperary values
                    $arr_temp_val = ['down'=>$loop_count, 'left'=>0, 'right'=>0];
                    
                    // check if there is just 1 empty seat on the left side while there is at least 1 seat on the right side
                    if($arr_span['left'] == 1 && $arr_span['right'] > 0){
                        $arr_temp_val['left']++;
                    // check if there is just 1 empty seat on the right side while there is at least 1 seat on the left side
                    }else if($arr_span['right'] == 1 && $arr_span['left'] > 0){
                        $arr_temp_val['right']++;
                    }

                    // check if the left value is set in the curent return array
                    if($arr_return_val['left'] > 0){
                        // check if the temp values have less steps to move as the the currently lowest amount of steps
                        if((array_sum($arr_return_val) - ($amount-$subtract) ) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                            // set the new lowest amount of steps to move
                            $arr_return_val = $arr_temp_val;
                        }
                    }else{
                        // check if the temp values have less steps to move as the the currently lowest amount of steps
                        if((array_sum($arr_return_val) - $subtract) > array_sum($arr_temp_val) || array_sum($arr_return_val) == 0){
                            // set the new lowest amount of steps to move
                            $arr_return_val = $arr_temp_val;
                        }
                    }
                }
                    
            }else{
                return $arr_return_val;
            }
        }
    }

    // move the selected seats to the left with the number of steps to move
    function move_left($data, $arr_unavailible, $amount_steps, $amount){
        // move te seats to the left
        $arr_check = array();
        foreach($data as $row){
            $array = ['PhyRowId'=>$row['PhyRowId'],'GridSeatNum'=>($row['GridSeatNum'] - $amount_steps)];
            array_push($arr_check, $array);
        }
        
        return $arr_check;
    }

    // move the selected seats to the right with the number of steps to move
    function move_right($data, $arr_unavailible, $amount_steps, $amount, $maximum){
        // move te seats to the right
        $arr_check = array();
        foreach($data as $row){
            $array = ['PhyRowId'=>$row['PhyRowId'],'GridSeatNum'=>($row['GridSeatNum'] + $amount_steps)];
            array_push($arr_check, $array);
        }
        
        return $arr_check;
    }
    
    // check for the reserved seats
    function check_reserved($data, $maximum, $rows, $amount, $arr_unavailible, $subtract, $no_space = false){
        // get the selected seats for manipulation
        $arr_data = $data;

        // check if the currently selected seats are availible
        $count_succesful = $this->check_if_valid($arr_data, $maximum, $arr_unavailible, false);

        // check how many seats are empty next to the selected seats
        $arr_span = $this->check_span($arr_unavailible, $arr_data, $maximum, $no_space);

        // check if the availible seats are of the same amount as there are selected seats
        $condition1 = $count_succesful != $amount;
        // check if there is not just 1 empty seat on the left side
        $condition2 = $arr_span['left'] == 1 && $arr_span['right'] == 0;
        // check if there is not just 1 empty seat on the right side
        $condition3 = $arr_span['left'] == 0 && $arr_span['right'] == 1;

        if($condition1 || $condition2 || $condition3){
            // set the reserved seat
            if($condition1){
                $reserved_seat = $arr_data[$count_succesful];
            }else{
                $reserved_seat = $arr_data[0];
            }

            // set the new selected seat
            $selected_seat = $arr_data[0];

            // get the differance between the reserved seat and the selected seat
            $col_differance = $reserved_seat['GridSeatNum'] - $selected_seat['GridSeatNum'];

            // check if there is an availible position to the left in the row
            $move_seats_left = $this->check_left($reserved_seat, $arr_unavailible, $amount, $maximum, $no_space); 
            // if left is not out of bounds set the amount to move left 
            if($move_seats_left > -4 ){$move_seats_left = $move_seats_left - $col_differance;}

            // check if there is an availible position to the right in the row
            $move_seats_right = $this->check_right($reserved_seat, $arr_unavailible, $amount, $maximum, $no_space);    
            // if right is not out of bounds set the amount to move right   
            if($move_seats_right > -4 ){$move_seats_right = $move_seats_right + $col_differance;}

            // check to see if the selection can move up + left/right and return the amount of steps to move
            $move_seats_above = $this->check_above($arr_data, $arr_unavailible, $amount, $maximum, $move_seats_left, $move_seats_right, $subtract, $no_space);  
            
            // check to see if the selection can move down + left/right and return the amount of steps to move
            $move_seats_below = $this->check_below($arr_data, $arr_unavailible, $amount, $maximum, $move_seats_left, $move_seats_right, $rows, $subtract, $no_space);  
 
            // count the amount of steps when moving up
            $sum_above = 0;
            if($move_seats_above['left']>0){
                $sum_above = array_sum($move_seats_above) - ($amount - $subtract);
            }else{
                $sum_above = array_sum($move_seats_above) - ($subtract);
            }

            // count the amount of steps when moving down
            $sum_below=0;
            if($move_seats_below['left']>0){
                $sum_below = array_sum($move_seats_below) - ($amount - $subtract);
            }else{
                $sum_below = array_sum($move_seats_below) - ($subtract);
            }

            // check if moving left is availible and if its less steps than moving up
            $condition1 = ($sum_above+3) < ($move_seats_left - ($amount -$subtract)) || $move_seats_left <= -4;
            // check if moving right is availible and if its less steps than moving up
            $condition2 = ($sum_above+3) < ($move_seats_right) || $move_seats_right <= -4;
            // check if moving down is unavailible and if moving up is less steps than moving down
            $condition3 = ($sum_above + $subtract) < $sum_below || array_sum($move_seats_below) == 0;
            // check if moving up is unavailible 
            $condition4 = array_sum($move_seats_above) > 0;

            // check if moving left is availible and if its less steps than moving down
            $condition5 = ($sum_below+3) < ($move_seats_left - ($amount -$subtract)) || $move_seats_left <= -4;
            // check if moving right is availible and if its less steps than moving down
            $condition6 = ($sum_below+3) < ($move_seats_right) || $move_seats_right <= -4;
            // check if moving up is unavailible and if moving down is less steps than moving down
            $condition7 = $sum_below <= ($sum_above + $subtract) || array_sum($move_seats_above) == 0;
            // check if moving down is unavailible 
            $condition8 = array_sum($move_seats_below) > 0;

            $arr_chosen = [];
            // moving up
            if( $condition1 && $condition2 && $condition3 && $condition4 ){
                // move seats up
                for($i=0;$i<count($arr_data);$i++){
                    $arr_data[$i]['PhyRowId'] = $selected_seat['PhyRowId'] - $move_seats_above['up'];
                }

                // move seats horizontaly
                if($move_seats_above['left'] > 0){
                    // move seats left
                    $arr_chosen = $this->move_left($arr_data, $arr_unavailible, $move_seats_above['left'], $amount);

                }else if($move_seats_above['right'] >0){
                    // move seats right
                    $arr_chosen = $this->move_right($arr_data, $arr_unavailible, $move_seats_above['right'], $amount, $maximum);

                }else if($move_seats_above['left'] == 0 && $move_seats_above['right'] == 0){
                    // dont move the seats anymore
                    $arr_chosen = $arr_data;
                }
            // moving down
            }else if( $condition5 && $condition6 && $condition7 && $condition8 ){
                // move seats down
                for($i=0;$i<count($arr_data);$i++){
                    $arr_data[$i]['PhyRowId'] = $selected_seat['PhyRowId'] + $move_seats_below['down'];
                }

                // move seats horizontaly
                if($move_seats_below['left'] > 0){
                    // move seats left
                    $arr_chosen = $this->move_left($arr_data, $arr_unavailible, $move_seats_below['left'], $amount);

                }else if($move_seats_below['right'] >0){
                    // move seats right
                    $arr_chosen = $this->move_right($arr_data, $arr_unavailible, $move_seats_below['right'], $amount, $maximum);

                }else if($move_seats_below['left'] == 0 && $move_seats_below['right'] == 0){
                    // dont move the seats anymore
                    $arr_chosen = $arr_data;
                }
            // check if moving left/right is possible
            }else if($move_seats_left > -4 || $move_seats_right > -4){
                // check if moving left is posible and if moving left is less or the same amount of steps as moving right
                $condition1 = ($move_seats_left - ($amount -$subtract))<=($move_seats_right - $subtract) && $move_seats_left > -4 ;
                // check if moving right is not availible while left is
                $condition2 = $move_seats_right <= -4 && $move_seats_left > -4 ;

                if( $condition1 || $condition2 ){
                    // moving left
                    $arr_chosen = $this->move_left($arr_data, $arr_unavailible, $move_seats_left, $amount);
                }
                // check if moving right is posible
                else if($move_seats_right > -4){
                    // moving right
                    $arr_chosen = $this->move_right($arr_data, $arr_unavailible, $move_seats_right, $amount, $maximum);
                }
            }else{
                return "unavailible";
            }
            return $arr_chosen;
        } 

        // check if there is just 1 empty seat on the left side while there is at least 1 seat on the right side
        if($arr_span['left'] == 1 && $arr_span['right'] > 0){
            for ($i = 0; $i<count($arr_data);$i++) {
                $arr_data[$i]['GridSeatNum']--;
            }
        // check if there is just 1 empty seat on the right side while there is at least 1 seat on the left side
        }else if($arr_span['right'] == 1 && $arr_span['left'] > 0){
            for ($i = 0; $i<count($arr_data);$i++) {
                $arr_data[$i]['GridSeatNum']++;
            }
        }

        return $arr_data;
    }

    /********************************************************
    *                                                       *
    *              ajax request (reserve seats)             *
    *                                                       *
    ********************************************************/

    // set temperary reservation
    function add_reservation(seat_validation $request){
        // get post data
        $data = $request->data;
        $id = $request->id;
        $max = $request->max;
        $old_seat = $request->old_seat;
        $old_tribune = $request->old_tribune;

        // set validation rules
        $validation = $this->array_check($data);

        // validate the input to make sure there is no manipulated data
        if($validation){
            // get the rows and columns of all unavalible positions (reserved seats + stairs)
            $arr_unavailible = $this->get_unavailible($id, $old_seat, $old_tribune);

            // check if the currently selected seats are availible
            $succesfull_validation = $this->check_if_valid($data, $max, $arr_unavailible, false);
    
            // check if the seats are still availible
            if($succesfull_validation === count($data)){
                // check if user ordered a different seat already
                if($old_seat !== "" && $old_seat !== false && $old_seat !== "undefined"){
                    // turn the string into an array to get the seat numbers from
                    $arr = explode(',', $old_seat);

                    // remove specific temperairy seats
                    reservations::where("tribune_id", "=", $old_tribune)->whereIn("seat_name", $arr)->update(['pre_reserve' => null]);
                }

                // get all seat names
                $arr = array();
                foreach ($data as $row)
                {
                    array_push($arr, $row['seatName']);
                }
                
                // set the by the user selected seats in the database as temporarily bought seats for 20 min or until the user selects other seats
                reservations::where("tribune_id", "=", $id)->whereIn("seat_name", $arr)->update(['pre_reserve' => date("Y-m-d H:i:s")]);

                return null;
            }
            else{
                // show message when the chosen seat is no longer availible so the user can chose a different seat
                $response = "the seat has just been sold to someone else";
                echo $response;
            }
        }
    }

    // validation for seat array data
    function array_check($arr){
        $count = 0;
        // loop through all selected seats and check if the values are ok
        foreach($arr as $item){
            $condition1 = is_numeric($item['GridSeatNum']);
            $condition2 = is_numeric($item['SeatStatus']);
            $condition3 = is_numeric($item['seatNumber']);
            $condition4 = is_numeric($item['seatName']);
            $condition5 = is_numeric($item['GridRowId']);
            $condition6 = is_numeric($item['PhyRowId']);
            $condition7 = is_numeric($item['AreaNum']);
            $condition8 = $item['AreaCode'] === "0000000003";
            $condition9 = $item['AreaDesc'] === "EXECUTIVE";

            if($condition1 && $condition2 && $condition3 && $condition4 && $condition5 && $condition6 && $condition7 && $condition8 && $condition9){
                $count++;
            }
        }

        // check if all seats were ok
		if($count === count($arr)){
			return true;
		}else{
			return false;
		}
	}
}
