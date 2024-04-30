<?php
require 'plugin-update-checker/plugin-update-checker.php'; 

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/kticoder/japonadam',
	__FILE__,
	'japonadam'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');
$myUpdateChecker->getVcsApi()->enableReleaseAssets();