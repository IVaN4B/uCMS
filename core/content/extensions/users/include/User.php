<?php
namespace uCMS\Core\Extensions\Users;
use uCMS\Core\Session;
use uCMS\Core\Setting;
use uCMS\Core\Database\Query;
use uCMS\Core\ORM\Model;
use uCMS\Core\uCMS;
use uCMS\Core\Debug;
use uCMS\Core\Form;
use uCMS\Core\Page;
use uCMS\Core\Notification;
class User extends Model{
	const AVATARS_PATH = 'content/uploads/avatars';
	const LOGIN_ACTION = 'login';
	const LOGOUT_ACTION = 'logout';
	const LIST_ACTION = 'users';
	const PROFILE_ACTION = 'user';
	const NAME_REGEX = "/[^a-zA-Z0-9-_]/";
	const DEFAULT_MIN_PASSWORD = 6;
	const DEFAULT_MAX_PASSWORD = 32;
	const DEFAULT_MIN_LOGIN = 4;
	const DEFAULT_MAX_LOGIN = 20;
	const INACTIVE_STATUS = 0;
	const ACTIVE_STATUS = 1;
	const SUPERUSER_ID = 1;

	const SUCCESS = 0;
	const ERR_WRONG_PASSWORD_SIZE = 10;
	const ERR_WRONG_LOGIN_SIZE = 20;
	const ERR_WRONG_LOGIN_CHARS = 30;
	const ERR_WRONG_EMAIL = 40;

	protected static $currentUser;

	public function init(){
		$this->primaryKey('uid');
		$this->tableName('users');
		$this->belongsTo('\\uCMS\\Core\\Extensions\\Users\\Group', array('bind' => 'group'));
		$this->hasMany('\\uCMS\\Core\\Extensions\\Entries\\Entry', array('bind' => 'entries'));
		$this->hasMany('\\uCMS\\Core\\Extensions\\Users\\UserInfoField', array('bind' => 'fields'));
		$this->hasMany('\\uCMS\\Core\\Extensions\\Users\\UserInfo', array('bind' => 'info', 'key' => 'uid'));
		$this->hasMany('\\uCMS\\Core\\Extensions\\FileManager\\File', array('bind' => 'files', 'key' => 'uid'));
		$this->hasMany('\\uCMS\\Core\\Extensions\\Comments\\Comment', array('bind' => 'comments', 'key' => 'uid'));
		$this->hasMany('\\uCMS\\Core\\Session', array('bind' => 'sessions', 'key' => 'uid'));
		$this->hasMany('\\uCMS\\Core\\Extensions\\Menus\\MenuLink', array('bind' => 'links'));
	}
	
	public static function Current(){
		if ( is_null( self::$currentUser ) ){
			self::CheckAuthorization();
		}
		return self::$currentUser;
	}

	public function getDisplayName($row){
		// print name or nickname if set
		$allows = (bool)Setting::Get('allow_nicknames');
		$nickname = $this->getInfo($row, 'nickname');
		if( $allows && !empty($nickname) ){
			return $nickname;
		}
		return $row->name;
	}

	public function getInfo($row, $name){
		if( isset($row->info[$name]) ){
			return $row->info[$name];
		}
		return "";
	}

	public function isLoggedIn($row){
		if( !Session::IsAuthorized() ) return false;
		return ( !empty($row->uid) && !empty($row->name) );
	}

	public function can($row, $permission){
		if( is_object($row->group) ){
			return $row->group->hasPermission($permission);
		}
		return false;
	}

	public static function Authorize($userID, $saveCookies = false){
		if( !self::IsExists($userID) ) return false; // fail if user doesn't exists
		if( Session::IsAuthorized() ){
			if( Session::GetCurrent()->uid === intval($userID) ) return false; //fail if user already logged in
			else{
				Session::Deauthorize(); // user got wrong cache saved
			}
		}
		$hash = uCMS::GenerateHash();
		$updateSession = new Query("{sessions}");
		$updated = $saveCookies ? 0 : time();
		$updateSession->insert(
			['sid', 'uid', 'ip', 'updated', 'created'],
			[[$hash, $userID, Session::GetIPAddress(), $updated, time()]]
		)->execute();
		$lastlogin = new Query("{users}");
		$lastlogin->update(['lastlogin' => time()])->where()->condition("uid", '=', $userID)->execute();
		//save cookies if needed
		if( $saveCookies ){
			Session::SaveID($hash); //save cookie for year
		}
		Session::Authorize($hash);
		return true;
	}

	public static function Deauthorize($userID = 0){
		if( $userID == User::Current()->uid || $userID === 0 ){
			Session::Deauthorize();
			Session::Destroy();
		}
	}

	public static function ActivateUser($userID){
		//?
	}

	public static function EncryptPassword($password){
		$password = crypt($password, '$2a$10$'.UCMS_HASH_SALT);
		return $password;
	}

	public static function CheckAuthorization(){
		if( Session::IsAuthorized() ) { 
			$uid = Session::GetCurrent()->uid;
			$hash = Session::GetCurrent()->sid;
			if( $uid > 0 ){
				self::$currentUser = (new User)->find($uid); //set current user to $uid
				if( is_null(self::$currentUser) || self::$currentUser->uid == 0 ){
					Session::Deauthorize();
				}
			}
		}

		if( is_null(self::$currentUser) ){
			self::$currentUser = (new User())->emptyRow();
			self::$currentUser->uid = 0;
			self::$currentUser->gid = Group::GUEST;
		}
	}


	public static function IsExists($uid){
		$check = new Query('{users}');
		$user = $check->select('uid')->where()->condition('uid', '=', $uid)->execute();
		return !empty($user);
	}

	public static function Authenticate($login, $password, $saveCookies = false){
		// /[^a-zA-Z0-9-_@]/
		$result = false;
		$password = self::EncryptPassword($password);
		$saveCookies = (bool) $saveCookies;
		$query = new Query("{users}");
		$check = $query->select("uid")->where()->condition("name", "=", $login)->_or()->condition("email", "=", $login)->limit(1)->execute();

		if( !empty($check) ){
			$id = $check[0]['uid'];
			$result = self::Authorize($id, $saveCookies);
		}else{
			// TODO: login attempts
			Debug::Log(self::Translate("Failed authentication"), Debug::LOG_ERROR, new self());
		}

		if( !$result ){
			$error = new Notification(self::Translate("Wrong username or password"), Notification::ERROR);
			$error->add();
		}
		return $result;
	}

	public static function IsAuthenticationRequested(){
		return ( isset($_POST['login-form']) && isset($_POST['login']) && isset($_POST['password']) && isset($_POST['save_cookies']) );
	}

	public static function GetLoginForm(){
		$form = new Form("login-form", Page::FromAction(self::LOGIN_ACTION), self::Current()->tr("Log In"));
		$form->addField("login", "text", self::Current()->tr("Username:"), "", "", self::Current()->tr("username or email"));
		$form->addField("password", "password", self::Current()->tr("Password:"), "", "", self::Current()->tr("password"));
		$form->addFlag("save_cookies", self::Current()->tr("Remember Me:"));
		return $form;
	}

	public function getDate($row){	
		return uCMS::FormatTime($row->created);
	}

	public function getProfilePage($row){
		return Page::FromAction(User::PROFILE_ACTION, $row->name);
	}

	public function setGID($value){
		if($value <= Group::DEFAULT_AMOUNT ){
			return $value;
		}
		$check = (new Group())->find($value);
		if( $check != NULL ){
			return $value;
		}
	}

	public function setName($value){
		$value = preg_replace(self::NAME_REGEX, "", $value);
		return $value;
	}

	public function setPassword($value){
		$value = self::EncryptPassword($value);
		return $value;
	}

	public function setEmail($value){
		if( preg_match("/@/", $value) ){
			return $value;
		}
	}

	public function create($row){
		if( empty($row->gid) ){
			$row->gid = Group::USER;
		}
		$row->ip = Session::GetCurrent()->getIPAddress();
		$row->created = time();
		$result = parent::create($row);
		if( !$result ) return false;
		$amount = Setting::GetRow('users_amount', $this);
		if( $amount ){

		}
		Setting::Increment('users_amount', $this);
	}

	public function delete($row){
		foreach ($row->sessions as $session) {
			$session->delete();
		}
		$result = parent::delete($row);
		if( !$result ) return false;
		Setting::Decrement('users_amount', $this);
	}


	public static function CheckPasswordConstraints($password){
		$minSize = (int)Setting::Get('password_min_size');
		$maxSize = (int)Setting::Get('password_max_size');
		$size = mb_strlen($password);
		if ( $size < $minSize || $size > $maxSize ){
			return self::ERR_WRONG_PASSWORD_SIZE;
		}
		return self::SUCCESS;
	}

	public static function CheckLoginConstraints($login){
		$minSize = (int)Setting::Get('login_min_size');
		$maxSize = (int)Setting::Get('login_max_size');
		$size = mb_strlen($login);
		if ( $size < $minSize || $size > $maxSize ){
			return self::ERR_WRONG_LOGIN_SIZE;
		}
		if( preg_match(self::NAME_REGEX, $login) ){
			return self::ERR_WRONG_LOGIN_CHARS;
		}
		return self::SUCCESS;
	}

	public static function CheckEmailConstraints($email){
		if( !preg_match("/@/", $email) ){
			return self::ERR_WRONG_EMAIL;
		}
		return self::SUCCESS;
	}

	public static function GetErrorMessage($errno){
		switch ($errno) {
			case self::ERR_WRONG_PASSWORD_SIZE:
				$minSize = (int)Setting::Get('password_min_size');
				$maxSize = (int)Setting::Get('password_max_size');
				return $this->tr('Password must be at least @s characters length and not more than @s characters.', $minSize, $maxSize);
			break;

			case self::ERR_WRONG_LOGIN_SIZE:
				$minSize = (int)Setting::Get('login_min_size');
				$maxSize = (int)Setting::Get('login_max_size');
				return $this->tr('Login must be at least @s characters length and not more than @s characters.', $minSize, $maxSize);
			break;

			case self::ERR_WRONG_LOGIN_CHARS:
				return $this->tr('Login contains wrong characters.');
			break;

			case self::ERR_WRONG_EMAIL:
				return $this->tr('Invalid e-mail.');
			break;
		}
		return "";
	}
}
?>