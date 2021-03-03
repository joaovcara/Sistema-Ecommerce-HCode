<?php

use \Hcode\Page;
use \Hcode\PageAdmin;
use \Hcode\Model\User;

#region ROTAS DE USUARIO
$app->get('/admin/users', function(){

	User::verifyLogin();

	$search = (isset($_GET['search'])) ? $_GET['search'] : '';

	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

	if($search != ''){

		$pagination = User::getPageSearch($search);

	}else{
		
		$pagination = User::getPage($page, 2);

	}	

	$pages = [];

	for ($i=0; $i < $pagination['pages']; $i++) { 
		
		array_push($pages, [
			'href'=>'/admin/users?'.http_build_query([
				'page'=>$i + 1,
				'search'=>$search
			]),
			'text'=>$i + 1
		]);

	}

	$page = new PageAdmin();

	$page->setTpl("users", array(
		'users'=>$pagination['data'],
		'search'=>$search,
		'pages'=>$pages
	));

});

//UsuarioTelaCreate
$app->get('/admin/users/create', function(){

	User::verifyLogin();

	$page = new PageAdmin();

	$page->setTpl("users-create");

});

//rota do delete
$app->get('/admin/users/:iduser/delete', function($iduser){

	User::verifyLogin();

	$user = new User();

	$user->get((int)$iduser);

	$user->delete();

	header("Location: /admin/users");

	exit;

});

//UsuarioTelaUpdate
$app->get('/admin/users/:iduser', function($iduser){

	User::verifyLogin();

	$user = new User();

	$user->get((int)$iduser);

	$page = new PageAdmin();

	$page->setTpl("users-update", array(
		"user"=>$user->getValues()
	));

});

//rota do create - para salvar
$app->post('/admin/users/create', function(){

	User::verifyLogin();

	$user = new User();

	$_POST["inadmin"] = (isset($_POST["inadmin"])) ? 1 : 0;
	$_POST["despassword"] = password_hash($_POST["despassword"], PASSWORD_DEFAULT);

	$user->setData($_POST);

	$user->save();

	header("Location: /admin/users");

	exit;

});

//rota do update - para salvar
$app->post('/admin/users/:iduser', function($iduser){

	User::verifyLogin();

	$user = new User();

	$_POST["inadmin"] = (isset($_POST["inadmin"])) ? 1 : 0;

	$user->get((int)$iduser);

	$user->setData($_POST);

	$user->update();

	header("Location: /admin/users");

	exit;

});
#endregion

#region ROTAS ESQUECI MINHA SENHA
$app->get("/admin/forgot", function(){

	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);

	$page->setTpl("forgot");

});

$app->post("/admin/forgot", function(){

	$user = User::getForgot($_POST["email"]);

	header("Location: /admin/forgot/sent");
	exit;

});

$app->get("/admin/forgot/sent", function(){
	
	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);

	$page->setTpl("forgot-sent");

});

$app->get("/admin/forgot/reset", function(){
	
	$user = User::validForgotDecrypt($_GET["code"]);

	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);

	$page->setTpl("forgot-reset", array(
		"name"=>$user["desperson"],
		"code"=>$_GET["code"]
	));

});

$app->post("/admin/forgot/reset", function(){

	$forgot = User::validForgotDecrypt($_POST["code"]);

	User::setForgotUsed($forgot["idrecovery"]);

	$user = new User();

	$user->get((int)$forgot["iduser"]);

	$password = password_hash($_POST["password"], PASSWORD_DEFAULT, [
		"cost"=>12
	]);

	$user->setPassword($password);

	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);

	$page->setTpl("forgot-reset-success");

});
#endregion

?>