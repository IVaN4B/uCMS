<div id="block">
<div class="inner">
	<?php
	if( $this->pageTitle() ){
		echo "<h2>".$this->pageTitle()."</h2>";
	}
	?>
	<div class="error">	
	<?php p("You don't have access to this page."); ?>
	</div>
	<a class="button" href="<?php echo $homePage; ?>"><?php p("Go To Home Page"); ?></a>
</div>
</div>