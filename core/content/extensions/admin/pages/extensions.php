<?php
use uCMS\Core\Admin\ManagePage;
use uCMS\Core\Admin\ManageTable;
use uCMS\Core\Database\Query;
use uCMS\Core\Admin\ControlPanel;
use uCMS\Core\Extensions\Extension;
use uCMS\Core\Page;
$extensionsPage = new ManagePage();

$extensionsPage->addAction('add',     'manage extensions',  'Extension::add');
$extensionsPage->addAction('delete',  'manage extensions',  'Extension::delete');
$extensionsPage->addAction('enable',  'manage extensions',  'Extension::enable');
$extensionsPage->addAction('disable', 'manage extensions',  'Extension::disable');

$extensionsPage->doActions();

$extensionsTable = new ManageTable();
$extensions = Extension::GetAll();

$siteLink = Page::Home();
$extensionsTable->addSelectColumn('manage extensions');
$extensionsTable->addColumn(tr('Extension'), true, 'manage extensions', '20%', true);
$extensionsTable->addColumn(tr('Description'), true, 'manage extensions', 0, true );
foreach ($extensions as $extension) {
	$dependencies = "";
	$extensionObject = Extension::Get($extension);
	if( empty($extensionObject) ){
		continue;
	}
	if( is_array($extensionObject->getDependenciesList()) ){
		foreach ($extensionObject->getDependenciesList() as $dependency) {
			if( Extension::IsLoaded($dependency) ){ //?
				$dependencies[] = Extension::Get($dependency)->getInfo('displayname');
			}
		}
		$dependencies = implode(", ", $dependencies);
	}
	$status = Extension::IsLoaded($extension);
	$style = $status ? "enabled" : "";
	$displayname = $extensionObject->getInfo('displayname');
	$description = $extensionObject->getInfo('description');
	$extensionsTable->setInfo('idKey', $extension);
	$extensionsTable->setInfo('status', $status);
	$version = $extensionObject->getVersion();
	$author = $extensionObject->getInfo('author');
	$site = $extensionObject->getInfo('site');
	$dependenciesMessage = !empty($dependencies) ? "<br>Depends on: @s" : "";
	$extensionsTable->addRow( 
		array(
			"$displayname<br><div class=\"manage-actions\">".
			$extensionsTable->manageButtons()."</div>",
			tr($description).tr('<br><br>Version: @s | Author: @s | Site: <a href="@s">@s</a>',
			$version, $author, $site, $site, $dependencies).tr($dependenciesMessage, $dependencies),

		),
		$style
	);
}

$extensionsTable->printTable();
?>