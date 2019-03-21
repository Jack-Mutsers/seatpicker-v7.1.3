<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\reservations;
use App\tribune;
use Carbon\Carbon;
use \PhpOffice\PhpSpreadsheet\IOFactory; //composer require phpoffice/phpspreadsheet
use App\Http\Requests\tribune_validation;

class tribune_controller extends Controller
{

	function new_tribune(){
		return view('new_tribune');
	}

	function update_tribune(){
        $tribunes = tribune::select("name", "id")->get();
        return view('tribune_update', ["tribunes" => $tribunes]);
    }
    
	function change(request $request){
		
		$id = $this->input_validate($request->identifier);
		$oldid = $this->input_validate($request->id);
		
		if($oldid !== null && $oldid !== ""){
			$tribune_data = tribune::where("id", "=", htmlspecialchars($oldid, ENT_QUOTES))->get();
		}else{
			$tribune_data = tribune::where("id", "=", htmlspecialchars($id, ENT_QUOTES))->get();
		}
		
		return view('tribune_change', ["tribune_data" => $tribune_data]);
	}

	function save(tribune_validation $request){
        // get post data
        if($request->active == "on"){
            $active = true;
        }else{
            $active = false;
		};
		
        if($request->reservations == "on"){
            $reserve = true;
        }else{
            $reserve = false;
        };

        $name = $this->input_validate($request->name);

		$tribune = $this->getTribune($request, $request->tribune);
		
		if($tribune['valid']){
			$dt = Carbon::now();   //create object for current date/time
			$sdt = $dt->format('Y-m-d H:i:s');

			$insert = [
				'visible'       => 1, 
				'active'        => $active,
				'tribune'       => $tribune['tribune_json'],
				'name'          => $name,
				'creation_date' => $sdt
			];

			$id = tribune::insertGetId( $insert );
			
			$this->make_seats($tribune['tribune_json'], $id, $reserve);
			return redirect('/');
		}
		else{
			return redirect()->action('tribune_controller@new_tribune')->with('status', $error);
		}
    }
    
    function delete(request $request){
        $id = $this->input_validate($request->id);
        tribune::where("id", "=", $id)->delete();
        reservations::where("tribune_id", "=", $id)->delete();
        return null;
    }
    
    function update(request $request){
		$error = array();

		if($request->active == "on"){
            $active = true;
        }else{
            $active = false;
        };

        $id 		= $this->input_validate($request->id);
		$name 		= $this->getName($request->name, $request->oldname);
		$tribune 	= $this->getTribune($request, $request->tribune, $request->oldtribune);

		if($name['valid'] && $tribune['valid']){
			$update = [
				'visible'       => 1, 
				'active'        => $active,
				'name'          => $name['name']
			];

			if($tribune['tribune_json'] !== ""){
				$update['tribune'] = $tribune['tribune_json'];
			}

			tribune::find($id)->update($update);

			if($tribune['tribune_json'] !== ""){
				reservations::where("tribune_id", "=", $id)->delete();
				$this->make_seats($tribune['tribune_json'], $id);
			}
		}else{
			if($name['error'] !== ""){
				array_push($error, $name['error']);
			}

			if($tribune['error'] !== ""){
				array_push($error, $tribune['error']);
			}

			return redirect()->action('tribune_controller@change', ['id' => $id])->with('status', $error);
		}

		return redirect('/tribune/update');
	}

	public function download( $filename = '' ){
		// Check if file exists in app/storage/file folder
		$filename = 'example.xlsx';
        $file_path = base_path() . "\upload\\" . $filename;
        $headers = array(
            'Content-Type: xlsx',
            'Content-Disposition: attachment; filename='.$filename,
        );
        if ( file_exists( $file_path ) ) {
            // Send Download
            return \Response::download( $file_path, $filename, $headers );
        } else {
            // Error
            exit( 'Requested file does not exist on our server!' );
        }
    }

    function make_seats($upload, $tribune_id, $reserve = false){
		$upload_data = json_decode($upload);
		$FileUrl = base_path() . "\\" . $upload_data->url;
		$FileType = "";

		if(isset($upload_data->type)){
			$FileType = $upload_data->type;
		}
		
        $inputFileType = "Xls";
        
        //split the name up in a name and an extension
        $name = explode(".", $upload_data->name);
        
        // check what the extension is of the file 
        $extension = ucfirst(end($name));

		if($FileType == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || $extension == "Xlsx"){
			$inputFileType = 'Xlsx';
		}
		
		//    $inputFileType = 'Xlsx';
		//    $inputFileType = 'Xml';
		//    $inputFileType = 'Ods';
		//    $inputFileType = 'Slk';
		//    $inputFileType = 'Gnumeric';
		//    $inputFileType = 'Csv';
		
		/**  Create a new Reader of the type defined in $inputFileType  **/
		$reader = IOFactory::createReader($inputFileType);

		/**  Load $inputFileName to a Spreadsheet Object  **/
		if ( $spreadsheet = $reader->load($FileUrl) ) {
			if($excel = $spreadsheet->getActiveSheet()->toArray(null, true, true, true)){
				
				$reorder = array();
				foreach($excel as $value){
					$arr_temp = array();
					foreach($value as $subValue){
						array_push($arr_temp, $subValue);
					}
					array_push($reorder, $arr_temp);
				}

				$tribune = $this->trim_edge($reorder);
		
				foreach($tribune as $key => $value){
					$count = 0;
					foreach($value as $subKey => $subValue){
						if (!is_numeric($subValue)) {
							$tribune[$key][$subKey] = 'stairs';;
						}else{
							$count++;
						}
					}
					if ($count == 0) {
						unset($tribune[$key]);
					}
				}

                $result = array_combine(range(1, count($tribune)), array_values($tribune));

                $data = array();
                foreach($result as $key => $value){
                    foreach($value as $subKey => $subValue){
                        $seat = array(
                            'seat_name' => $subValue,
                            'row' => $key,
                            'colomn' => ($subKey + 1),
                            'tribune_id' => $tribune_id,
                            'customer_id' => null,
                            'order_date' => null
                        );

                        array_push($data, $seat);
                    }
                }

                reservations::insert($data);

				/************************************************
			 	* 												*
				* 			remove after test phase 			*
				* 												*
				************************************************/
				
				if($reserve){
					$this->temp_set_reserves($tribune_id);
				}

				/************************************************
			 	* 												*
				* 					end remove 					*
				* 												*
                ************************************************/
                
			}
		}
	}

	function trim_edge($array){

		$start = "";
		$end = 0;
		foreach($array as $key => $value){
			foreach($value as $subKey => $subValue){
				if($subValue != null && is_numeric($subValue)){
					if($start > $subKey || !is_numeric($start)){
						$start = $subKey;
					}
					
					if($end < ($subKey-($start -1))){
						$end = ($subKey-($start -1));
					}

				}
			}
		}
		
		$result = array();
		foreach($array as $key => $value){
			$row = array_slice($value, $start, $end);
			array_push($result, $row);
		}

		return $result;
	}
	
	function getName($name, $oldname){
		$result =['name' => '','error'=>'', 'valid'=>false];
		if($oldname != $name){
			$query_result = tribune::where("name","=",$name)->get();
			if(count($query_result) === 0){
				$result['valid'] = true;
				$result['name'] = $this->input_validate($name);
			}
		}else{
			$result['valid'] = true;
			$result['name'] = $this->input_validate($name);
		}

		return $result;
	}

	function getTribune($request, $tribune, $oldtribune = null){
		$result = ['tribune_json' => '', 'error' => '', 'valid' => true];
		if($tribune != null){

			$tribune_name   = $tribune->getClientOriginalName();
			$tribune_name   = str_replace(" ", "_", $tribune_name);

			if($oldtribune != $tribune_name){
				if($request->hasFile('tribune')){
					if( $request->file('tribune')->isValid()){
						$tribune = $request->tribune;
						if($this->file_check($tribune)){
							$file_path = base_path() . "\\upload";
							if($tribune->move($file_path, $tribune_name)){
								$tribune_object = (object) array(
									'url' => "upload\\" . $tribune_name,
									'name' => $tribune_name,
									'image' => false
								);
			
								$result['tribune_json'] = json_encode($tribune_object);
							}
						}else{
							$result['valid'] = false;
							$result['error'] = 'file has to be an xls/xlsx type';
						}
					}else{
						$result['valid'] = false;
						$result['error'] = 'file has to be an xls/xlsx type';
					}
				}else{
					$result['valid'] = false;
					$result['error'] = 'file has to be an xls/xlsx type';
				}
			}
		}
		return $result;
	}

	function input_validate($item){
		$search = array('&', '<', '>', '€', '‘', '’', '“', '”', '–', '—', '¡', '¢','£', '¤', '¥', '¦', '§', '¨', '©', 'ª', '«', '¬', '®', '¯', '°', '±', '²', '³', '´', 'µ', '¶', '·', '¸', '¹', 'º', '»', '¼', '½', '¾', '¿', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', '×', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'Þ', 'ß', 'à', 'á', 'â', 'ã','ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', '÷', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'þ', 'ÿ','Œ', 'œ', '‚', '„', '…', '™', '•', '˜');

		$replace  = array('&amp;', '&lt;', '&gt;', '&euro;', '&lsquo;', '&rsquo;', '&ldquo;','&rdquo;', '&ndash;', '&mdash;', '&iexcl;','&cent;', '&pound;', '&curren;', '&yen;', '&brvbar;', '&sect;', '&uml;', '&copy;', '&ordf;', '&laquo;', '&not;', '&reg;', '&macr;', '&deg;', '&plusmn;', '&sup2;', '&sup3;', '&acute;', '&micro;', '&para;', '&middot;', '&cedil;', '&sup1;', '&ordm;', '&raquo;', '&frac14;', '&frac12;', '&frac34;', '&iquest;', '&Agrave;', '&Aacute;', '&Acirc;', '&Atilde;', '&Auml;', '&Aring;', '&AElig;', '&Ccedil;', '&Egrave;', '&Eacute;', '&Ecirc;', '&Euml;', '&Igrave;', '&Iacute;', '&Icirc;', '&Iuml;', '&ETH;', '&Ntilde;', '&Ograve;', '&Oacute;', '&Ocirc;', '&Otilde;', '&Ouml;', '&times;', '&Oslash;', '&Ugrave;', '&Uacute;', '&Ucirc;', '&Uuml;', '&Yacute;', '&THORN;', '&szlig;', '&agrave;', '&aacute;', '&acirc;', '&atilde;', '&auml;', '&aring;', '&aelig;', '&ccedil;', '&egrave;', '&eacute;','&ecirc;', '&euml;', '&igrave;', '&iacute;', '&icirc;', '&iuml;', '&eth;', '&ntilde;', '&ograve;', '&oacute;', '&ocirc;', '&otilde;', '&ouml;', '&divide;','&oslash;', '&ugrave;', '&uacute;', '&ucirc;', '&uuml;', '&yacute;', '&thorn;', '&yuml;', '&OElig;', '&oelig;', '&sbquo;', '&bdquo;', '&hellip;', '&trade;', '&bull;', '&asymp;');

		//REPLACE VALUES
		$str = str_replace($search, $replace, $item);

		//RETURN FORMATED STRING
		return $str;
	}
    
	function file_check($tribune){

		$allowed_mime_type_arr = array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.ms-excel', 'application/octet-stream');
		
		if($tribune->getClientOriginalName() != null && ($tribune->getClientOriginalName() != '')){
			$mime = $tribune->getMimeType();
			
            //split the name up in a name and an extention
            $name = explode(".", $tribune->getClientOriginalName());
            
            // check what the extension is of the file 
            $extension = ucfirst(end($name));

			if(in_array($mime, $allowed_mime_type_arr) || $extension == 'Xls' || $extension == "Xlsx"){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	
	function temp_set_reserves($id){
		$arr_reserved = [8,10,11,12,19,20,21,22,25,27,51,59,63,65,77,78,79,80,89,90,91,92,139,151,156,162,163,173,175,211,221,222,224,234,271,283,306,315,316,317,319,320,321,322,341,344,355,356,365,366,368,369,372];
        $result = array();

        foreach($arr_reserved as $item){
            array_push($result, strval($item));
        }
        
        reservations::where("tribune_id", "=", $id)->whereIn("seat_name", $result)->update(['customer_id' => 1]);
    }
}
