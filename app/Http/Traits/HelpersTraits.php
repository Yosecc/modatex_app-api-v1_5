<?php

namespace App\Http\Traits;
use App\Models\Client;
use Illuminate\Support\Arr;

trait HelpersTraits {

	/*
	* Converts Y & N values to true or false
	* @params $value = String Y|N
	* @return $value = Bolean true|false
	*/
	public function tBoolean($value){
		if ($value == 'Y') {
			return true;
		}else if($value = 'N'){
			return false;
		}
	}

	/*
	* Converts number values of string type into number type contained in an array
	* @params $array = array('1','2','3'...)
	* @return $array = array(1, 2, 3,...)
	*/
	public function transformStringNumberArray($array){
		if ($array) {
			$function = function($value){
				return intval($value);
			};

			return array_map($function, $array);
		}
		return [];
	}

	/*
	* Validate if there is a registered email
	* use App\Models\Client
	* @params $email = String
	* @return Boolean => if is true else false 
	*/
	public function isEmail($email){
		$client =  Client::where('email',$email)->first();
		if ($client) {
			return true;
		}
		return false;
	}

	/*
	* Generate code random 
	* @params $length = 4 default
	* @return Number 
	*/
	public function generateCode($length = 4){
		$array = [1, 2, 3, 4, 5, 6, 7, 8, 9, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 0];
		$random = Arr::random($array,$length);
		return intval(implode($random));
	}

}