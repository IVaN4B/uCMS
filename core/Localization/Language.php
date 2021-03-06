<?php
namespace uCMS\Core\Localization;
use uCMS\Core\Setting;
use uCMS\Core\Session;
use uCMS\Core\uCMS;
use uCMS\Core\Object;

class Language extends Object{
	const PATH = 'content/languages/';
	const ENGLISH = 'en_US';
	private static $instance = NULL;
	private static $list;
	private static $packages;
	private $name;
	private $langStrings;

	public static function GetCurrent(){
		if ( !is_null( self::$instance ) ){
			return self::$instance;
		}
	}

	public static function IsLoaded(){
		return !is_null( self::$instance );
	}

	public static function IsSaved(){
		$sessionLang = Session::Get('language');
		return !empty($sessionLang);
	}

	public static function GetCurrentLanguage(){
		$sessionLang = Session::Get('language');
		$storedValue = Setting::Get(Setting::LANGUAGE);
		if( empty($storedValue) ){
			if( empty($sessionLang) ) return self::ENGLISH;
			$language = $sessionLang;
		}else{
			$language = $storedValue;
		}
		return $language;
	}

	public static function Init(){
		$sessionLang = Session::Get('language');
		$storedValue = Setting::Get(Setting::LANGUAGE);
		if( !self::IsSaved() && empty($storedValue) ) return false;
		self::$instance = new self();
		Language::GetCurrent()->load();
		return true;
	}

	public function __construct($name = ""){
		if( empty($name) ){
			$this->name = self::GetCurrentLanguage();
		}else{
			$this->name = $name;
		}
		setlocale(LC_ALL, "C");
		putenv('LC_ALL=C');
	}

	public function load(){
		if( $this->name == self::ENGLISH ) return true;
		$languageFile = ABSPATH.self::PATH.$this->name.".po";

		if( !file_exists($languageFile) ){
			$result = self::DownloadLanguage($this->name);
			if( !$result ) return false;
		}
		$this->loadStrings($languageFile);
		return true;
	}

	public function loadStrings($languageFile){
		if( !file_exists($languageFile) ) return false;
		// parse headers
		$msgid = "";
		$strings = file($languageFile);
		foreach ($strings as $string) {
			$match = array();
			if( preg_match("/msgid \"(.*)\"/", $string, $match) && !empty($match[1]) ){
				$msgid = $match[1];
			}
			else if( preg_match("/msgstr \"(.*)\"/", $string, $match) && !empty($match[1]) && !empty($msgid) ){
				$this->langStrings[$msgid] = $match[1];
			}
		}
	}

	public static function GetList(){
		$path = uCMS::GetRemotePath().'languages/list.json';
		if( !empty(self::$list) ) return self::$list;
		$list = @file_get_contents($path);
		$list = json_decode($list, true);
		if( empty($list) ){
			return ["English" => "en_US"];
		}
		self::$list = $list;
		return $list;
	}

	public static function DownloadLanguage($lang){
		$list = self::GetList();
		if( !in_array($lang, $list) ) return false;
		$localpath = ABSPATH.self::PATH."$lang.po";
		$remotepath = uCMS::GetRemotePath()."languages/$lang.po";
		$exists = uCMS::IsRemoteFileExists($remotepath);

		if( $exists ){
			$result = @copy($remotepath, $localpath);
			return $result;
		}
		return false;
	}

	public function get($string, $args = array()){
		$this->insertSourceLine($string);
		$string = !empty($this->langStrings[$string]) ? $this->langStrings[$string] : $string;
		$args = func_get_args();
		$args = array_slice($args, 1);
		if( count($args) > 0 ){
			foreach ($args as $arg) {
				$patt[] = "/@s/";
			}
			$string = preg_replace($patt, $args, $string, 1);
		}
		return $string;
	}

	private function insertSourceLine($string){
		if( empty($this->langStrings[$string]) ){
			$engFile = ABSPATH.self::PATH.self::ENGLISH.'.po';
			if( !file_exists($engFile) ){
				touch($engFile);
			}else{
				$lines = file($engFile);
				if( in_array("msgid \"$string\"\n", $lines) ) return;
			}
			if( !is_writable($engFile) ) return;
			$lang = @fopen($engFile, 'a');
			if( !$lang ) return;
			$line = "msgid \"$string\"\nmsgstr \"\"\n\n";
			fwrite($lang, $line);
			fclose($lang);
		}
	}

	private function parseGettextPO(){
	}

	private function getFromPO($string){

	}
}
?>
