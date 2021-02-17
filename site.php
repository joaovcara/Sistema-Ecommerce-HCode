<?php

use \Hcode\Page;

#region ROTA PRINCIPAL
$app->get('/', function() {
    
	$page = new Page();

	$page->setTpl("index");

});
#endregion

?>