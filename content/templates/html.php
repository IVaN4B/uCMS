<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta charset="utf-8">
<?php
$this->loadStyles();
$this->loadScripts();
?>
<title><?php echo (UCMS_DEBUG ? tr("[DEBUG] ") : "").$this->getTitle(); ?></title>
</head>
<body>