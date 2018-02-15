<?php
	
define('USER_LOGIN', 'kryukov@fondo.ru');
define('USER_HASH', '5e9f0151caf88a41b54f356f85bae171');
define('SUBDOMAIN', 'new5a83f63e39b4f');

$auth['link'] = getLinkForAuth();
$auth['data'] = ['USER_LOGIN' => USER_LOGIN, 'USER_HASH' => USER_HASH];
$auth['response'] = cURL($auth['link'], $auth['data'])['response'];

$contact['link'] = getLinkForContacts();
$contact['data'] = prepareDataForAddContactInAmoCRM($auth['response']['user']);
$contact['response'] = cURL($contact['link'], $contact['data']);

$lead['link'] = getLinkForLeads();
$lead['data'] = prepareDataForAddLeadsInAmoCRM($auth['response']['user'], $contact['response']['_embedded']['items']);
$lead['response'] = cURL($lead['link'], $lead['data']);

$task['link'] = getLinkForLeadTasks();
$task['data'] = prepareDataForAddTasksInAmoCRM($auth['response']['user'], $lead['response']['_embedded']['items'][0]['id']);
$task['response'] = cURL($task['link'], $task['data']);

function getLinkForAuth(){
	return 'https://' . SUBDOMAIN . '.amocrm.ru/private/api/auth.php?type=json';
}

function getLinkForContacts(){
	return 'https://' . SUBDOMAIN . '.amocrm.ru/api/v2/contacts';
}

function getLinkForLeads(){
	return 'https://' . SUBDOMAIN . '.amocrm.ru/api/v2/leads';
}

function getLinkForLeadTasks(){
	return 'https://' . SUBDOMAIN . '.amocrm.ru/api/v2/tasks';
}

/**
 * cURL function.
 *
 * @param  string  $link
 *         array   $data
 * @return array   response from server
 */
function cURL($link, $data){
	$errors = [
	  	301=>'Moved permanently',
	  	400=>'Bad request',
	  	401=>'Unauthorized',
	  	403=>'Forbidden',
	  	404=>'Not found',
	  	500=>'Internal server error',
	  	502=>'Bad gateway',
	  	503=>'Service unavailable'
	];

	$curl=curl_init();

	curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
	curl_setopt($curl,CURLOPT_URL,$link);
	curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
	curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
	curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
	curl_setopt($curl,CURLOPT_HEADER,false);
	curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); 
	curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); 
	curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
	curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);

	$out=curl_exec($curl); 
	$code=curl_getinfo($curl,CURLINFO_HTTP_CODE); 

	if($code!=200 && $code!=204){
	    throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error',$code);
	};


	curl_close($curl); 

	return json_decode($out, true);
}

/**
 * Create contact in amoCRM. https://www.amocrm.ru/developers/content/api/contacts
 *
 * @param       array      $responsible_user
 *              json       $users_list
 *
 * @var         array      $user 
 * @user(array) string 	   'name'(require), 'company_name', 'tags', 'leads_id', 'customers_id', 'company_id'
 *              int        'responsible_user_id', 'created_by'
 *              array      'custom_fields'
 *              timestamp  'created_at', 'updated_at'
 *
 * @return      array      $contacts
 */
function prepareDataForAddContactInAmoCRM($responsible_user, $users_list = null){
	$contacts['add'] = [];

	if(isset($users_list)){ //Быстрый метод, если запрос с формы сайта корректно составлен в виде массива пользователей JSON 
		$users_list = json_decode($users_list, true);
		foreach($users_list as $user){   
			$user['responsible_user_id'] = intval($responsible_user['id']);
			$user['created_by'] = intval($responsible_user['id']);

			$contacts['add'][] = $user;
		}
	} else if(isset($_POST['user'])){ 
		$user = json_decode($_POST['user'], true);
		$user['responsible_user_id'] = intval($responsible_user['id']);
		$user['created_by'] = intval($responsible_user['id']);

		$contacts['add'][] = $user;
	} else { 
		$user['name'] = isset($_POST['user_name']) ? $_POST['user_name'] : 'Вася Петрович';
		$user['responsible_user_id'] = intval($responsible_user['id']);
		$user['created_by'] = intval($responsible_user['id']);

		if( isset($_POST['user_created_at']) ){ $user['created_at'] = $_POST['user_created_at']; }
		if( isset($_POST['user_updated_at']) ){ $user['updated_at'] = $_POST['user_updated_at']; }
		if( isset($_POST['user_company_name']) ){ $user['company_name'] = $_POST['user_company_name']; }
		if( isset($_POST['user_tags']) ){ $user['tags'] = $_POST['user_tags']; }
		if( isset($_POST['user_leads_id']) ){ $user['leads_id'] = $_POST['user_leads_id']; }
		if( isset($_POST['user_customers_id']) ){ $user['customers_id'] = $_POST['user_customers_id']; }
		if( isset($_POST['user_company_id']) ){ $user['company_id'] = $_POST['user_company_id']; }
		if( isset($_POST['user_custom_fields']) ){ $user['custom_fields'] = json_decode($_POST['user_custom_fields'], true); }

		$contacts['add'][] = $user;
	}


	return $contacts;
}

/**
 * Create leads in amoCRM. https://www.amocrm.ru/developers/content/api/leads
 *
 * @param       array      $responsible_user
 *              json       $leads_list
 *
 * @var         array      $lead 
 * @lead(array) string 	   'name'(require), 'tags', 'company_id'
 *              int        'responsible_user_id', 'created_by', 'sale', 'pipeline_id', 'status_id'
 *              int/array  'contacts_id'
 *              array      'custom_fields'
 *              timestamp  'created_at', 'updated_at'
 *
 * @return      array      $leads
 */
function prepareDataForAddLeadsInAmoCRM($responsible_user, $contacts = null, $leads_list = null){
	$leads['add'] = [];

	if(isset($leads_list)){ //Быстрый метод, если запрос с формы сайта корректно составлен в виде массива пользователей JSON 
		$leads_list = json_decode($leads_list, true);
		foreach($leads_list as $lead){   
			$lead['responsible_user_id'] = intval($responsible_user['id']);
			$leads['add'][] = $lead;
		}
	} else if(isset($_POST['lead'])){ 
		$lead = json_decode($_POST['lead'], true);
		$lead['responsible_user_id'] = intval($responsible_user['id']);

		if(isset($contacts)){
			foreach ($contacts as $item) {
				if(!isset($lead['contacts_id'])){ $lead['contacts_id'] = []; }
				$lead['contacts_id'][] = $item['id'];
			}
		}

		$leads['add'][] = $lead;
	} else { 
		$lead['name'] = isset($_POST['lead_name']) ? $_POST['lead_name'] : 'Тестовая сделка';
		$lead['responsible_user_id'] = intval($responsible_user['id']);

		if( isset($_POST['lead_created_at']) ){ $lead['created_at'] = $_POST['lead_created_at']; }
		if( isset($_POST['lead_updated_at']) ){ $lead['updated_at'] = $_POST['lead_updated_at']; }
		if( isset($_POST['lead_sale']) ){ $lead['sale'] = $_POST['lead_sale']; }
		if( isset($_POST['lead_status_id']) ){ $lead['status_id'] = $_POST['lead_status_id']; }
		if( isset($_POST['lead_tags']) ){ $lead['tags'] = $_POST['lead_tags']; }
		if( isset($_POST['lead_pipeline_id']) ){ $lead['pipeline_id'] = $_POST['lead_pipeline_id']; }
		if( isset($_POST['lead_contacts_id']) ){ $lead['contacts_id'] = $_POST['lead_contacts_id']; }
		if( isset($_POST['lead_company_id']) ){ $lead['company_id'] = $_POST['lead_company_id']; }
		if( isset($_POST['lead_custom_fields']) ){ $lead['custom_fields'] = json_decode($_POST['lead_custom_fields'], true); }

		if(isset($contacts)){
			foreach ($contacts as $item) {
				if(!isset($lead['contacts_id'])){ $lead['contacts_id'] = []; }
				$lead['contacts_id'][] = $item['id'];
			}
		}
 
		$leads['add'][] = $lead;
	}


	return $leads;
}

/**
 * Create tasks in amoCRM. https://www.amocrm.ru/developers/content/api/tasks
 *
 * @param       array      $responsible_user
 *              json       $tasks_list
 *
 * @var         array      $task 
 * @task(array) string 	   'text'
 *              int        'responsible_user_id', 'element_id', 'element_type', 'task_type', 'created_by'
 *              bool       'is_completed'
 *              array      'custom_fields'
 *              timestamp  'created_at', 'updated_at'
 *
 * @return      array      $tasks
 */
function prepareDataForAddTasksInAmoCRM($responsible_user, $lead_id = null, $tasks_list = null){
	$tasks['add'] = [];

	if(isset($tasks_list)){ //Быстрый метод, если запрос с формы сайта корректно составлен в виде массива пользователей JSON 
		$tasks_list = json_decode($tasks_list, true);
		foreach($tasks_list as $task){   
			$task['responsible_user_id'] = intval($responsible_user['id']);
			$task['created_by'] = intval($responsible_user['id']);
			$tasks['add'][] = $task;
		}
	} else if(isset($_POST['task'])){ 
		$task = json_decode($_POST['task'], true);
		$task['responsible_user_id'] = intval($responsible_user['id']);
		$task['created_by'] = intval($responsible_user['id']);
		$task['element_type'] = 2;
		$task['element_id'] = intval($lead_id);

		$tasks['add'][] = $task;
	} else { 
		$task['text'] = isset($_POST['task_text']) ? $_POST['task_text'] : 'Тестовая задача к сделке';
		$task['responsible_user_id'] = intval($responsible_user['id']);
		$task['created_by'] = intval($responsible_user['id']);
		$task['element_type'] = 2;
		$task['element_id'] = intval($lead_id);

		if( isset($_POST['task_created_at']) ){ $task['created_at'] = $_POST['task_created_at']; }
		if( isset($_POST['task_updated_at']) ){ $task['updated_at'] = $_POST['task_updated_at']; }
		if( isset($_POST['task_task_type']) ){ $task['task_type'] = $_POST['task_task_type']; }
		if( isset($_POST['task_is_completed']) ){ $task['is_completed'] = $_POST['task_is_completed']; }
		if( isset($_POST['task_custom_fields']) ){ $task['custom_fields'] = json_decode($_POST['task_custom_fields'], true); }

 
		$tasks['add'][] = $task;
	}


	return $tasks;
}