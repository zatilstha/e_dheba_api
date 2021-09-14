<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use App\Models\Common\RequestLog;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Response\QrCodeResponse;
use App\Models\Common\AdminService;
use App\Models\Common\Setting;
use Auth;
use Illuminate\Support\Facades\Crypt; 
use Log;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;
use Illuminate\Support\Facades\Mail;

class Helper {

	public static function getUsername(Request $request) {
		
		$username = "";

		if(isset($request->mobile)) {
			$username = 'mobile';
		} else if(isset($request->email)) {
			$username = 'email';
		}

		return $username;
	}

	public static function dateFormat($company_id=null){
		$setting = Setting::where('company_id', 1)->first();
		$settings = json_decode(json_encode($setting->settings_data));
		$siteConfig = isset($settings->site->date_format) ? $settings->site->date_format:0 ;
		if($siteConfig=='1'){
          return "d-m-Y H:i:s";
		}else{
          return "d-m-Y g:i A";
		}
	}

	public static function currencyFormat($value = '',$symbol='')
	{
		if($value == ""){
			return $symbol.number_format(0, 2, '.', '');
		} else {
			return $symbol.number_format($value, 2, '.', '');
		}
	}

	public static function decimalRoundOff($value)
	{
		return number_format($value, 2, '.', '');
	}

	public static function qrCode($data, $file, $company_id, $path = 'qr_code/', $size = 500, $margin = 10) {
		//return true;
		$qrCode = new QrCode();
        $qrCode->setText($data);
        $qrCode->setSize($size);
        $qrCode->setWriterByName('png');
        $qrCode->setMargin($margin);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevel(ErrorCorrectionLevel::HIGH));

        $qrCode->setRoundBlockSize(true);
        $qrCode->setValidateResult(false);
        $qrCode->setWriterOptions(['exclude_xml_declaration' => true]);
        $filePath = 'app/public/'.$company_id.'/'.$path;
		
		$filePath = 'app/public/'.$company_id.'/'.$path;

        if (!file_exists( app()->basePath('storage/'.$filePath )  )) {
            mkdir(app()->basePath('storage/'.$filePath ), 0777, true);
        }

        $qrCode->writeFile( app()->basePath('storage/'.$filePath ).$file);

        return url().'/storage/'.$company_id.'/'.$path.$file; 

	}

	public static function upload_file($picture, $path, $file = null, $company_id = null)
	{
		if($file == null) {
			$file_name = time();
			$file_name .= rand();
			$file_name = sha1($file_name);

			$file = $file_name.'.'.$picture->getClientOriginalExtension();
		}
		
		if(!empty(Auth::user())){          
            $company_id = Auth::user()->company_id;
        }

		$path = $company_id.'/'.$path;
		
		if (!file_exists( app()->basePath('storage/app/public/'.$path )  )) {
            mkdir(app()->basePath('storage/app/public/'.$path ), 0777, true);
        }

        return url().'/storage/'.$picture->storeAs($path, $file);
	}

	public static function upload_providerfile($picture, $path, $file = null, $company_id = null)
	{
		if($file == null) {
			$file_name = time();
			$file_name .= rand();
			$file_name = sha1($file_name);

			$file = $file_name.'.'.$picture->getClientOriginalExtension();
		}

		$path = ( ($company_id == null) ? Auth::guard('provider')->user()->company_id : $company_id ) .'/'.$path;
		
		if (!file_exists( app()->basePath('storage/app/public/'.$path )  )) {
            mkdir(app()->basePath('storage/app/public/'.$path ), 0777, true);
        }

        return url().'/storage/'.$picture->storeAs($path, $file);
	}

	public static function getGuard(){
	    if(Auth::guard('admin')->check()) {
	    	return strtoupper("admin");
	    } else if(Auth::guard('provider')->check()) {
	    	return strtoupper("provider");
	    } else if(Auth::guard('user')->check()) {
	    	return strtoupper("user");
	    } else if(Auth::guard('shop')->check()){
	    	return strtoupper("shop");
	    }
	}

	public static function CBSselflogin($data)
	{	
		$url=@$data['cbs_url'];
		$username=@$data['username'];
		$password=@$data['password'];
		$self_login_url=$url."/fineract-provider/api/v1/self/authentication?username=".$username."&password=".$password;
		\Log::info("CBS Login URL-------------".$self_login_url);
		$auth = base64_encode($username.":".$password);
		\Log::info("Basecode-------");
		\Log::info($auth);
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $self_login_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  //CURLOPT_POSTFIELDS => "",
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$auth,
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 4ce0338a-0ccf-4474-924d-d3d9042a1bb4",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$response_return = json_decode($response,true);
		curl_close($curl);
		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}

		\Log::info("CBS Login-----------");
		\Log::info($response_return);
	    return $response_return;
	}

	public static function CBSUserselflogin($data)
	{	
		$url=@$data['cbs_url'];
		$username=@$data['username'];
		$password=@$data['password'];
		$self_login_url=$url."/fineract-provider/api/v1/self/authentication?username=".$username."&password=".$password;
		\Log::info("CBS Login URL-------------".$self_login_url);
		\Log::info("CBS Auth Token------------".$data['base64EncodedAuthenticationKey']);
		
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $self_login_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  //CURLOPT_POSTFIELDS => "",
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 4ce0338a-0ccf-4474-924d-d3d9042a1bb4",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$response_return = json_decode($response,true);
		curl_close($curl);
		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}

		\Log::info("CBS Login-----------");
		\Log::info($response_return);
	    return $response_return;
	}

	public static function CBSwallet($data)
	{
		$url=$data['cbs_url'];
		$store_url=$url."/fineract-provider/api/v1/accounttransfers";
		\Log::info("Store Url--------");
		\Log::info($store_url);
		$activationDate = date("d M Y");
		\Log::info("Date-------".$activationDate);
		$payable_amount=@$data['payable_amount'];

		$fromClientId = @$data['fromClientId'];
		$fromAccountId = @$data['fromAccountId'];
		$toClientId = @$data['toClientId'];//6005;//2155;//1847;
		$toAccountId = @$data['toAccountId'];//4542;//2057;//1755;
		
    	$postfield=[
			     "fromOfficeId" => "1",
				 "fromClientId" => $fromClientId,//"1980",
				 "fromAccountType" => "2",
				 "fromAccountId" => $fromAccountId,//"1902",
				 "toOfficeId" => "1",
				 "toClientId" => $toClientId,//"1788",
				 "toAccountType" => "2",
				 "toAccountId" => $toAccountId,//"1609",
				 "dateFormat" => "dd MMMM yyyy",
				 "locale" => "en",
				 "transferDate" => $activationDate,//"03 FEB 2020"
				 "transferAmount" => $payable_amount,
				 "transferDescription" => "Instant Referral"		   
		];

		\Log::info("Request Body-------------");
		\Log::info($postfield);
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $store_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => json_encode($postfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			\Log::info($err);
		  return $return= "cURL Error #:" . $err;
		}

		\Log::info("No error----");

		\Log::info($response);

		return $wallet = json_decode($response,true);;
	}	

	public static function CBSauthentication($data)
	{
		$url=$data['cbs_url'];
		$username=$data['cbs_username'];
		$password=$data['cbs_passwprd'];
		/*$url='https://104.211.112.202';
		$username='indiateam';
		$password='123456789';*/
		$auth_url=$url."/fineract-provider/api/v1/authentication?username=".$username."&password=".$password;

		\Log::info("CBS auth url---------".$auth_url);

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $auth_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_HTTPHEADER => array(
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 0129f4c4-9fc2-447f-8e05-b25376ad405b",
		    "cache-control: no-cache",
		    "content-type: application/json"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$authToken = json_decode($response);
		curl_close($curl);
		if ($err) {
			\Log::info($err);
		  return $return= "cURL Error #:" . $err;
		}

		\Log::info("CBS AUthentication Log-------");
	    return $authToken;
	}
	public static function CBScreateclient($data)
	{
		$url=$data['cbs_url'];
		$store_url=$url."/fineract-provider/api/v1/clients";
		$activationDate = date("d M Y");
    	//$mobile = $this->cusdecrypt($provider->mobile,env('DB_SECRET'));
    	$postfield=[
			"officeId" => 1,
			"firstname"=> $data['first_name'],
			"lastname"=> $data['last_name'],
			"externalId"=> "",
			"dateFormat"=> "dd MMMM yyyy",
			"locale"=> "en",
			"active"=> true,
			"activationDate"=> $activationDate,
			"submittedOnDate"=> $activationDate,
			"savingsProductId" => 51,
			"mobileNo"=>$data['mobile'],
			"clientClassificationId"=>"41",
			"clientTypeId"=>37,
			"genderId"=>14,
			"legalFormId"=>1,
			"address"=>[[
			"addressTypeId"=>23,
			"isActive"=>1,
			"street"=>"nepal",
			"stateProvinceId"=>12,
			"countryId"=>31			   
			   ]]			   
		];
		//Create Client
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $store_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => json_encode($postfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}

		return $create_client = json_decode($response,true);;
	}
	public static function CBSactivateclient($data)
	{
		//Activate Client
		$url=$data['cbs_url'];
		$activationDate = date("d M Y");
		$clientId=$data['clientId'];
		$activate_url=$url."/fineract-provider/api/v1/clients/".$clientId."?command=activate";
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $activate_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  //CURLOPT_POSTFIELDS => json_encode($postfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}
		$activate = json_decode($response,true);
		if(@$activate['httpStatusCode'] == 403){
			return $response;
		}
	}
	public static function CBSsavingsaccounts($data)
	{
		//SavingAccountVerify
		$url=$data['cbs_url'];
		$activationDate = date("d M Y");
		$clientId=$data['clientId'];

		$savingaccount_url=$url."/fineract-provider/api/v1/savingsaccounts";
		$savingfield=[
			"clientId" => $clientId,
			"productId"=> 1,
			"locale"=> "en",
			"dateFormat"=> "dd MMMM yyyy",			   
			"submittedOnDate"=> $activationDate,			   
		];

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $savingaccount_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => json_encode($savingfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}
	}
	public static function CBScreateselfuser($data)
	{
		//Create Self User
		$url=$data['cbs_url'];
		$activationDate = date("d M Y");
		$clientId=$data['clientId'];

		$createself_url=$url."/fineract-provider/api/v1/users";
		$create_self=[
			"username" => $data['mobile'],
			"firstname" => $data['first_name'],
			"lastname" => $data['last_name'],
			"email" => $data['email'],
			"officeId"=> 1,		
			"staffId"=> 4,			   
			"roles"=> [5],
			"sendPasswordToEmail" => "false",
			"clients"=> [$clientId],
			"isSelfServiceUser"=> "true"							   
		];

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $createself_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => json_encode($create_self),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}
		return $selfservice = json_decode($response,true);
	}
	public static function CBSchangepassword($data)
	{
		//Changes password
		$url=$data['cbs_url'];
		$activationDate = date("d M Y");
		$resourceId=$data['resourceId'];

		$password_url=$url."/fineract-provider/api/v1/users/".$resourceId;
		\Log::info("CBS Pass URL----------");
		\Log::info($password_url);		
		$password_field=[
			"firstname" => $data['first_name'],
			"password" => $data['password'],
			"repeatPassword" => $data['password'],							   
		];

		\Log::info("Request Data---------");
		\Log::info($password_field);
		\Log::info("----------");
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $password_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "PUT",
		  CURLOPT_POSTFIELDS => json_encode($password_field),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}
		
		$activate = json_decode($response,true);

		\Log::info("Activation Response of Password Update-----------");
		\Log::info($activate);
		\Log::info("----------------");

		return $activate;
	}

	public static function CBSGetUserDetails($data)
	{
		$mobile=$data['username'];
		$getresource_url="http://13.71.26.182:8081/User/api/v1/user/detail/".$mobile;
		\Log::info("GEt Details URL-------------".$getresource_url);
		\Log::info("Tokenn-----------".$data['base64EncodedAuthenticationKey']);
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $getresource_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  //CURLOPT_POSTFIELDS => json_encode($postfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
			\Log::info($err);
		  return $return= "cURL Error #:" . $err;
		}
		$activate = json_decode($response,true);

		\Log::info("Response------");
		\Log::info($activate);
		\Log::info("--------------");

		return $activate;
	}

	public static function CBSgetresource($data)
	{
		//Activate Client
		$url=$data['cbs_url'];
		$activationDate = date("d M Y");
		$mobile=$data['mobile'];
		$getresource_url="http://13.71.26.182:8081/User/api/v1/user/".$mobile;
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $getresource_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 120,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  //CURLOPT_POSTFIELDS => json_encode($postfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {

			\Log::info("Resorce errorrrrrr------");
			\Log::info($err);
		  return $return= "cURL Error #:" . $err;
		}
		$activate = json_decode($response,true);

		\Log::info("CBS --- Get resource-------------");

		\Log::info($activate);

		\Log::info("----------------------");


		return $activate;
	}
	public static function CBSgetclient($data)
	{
		//Activate Client
		$url=$data['cbs_url'];
		$activationDate = date("d M Y");
		$resourceId=$data['resourceId'];
		$getresource_url=$url."/fineract-provider/api/v1/users/".$resourceId;
		\Log::info("GEt Resource URL-------".$getresource_url);
		\Log::info("AUth Token------".$data['base64EncodedAuthenticationKey']);
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $getresource_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  //CURLOPT_POSTFIELDS => json_encode($postfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}
		return $activate = json_decode($response,true);
	}
	public static function CBSsavingsaccount($data)
	{
		//Activate Client
		$url=$data['cbs_url'];
		$activationDate = date("d M Y");
		$clientId=$data['clientId'];
		$getresource_url=$url."/fineract-provider/api/v1/clients/".$clientId;
		\Log::info("Saving Url------".$getresource_url);
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $getresource_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_SSL_VERIFYPEER => 0,
		  CURLOPT_SSL_VERIFYHOST => 0,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  //CURLOPT_POSTFIELDS => json_encode($postfield),
		  CURLOPT_HTTPHEADER => array(
		    "Authorization: Basic ".$data['base64EncodedAuthenticationKey'],
		    "Content-Type: application/json",
		    "Fineract-Platform-TenantId: default",
		    "Postman-Token: 2bf7d5cd-3edf-4ffc-864b-c59ce97bfb46",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
		  return $return= "cURL Error #:" . $err;
		}
		return $activate = json_decode($response,true);
	}
	public static function curl($url)
	{
		// return $url;
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    $return = curl_exec($ch);
	    curl_close ($ch);
	    return $return;


		// $curl = curl_init();

		// curl_setopt_array($curl, array(
		// 	CURLOPT_URL => $url,
		// 	CURLOPT_RETURNTRANSFER => true,
		// 	CURLOPT_ENCODING => "",
		// 	CURLOPT_MAXREDIRS => 10,
		// 	CURLOPT_TIMEOUT => 30,
		// 	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		// 	CURLOPT_CUSTOMREQUEST => "GET",
		// 	CURLOPT_HTTPHEADER => array(
		// 	"cache-control: no-cache",
		// 	),
		// ));

		// $response = curl_exec($curl);
		// $err = curl_error($curl);
		// curl_close($curl);

		// return $response;
	}

	public static function generate_booking_id($prefix) {
		return $prefix.mt_rand(100000, 999999);
	}

	public static function setting($company_id = null)
	{
		
		if( Auth::guard(strtolower(self::getGuard()))->user() != null ) {
			$id = ($company_id == null) ? Auth::guard(strtolower(self::getGuard()))->user()->company_id : $company_id;
		} else {
			$id = 1;
		}
		$setting = Setting::where('company_id', $id )->first();
		$settings = json_decode(json_encode($setting->settings_data));
		$settings->demo_mode = $setting->demo_mode;
		return $settings;
	}

	public static function getAddress($latitude,$longitude){

		if(!empty($latitude) && !empty($longitude)){
			//Send request and receive json data by address
			$geocodeFromLatLong = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?latlng='.trim($latitude).','.trim($longitude).'&sensor=false&key='.config('constants.map_key')); 
			$output = getDistanceMap(trim($latitude), trim($longitude));
			$status = $output->status;
			//Get address from json data
			$address = ($status=="OK")?$output->results[0]->formatted_address:'';
			//Return address of the given latitude and longitude
			if(!empty($address)){
				return $address;
			}else{
				return false;
			}
		}else{
			return false;   
		}
	}

	public static function getDistanceMap($source, $destination) {

		$settings = Helper::setting();
		$siteConfig = $settings->site;

		$map = file_get_contents('https://maps.googleapis.com/maps/api/distancematrix/json?origins='.implode('|', $source).'&destinations='.implode('|', $destination).'&sensor=false&key='.$siteConfig->server_key); 
		return json_decode($map);
	}

	public static function my_encrypt($passphrase, $encrypt) {
	 
	    $salt = openssl_random_pseudo_bytes(128);
		$iv = openssl_random_pseudo_bytes(16);
		//on PHP7 can use random_bytes() istead openssl_random_pseudo_bytes()
		//or PHP5x see : https://github.com/paragonie/random_compat

		$iterations = 999;  
		$key = hash_pbkdf2("sha1", $passphrase, $salt, $iterations, 64);

		$encrypted_data = openssl_encrypt($encrypt, 'aes-128-cbc', hex2bin($key), OPENSSL_RAW_DATA, $iv);

		$data = array("ciphertext" => base64_encode($encrypted_data), "iv" => bin2hex($iv), "salt" => bin2hex($salt));

		return $data;

	}

	public static function encryptResponse($response = []) {

		$status = !empty($response['status']) ? $response['status'] : 200 ;
		$title = !empty($response['title']) ? $response['title'] : self::getStatus($status) ;
		$message = !empty($response['message']) ? $response['message'] : '' ;
		$responseData = !empty($response['data']) ? self::my_encrypt('FbcCY2yCFBwVCUE9R+6kJ4fAL4BJxxjd', json_encode($response['data'])) : [] ;
		$error = !empty($response['error']) ? $response['error'] : [] ;

		if( ($status != 401) && ($status != 405) && ($status != 422)  ) {

			RequestLog::create(['data' => json_encode([
			'request' => app('request')->request->all(),
			'response' => $message,
			'error' => $error,
			'responseCode' => $status,
			$_SERVER['REQUEST_METHOD'] => $_SERVER['REQUEST_URI'] . " " . $_SERVER['SERVER_PROTOCOL'], 
            'host' => $_SERVER['HTTP_HOST'], 
            'ip' => $_SERVER['REMOTE_ADDR'], 
            'user_agent' => $_SERVER['HTTP_USER_AGENT'], 
            'date' => \Carbon\Carbon::now()->format('Y-m-d H:i:s')])]);

		}
		
		return response()->json(['statusCode' => (string) $status, 'title' => $title, 'message' => $message, 'responseData' => $responseData, 'error' => $error], $status);
	}

	public static function getResponse($response = []) {
		
		$status = !empty($response['status']) ? $response['status'] : 200 ;
		$title = !empty($response['title']) ? $response['title'] : self::getStatus($status) ;
		$message = !empty($response['message']) ? $response['message'] : '' ;
		$responseData = !empty($response['data']) ? $response['data'] : [] ;
		$error = !empty($response['error']) ? $response['error'] : [] ;

		if( ($status != 401) && ($status != 405) && ($status != 422)  ) {
		
			app('request')->request->remove('picture');
			app('request')->request->remove('file');
			app('request')->request->remove('vehicle_image');
			app('request')->request->remove('vehicle_marker');

			RequestLog::create(['data' => json_encode([
			'request' => app('request')->request->all(),
			'response' => $message,
			'error' => $error,
			'responseCode' => $status,
			$_SERVER['REQUEST_METHOD'] => $_SERVER['REQUEST_URI'] . " " . $_SERVER['SERVER_PROTOCOL'], 
            'host' => $_SERVER['HTTP_HOST'], 
            'ip' => $_SERVER['REMOTE_ADDR'], 
            'user_agent' => $_SERVER['HTTP_USER_AGENT'], 
            'date' => \Carbon\Carbon::now()->format('Y-m-d H:i:s')])]);

		}
		
		return response()->json(['statusCode' => (string) $status, 'title' => $title, 'message' => $message, 'responseData' => $responseData, 'error' => $error], $status);
	}

	public static function getStatus($code) {

		switch ($code) {
			case 200:
				return "OK";
				break;
			
			case 201:
				return "Created";
				break;

			case 204:
				return "No Content";
				break;

			case 301:
				return "Moved Permanently";
				break;

			case 400:
				return "Bad Request";
				break;

			case 401:
				return "Unauthorized";
				break;

			case 403:
				return "Forbidden";
				break;

			case 404:
				return "Not Found";
				break;

			case 405:
				return "Method Not Allowed";
				break;

			case 422:
				return "Unprocessable Entity";
				break;

			case 500:
				return "Internal Server Error";
				break;

			case 502:
				return "Bad Gateway";
				break;

			case 503:
				return "Service Unavailable";
				break;
		}
	}


	public static function delete_picture($picture) {
		$url = app()->basePath('storage/') . $picture;
		@unlink($url);
		return true;
	}

	public static function send_sms($companyId,$plusCodeMobileNumber, $smsMessage) {

		\Log::info("Send SMS--------------");
		//  SEND OTP TO REGISTER MEMBER
		
		$settings = json_decode(json_encode(Setting::where('company_id',$companyId)->first()->settings_data));
		$siteConfig = $settings->site; 

		$username =$siteConfig->sms_login;
		$password = $siteConfig->sms_pass;


		$tousernumber = $plusCodeMobileNumber ;

		$url = "http://54.186.205.141/playsms/index.php?app=ws&u=vnext&h=7cf9a80d2ce88ee2b205bced4e408e9b&op=pv&to=".$tousernumber."&msg=".urlencode($smsMessage);
		\Log::info($url);
                
		try {
		
			$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Connection: Keep-Alive'
		]);
			$response = curl_exec($ch);
			$err = curl_error($ch);
			curl_close($ch);
			if($err){

				return $err;
				
			}
	
			Log::info('Message sent to ' . $plusCodeMobileNumber.'---'. $response);
			return $response;
		} catch (Exception $e) {
			Log::error($e->getMessage);
			return $e;
		}


		//OLD TWILLIO
		/*$settings = json_decode(json_encode(Setting::where('company_id',$companyId)->first()->settings_data));
		$siteConfig = $settings->site; 
		$accountSid =$siteConfig->sms_account_sid;
		$authToken = $siteConfig->sms_auth_token;
		$twilioNumber = $siteConfig->sms_from_number;
		
		$client = new Client($accountSid, $authToken);
		// $tousernumber = '+17577932902';
		$tousernumber = $plusCodeMobileNumber ;
		try {
			$client->messages->create(
				$tousernumber,
				[
					"body" => $smsMessage,
					"from" => $twilioNumber
					//   On US phone numbers, you could send an image as well!
					//  'mediaUrl' => $imageUrl
				]
			);
			Log::info('Message sent to ' . $plusCodeMobileNumber.'from '. $twilioNumber);
			return true;
		} catch (TwilioException $e) {
			Log::error(
				'Could not send SMS notification.' .
				' Twilio replied with: ' . $e
			);
			return $e;
		}*/

	}



	public static function send_msg($companyId,$plusCodeMobileNumber, $smsMessage) {
		\Log::info("Send Message--------------");
		//  SEND OTP TO REGISTER MEMBER
		$settings = json_decode(json_encode(Setting::where('company_id',$companyId)->first()->settings_data));
		$siteConfig = $settings->site; 

		$username =$siteConfig->sms_login;
		$password = $siteConfig->sms_pass;


		$tousernumber = $plusCodeMobileNumber ;
		try {

			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_PORT => "33844",
			//	CURLOPT_URL => "https://182.50.64.134:33844/sendsms?username=".$username."&password=".$password."&phonenumber=9861941884&message=msgfromapp",
				CURLOPT_URL => "http://54.186.205.141/playsms/index.php?app=ws&u=vnext&h= 7cf9a80d2ce88ee2b205bced4e408e9b&op=pv&to=".$tousernumber."&msg=".urlencode($smsMessage),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 300,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "GET",
                                CURLOPT_SSLVERSION => 6,
                                CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST=>0,
                               // CURLOPT_SSL_VERIFYPEER=>0,
	
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			\Log::info($err);
			curl_close($curl);
	
			Log::info('Message sent to ' . $plusCodeMobileNumber.'---'. $response);
			return $response;
		} catch (Exception $e) {
			Log::error($e->getMessage);
			return $e;
		}

	}


	public static function siteRegisterMail($user){

		$settings = json_decode(json_encode(Setting::where('company_id',$user->company_id)->first()->settings_data));

		Mail::send('mails.welcome', ['user' => $user, 'settings' => $settings], function ($mail) use ($user, $settings) {
			$mail->from($settings->site->mail_from_address, $settings->site->mail_from_name);
			$mail->to($user->email, $user->first_name.' '.$user->last_name)->subject('Welcome');
		});

		return true;
	}

	public static function newOrder($shop, $user){

		$settings = json_decode(json_encode(Setting::where('company_id',$user->company_id)->first()->settings_data));

		Mail::send('mails.shoporder', ['shop' => $shop, 'user' => $user,'settings' => $settings], function ($mail) use ($settings) {
			$mail->from($settings->site->mail_from_address, $settings->site->mail_from_name);
			$mail->to('support@edheba.com')->subject('New Order Received');
		});

		return true;
	}
	
	public static function send_emails($templateFile,$toEmail,$subject, $data) {
		try{
			//dd($data['salt_key']);
            if(isset($data['salt_key'])){
				$settings = json_decode(json_encode(Setting::where('company_id',$data['salt_key'])->first()->settings_data));
			}else{
                   if(!empty(Auth::user())){          
			            $company_id = Auth::user()->company_id;
			        }
			        else if(!empty(Auth::guard('shop')->user())){          
			            $company_id = Auth::guard('shop')->user()->company_id;
			        }else{

			        }
				$settings = json_decode(json_encode(Setting::where('company_id',$company_id)->first()->settings_data));
			}
			$data['settings'] = $settings;
			$mail =  Mail::send("$templateFile",$data,function($message) use ($data,$toEmail,$subject,$settings) {
				$message->from($settings->site->mail_from_address, $settings->site->mail_from_name);
				$message->to($toEmail)->subject($subject);
			});
			
			if( count(Mail::failures()) > 0 ) {
			  
			   throw new \Exception('Error: Mail sent failed!');

			} else {
				return true;
			}
			
		}
		catch (\Throwable $e) {	
			dd($e);
		
            throw new \Exception($e->getMessage());
        } 
		
	}

	
	public static function send_emails_job($templateFile, $toEmail, $subject, $data) 
	{
		try{
			
			$mail =  Mail::send($templateFile, $data, function($message) use ($data, $toEmail, $subject) {
				$message->from("dev@appoets.com", "GOX");
				$message->to($toEmail)->subject($subject);
			});

			// dd(Mail::failures());
			
			if( count(Mail::failures()) > 0 ) {
			  
			   throw new \Exception('Error: Mail sent failed!');

			} else {
				return true;
			}
			
		}
		catch (\Throwable $e) {	
			dd($e);
		
            throw new \Exception($e->getMessage());
        } 
		
	}
}

