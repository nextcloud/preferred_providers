<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Preferred_Providers\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
	'ocs' => [
		['root' => '/account', 'name' => 'Account#requestAccount', 'url' => '/request/{token}', 'verb' => 'POST']
	],
	'routes' => [
		['name' => 'mail#confirm_mail_address', 'url' => '/login/confirm/{email}/{token}', 'verb' => 'GET'],
		['name' => 'password#set_password', 'url' => '/password/set/{email}/{token}', 'verb' => 'GET'],
		['name' => 'password#submit_password', 'url' => '/password/submit/{token}', 'verb' => 'POST'],
	]
];
