<?php
use uCMS\Core\Admin\ManagePage;
use uCMS\Core\Admin\ManageTable;
use uCMS\Core\Database\Query;
use uCMS\Core\Admin\ControlPanel;
use uCMS\Core\Extensions\Users\Permission;
use uCMS\Core\Extensions\Users\Group;
use uCMS\Core\Extensions\Users\User;
use uCMS\Core\Extensions\ExtensionHandler;
use uCMS\Core\Setting;
$groupsPage = new ManagePage();
$groupsTable = new ManageTable();

$limit = Setting::Get('per_page');
$groups = (new Group())->find(array('limit' => $limit));
$groupsTable->addSelectColumn('manage users');
$groupsTable->setInfo("amount", Setting::Get('groups_amount'));

$groupsTable->addColumn($this->tr('Name'), true, 'manage users', 0, true);
$groupsTable->addColumn($this->tr('Permissions'), true, 'manage users', '40%', true);
$groupsTable->addColumn($this->tr('Users count'), true, 'manage users');
foreach ($groups as $group) {
	$groupsTable->setInfo('idKey', $group->gid);
	$permissions = array();
	// ob_start();
	$perms = $group->permissions;
	usort($perms, 'cmp');
	$prevOwner = "";
	// TODO: Optimize
	$c = 0;
	foreach ($perms as $permission) {
		$info = $permission->getInfo();
		if( empty($info) ){
			$data = $permission->name;
		}else{
			$data = $info['title'].'<br>'.$info['description'];
		}
		if( $prevOwner != $permission->owner ){
			$owner = $permission->owner;
			if ( $owner === 'core' ){
				$owner = $this->tr('General');
			}
			$extension = ExtensionHandler::Get($permission->owner);
			if( !empty($extension) ){
				$owner = $this->tr($extension->getInfo('displayname'));
			}
			$permissions[$c] = ($c > 0 ? "</div>" : "")."\n<div class=\"permissions-block\"><b>$owner</b>:<br>$data";
		}else{
			$permissions[$c] = $data; 
		}
		$prevOwner = $permission->owner;
		if ( $c+1 == count($perms) ){
			$permissions[$c] .= "</div>";
		}
		$c++;
	}

	$groupsTable->addRow(
		array(
			$this->tr($group->name)."<br><div class=\"manage-actions\">".$groupsTable->manageButtons(array(
				'Edit' => 'edit',
				'Delete' => 'delete'))."</div>",
			implode("<br>", $permissions),
			(new User())->count(array('gid' => $group->gid))
		)
	);

}
$groupsTable->printTable();

function cmp($a, $b){
	if( $a->owner === 'core') return -1;
	if( $b->owner === 'core') return 1;
    return strcmp($a->owner, $b->owner);
}
?>