<?php

namespace EntityGenerator\Helper;

class HelperFunctions{
	/* Capitalize the String name */
	public static function studlyCaps($string){
		return implode('',array_map('ucfirst',explode('_',strtolower($string))));
	}

	/* Capitalize the String name */
	public static function camelCase($string){
		$stringArray = explode('_',strtolower($string));
		$firstWord = (isset($stringArray)) ? $stringArray[0] : '';
		unset($stringArray[0]);
		return $firstWord.implode('',array_map('ucfirst',$stringArray));
	}
	/*
	public static function lowers($string){
		$camelFirst = $this->camelCase($string);  //camelize first, then lower the first chars
		$lowers = strToLower(substr($camelFirst,0,1)).substr($camelFirst,1,strlen($camelFirst));
		return $lowers;
	}*/
	
	/* alternate attribute type */
	public static function altDataType($type){
		$altType =$type;
		if(substr($type,0,3)=='int'  ){
			$altType= 'int';
		} 
		if (substr($type,0,7)=='varchar'){
			$altType= 'string';
		}
		if($type=='char(1)'){
			$altType='string';
		}
		if($type=='bigint(18)'){
			$altType='int';
		}
		if($type=='decimal(25,2)'){
			$altType='float';
		}
		if($type=='datetime'){
			$altType= '\DateTime';
		}
		return $altType;
	}
	
	/*  get string maxLength */
	public static function getMaxStringLength($type){
		//if there are no brackets found 
		if(!strpos($type,'(') && !strpos($type,')')) return '';
		$maxLength=substr($type,strpos($type,'(')+1,strpos($type,')')-strpos($type,'(')-1);
		return $maxLength;
	}
}