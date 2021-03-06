<div id="wrapper">
<?php
	use uCMS\Core\Notification;
	if( !$isPanel ){
		$error = new Notification($this->tr('Error: Attempted to use control panel theme as site theme!'), Notification::ERROR);
		$error->add();
	}
?>
<a href="#sidebar" class="show-sidebar"></a>
<div id="sidebar">
	<?php
		echo $adminSidebar;
	?>
	<ul>
		<li><a href="<?php echo $homePage; ?>"><?php $this->p('Go to site'); ?></a></li>
	</ul>
</div>

<div id="content">
<?php
	$this->region("header");
	$this->showNotifications();
	if( $this->pageTitle() ){
		echo "<h2>".$this->pageTitle()."</h2>";
	}
	if( !empty($adminPage) ){
		include_once($adminPage);
	}
?>
</div>
<div id="footer">
	<?php
	$this->region("footer");
	echo "μCMS © 2011-".date('Y').' ';
	$this->p("Queries: @s.", $queriesCount());
	echo ' ';
	$this->p("Load time: @s seconds.", $loadTime());
	echo '<span class="ucms-version">'.$this->tr("Version: @s", $coreVersion).'</span>';
	?>
</div>
</div>