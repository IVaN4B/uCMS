<?php
namespace uCMS\Core\Extensions\Users;
use uCMS\Core\Extensions\Theme;
use uCMS\Core\Extensions\ThemeHandler;
use uCMS\Core\Admin\ControlPanel;
use uCMS\Core\Database\Query;
use uCMS\Core\Block;
use uCMS\Core\Page;
use uCMS\Core\Tools;
use uCMS\Core\Settings;
use uCMS\Core\Installer;
use uCMS\Core\Session;
use uCMS\Core\Notification;
use uCMS\Core\Extensions\ExtensionHandler;
use uCMS\Core\Events\Event;
use uCMS\Core\Events\CoreEvents;
use uCMS\Core\Extensions\Menus\MenuLink;
use uCMS\Core\Extensions\Menus\Menu;
class Users extends \uCMS\Core\Extensions\Extension {

	public function onLoad(){
		$allowedActions = [User::LOGIN_ACTION, Page::INSTALL_ACTION];
		Permission::Register('manage users', tr('Manage Users'), tr('Allow user to add, edit and delete other users.'));
		User::CheckAuthorization();

		if( !in_array(Page::GetCurrent()->getAction(), $allowedActions) ){
			// If user is banned we display simple page, saying that he doesn't have access to the site.
			if( !User::Current()->can('access site') ){
				ThemeHandler::LoadTemplate('access_denied');
				exit;
			}
	
			if( !User::Current()->can('access site in maintenance mode') && !ControlPanel::IsActive() ){
				if( (bool)Settings::Get('ucms_maintenance') ){
					ThemeHandler::LoadTemplate('maintenance');
					exit;
				}
			}
		}
	}

	public function onAction($action){
		switch ($action) {
			case 'login':
				if( !User::Current()->isLoggedIn() ){
					if( User::IsAuthenticationRequested() ){
						$success = User::Authenticate($_POST['login'], $_POST['password'], $_POST['save_cookies']);
						if( $success ){
							Page::GoBack();
						}
						Page::Refresh();
					}else{
						Theme::GetCurrent()->setPageTitle(tr("Login"));
					}
				}else{
					Page::GoBack();
				}
			break;

			case 'logout':
				User::Deauthorize();
				Page::GoBack();
			break;
			
			case 'user':
				$userName = Page::GetCurrent()->getActionValue();
				$user = (new User())->find(['name' => $userName, 'limit' => 1]);
				if( empty($user) ){
					// If user is not found, redirect to current user page
					if( User::Current()->isLoggedIn() ){
						$profilePage = Page::FromAction(User::PROFILE_ACTION, User::Current()->name);
					}else{
						// If user is not authorized we redirect him to the home page.
						$profilePage = Page::Home();
					}
					$profilePage->go();
				}
				Theme::GetCurrent()->setTitle($user->getDisplayName());
			break;
		}
	}

	public function onAdminAction($action){
		$title = tr("Users");
		switch ($action) {
			case 'users/groups':
				$title = tr("Groups");
			break;
		}
		ControlPanel::SetTitle($title);
	}

	private function addSuperUser($email, $login, $password){
		$admin = (new User())->empty();
		$admin->uid = User::SUPERUSER_ID;
		$admin->gid = Group::ADMINISTRATOR;
		$admin->email = $email;
		$admin->name = $login;
		$admin->password = $password;
		$admin->status = User::ACTIVE_STATUS;
		$admin->create();
		User::Authorize($admin->uid, true);
		Settings::Update(Settings::SITE_AUTHOR, $admin->name);
	}

	private function addBlocks(){
		$login = (new Block())->empty();
		$login->name = "login-form";
		$login->region = "content";
		$login->theme = Theme::DEFAULT_THEME;
		$login->visibility = Block::SHOW_LISTED;
		$login->actions = User::LOGIN_ACTION;
		$login->status = Block::ENABLED;
		$login->create();
		$card = (new Block())->empty();
		$card->name = "user-card";
		$card->region = "right-sidebar";
		$card->theme = Theme::DEFAULT_THEME;
		$card->status = Block::ENABLED;
		$card->create();

		$profile = (new Block())->empty();
		$profile->name = "user-profile";
		$profile->region = "content";
		$profile->theme = Theme::DEFAULT_THEME;
		$profile->status = Block::ENABLED;
		$profile->visibility = Block::SHOW_LISTED;
		$profile->actions = User::PROFILE_ACTION;
		$profile->create();
	}

	private function addMenu(){
		$check = (new Menu())->find('user-menu');
		if( $check == NULL ){
			$userMenu = (new Menu())->empty();
			$userMenu->menu = 'user-menu';
			$userMenu->title = 'User Menu';
			$userMenu->description = 'User actions, displayed at the user card in sidebar.';
			$userMenu->create();

			$profileLink  = (new MenuLink())->empty();
			$userlistLink = (new MenuLink())->empty();
			$logoutLink   = (new MenuLink())->empty();
			$cpanelLink   = (new MenuLink())->empty();

			$profileLink->menu = $userlistLink->menu =
			$logoutLink->menu = $cpanelLink->menu = 'user-menu';

			$profileLink->status = $userlistLink->status =
			$logoutLink->status = $cpanelLink->status = 1;

			$profileLink->link  = User::PROFILE_ACTION;
			$userlistLink->link = User::LIST_ACTION;
			$logoutLink->link   = User::LOGOUT_ACTION;
			$cpanelLink->link   = ControlPanel::ACTION;

			$profileLink->title  = "Profile";
			$userlistLink->title = "Users";
			$logoutLink->title   = "Logout";
			$cpanelLink->title   = "Control Panel";
			$cpanelLink->permission = "access control panel";

			$profileLink->create();
			$userlistLink->create();
			$logoutLink->create();
			$cpanelLink->create();
		}
	}

	protected function checkStage(){
		$this->addBlocks();
		$this->addMenu();
		return parent::checkStage();
	}

	protected function updateStage(){

	}

	protected function prepareStage(){
		$isPosted = (isset($_POST['stage']) && $_POST['stage'] === Installer::GetInstance()->getCurrentStage());
		if( !$isPosted ){
			Installer::GetInstance()->setTitle(tr('Creating an Administrator'));
			return parent::prepareStage();
		}

		if( $isPosted ){
			$notAllFields = false;
			$required = ['email', 'login', 'password', 'password_check'];
			$errors = [];
			foreach ($required as $field) {
				if( empty($_POST[$field]) ){
					$notAllFields = true;
					break;
				}
			}
			if( $notAllFields ){
				$error = new Notification(tr('Error: All fields are required.'), Notification::ERROR);
				$error->add();
				Page::Refresh();
			}
			if( $_POST['password'] != $_POST['password_check'] ){
				$error = new Notification(tr('Error: Passwords do not match.'), Notification::ERROR);
				$error->add();
				Page::Refresh();
			}
			$errors[] = User::CheckLoginConstraints($_POST['login']);
			$errors[] = User::CheckPasswordConstraints($_POST['password']);
			$errors[] = User::CheckEmailConstraints($_POST['email']);
			$errorMessages = [];
			foreach ($errors as $error) {
				$message = User::GetErrorMessage($error);
				if( !empty($message) ){
					$errorMessages[] = tr($message);
				}
			}
			if( !empty($errorMessages) ){
				$messages = implode('<br>', $errorMessages);
				$error = new Notification(tr('Unable to register user due to errors below:<br> @s', $messages), Notification::ERROR);
				$error->add();
				Page::Refresh();
			}
			$this->addSuperUser($_POST['email'], $_POST['login'], $_POST['password']);
			
			return ExtensionHandler::DONE_INSTALL;
		}
	}

	protected function printStage(){
		$this->addAdminFormStage();
	}

	protected function getSchemas(){
		$schemas['groups']  = [
			'fields' => [
				'gid' => [
					'type' => 'serial',
					'not null' => true
				],
				'name' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'position' => [
					'type' => 'int',
					'default' => 0
				]
			],
			'primary key' => 'gid',
			'unique keys' => ['name' => 'name']
		];

		$schemas['group_permissions']  = [
			'fields' => [
				'gid' => [
					'type' => 'int',
					'not null' => true
				],
				'name' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'owner' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				]
			],
			'primary key' => ['gid', 'name']
		];
		
		$schemas['users']  = [
			'fields' => [
				'uid' => [
					'type' => 'serial',
					'not null' => true
				],
				'name' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'password' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'email' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'status' => [
					'type' => 'int',
					'size', 'tiny',
					'not null' => true
				],
				'gid' => [
					'type' => 'int',
					'not null' => true
				],
				'theme' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'avatar' => [
					'type' => 'int',
					'not null' => true
				],
				'language' => [
					'type' => 'varchar',
					'size' => 'small',
					'not null' => true
				],
				'timezone' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'ip' => [
					'type' => 'varchar',
					'not null' => true
				],
				'created' => [
					'type' => 'int',
					'default' => time()
				],
				'lastlogin' => [
					'type' => 'int',
					'default' => time()
				],
				'visited' => [
					'type' => 'int',
					'default' => time()
				]
			],
			'primary key' => 'uid'
		];

		$schemas['user_info'] = [
			'fields' => [
				'uid' => [
					'type' => 'int',
					'not null' => true
				],
				'name' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'value' => [
					'type' => 'text',
					'size' => 'big'
				],
				'changed' => [
					'type' => 'int',
					'default' => time()
				]
			],
			'primary key' => ['uid', 'name']
		];

		$schemas['user_fields'] = [
			'fields' => [
				'name' => [
					'type' => 'varchar',
					'size' => 'big',
					'not null' => true
				],
				'title' => [
					'type' => 'varchar',
					'size' => 'big'
				],
				'description' => [
					'type' => 'text',
					'size' => 'big'
				],
				'type' => [
					'type' => 'varchar',
					'default' => 'string'
				]
			],
			'primary key' => ['name']
		];
		return $schemas;
	}

	protected function fillTable($table){
		switch($table){
			case 'users':
				// Request form
				return true;
			break;

			case 'groups':
				$this->addDefaultGroups();
				return false;
			break;

			case 'user_fields':
				$this->addDefaultFields();
			break;
		}
		return false;
	}


	private function getDefaultGroups(){
		$defaultGroups = [
			[Group::ADMINISTRATOR, 'Administrator'],
			[Group::MODERATOR,     'Moderator'    ],
			[Group::TRUSTED,       'Trusted'      ],
			[Group::USER,          'User'         ],
			[Group::BANNED,        'Banned'       ],
			[Group::GUEST,         'Guest'        ]
		];
		return $defaultGroups;
	}

	private function addDefaultFields(){
		$firstname = (new UserInfoField())->empty();
		$surname = (new UserInfoField())->empty();
		$firstname->name = 'firstname';
		$firstname->title = 'First Name';
		$firstname->description = 'User\'s first name.';

		$surname->name = 'surname';
		$surname->title = 'Surname';
		$surname->description = 'User\'s surname.';

		$firstname->create();
		$surname->create();
	}

	private function addDefaultGroups(){
		$defaultGroups = $this->getDefaultGroups();
		$query = new Query('{groups}');
		$query->insert(['gid', 'name'], $defaultGroups, true)->execute();
	}

	private function addAdminFormStage(){
		echo '<h3>'.tr('Let\'s create you an administrator!').'</h3>';
		$domain = preg_replace("/([a-zA-Z]\:\/\/)/i", '', Settings::Get(Settings::SITE_DOMAIN));
		$form = Installer::GetInstance()->installForm('user-form');
		$form->addField('email', 'email', tr('E-mail:'), tr('Set e-mail address for all system notifications.'), "admin@$domain", tr('e-mail'));
		$form->addField('login', 'text', tr('Login:'), tr('Set login for administrator.'), "admin", tr('login'));
		$form->addField('password', 'password', tr('Password:'), tr("Set password for administrator:<br> choose wisely, security of the site depends on it."), '', tr('password'));
		$form->addField('password_check', 'password', tr('Confirm Password:'), '', '', tr('password'));
		$form->render();
	}
}
?>