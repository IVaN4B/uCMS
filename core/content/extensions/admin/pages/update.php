<?php

use uCMS\Core\uCMS;
use uCMS\Core\Form;
use uCMS\Core\Page;
use uCMS\Core\Notification;
use uCMS\Core\Extensions\FileManager\File;

$isPackage = false;
$packagePath = ABSPATH.File::UPLOADS_PATH.'update.zip';
if( isset($_POST['load-package']) && isset($_FILES['package']) && UCMS_DEBUG ){
	if( !isset($_FILES['package']) ) Page::Refresh();
	$filepath = $_FILES['package']['tmp_name'];
	// TODO: use File class method
	move_uploaded_file($filepath, $packagePath);
	$version = "";
	$notes = "";
	$result = uCMS::GetPackageInfo($packagePath, $version, $notes);

	switch ($result) {
		case uCMS::ERR_INVALID_PACKAGE:
			unlink($packagePath);
			$error = new Notification($this->tr("This file is not a valid μCMS package."), Notification::ERROR);
			$error->add();
			Page::Refresh();
		break;

		case uCMS::ERR_HOST_FAILURE:
			unlink($packagePath);
			$error = new Notification($this->tr("Unable to check package signature."), Notification::ERROR);
			$error->add();
			Page::Refresh();
		break;
		
		case uCMS::SUCCESS:
			$isPackage = true;
		break;
	}
}
echo '<div class="update-block">';
if( uCMS::IsUpdateAvailable() || $isPackage ){
	if( !$isPackage ){
		$version = uCMS::GetLatestVersion();
		$notes = uCMS::GetUpdateNotes();
		$this->p("<h2>Update is Available: μCMS @s</h2>", $version);
	}else{
		$this->p("<h2>Update from Package: μCMS @s</h2>", $version);
	}
	if( !empty($notes) ){
		$this->p("<h3>Please read the notes for this update:</h3><br>");
		echo "<pre>$notes</pre>";
	}
	$updateForm = new Form('update', Page::Install('check'), $this->tr('Update'));
	$updateForm->addHiddenField('action', 'update');
	if( $isPackage ){
		$updateForm->addHiddenField('package', $packagePath);
	}
	$updateForm->render();
}else{
	$this->p("<h2>Your μCMS version is up to date.</h2>");
}
$this->p("<h3>You can reinstall your current version of μCMS if something's wrong with it. This will not affect your themes or extensions.</h3>");
$reinstallForm = new Form('update', Page::Install('check'), $this->tr('Reinstall'));
$reinstallForm->addHiddenField('action', 'reinstall');
$reinstallForm->render();

if( !isset($_POST['load-package']) && !isset($_FILES['package']) && UCMS_DEBUG ){
	$packageForm = new Form('load-package', "", $this->tr('Upload package'));
	$packageForm->addField('package', 'file', $this->tr('Install update from file:'));
	$packageForm->render();
}
echo '</div>';
?>