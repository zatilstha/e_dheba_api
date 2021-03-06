<?php

namespace App\Http\Controllers\V1\Common\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Services\ReferralResource;
use App\Models\Common\CompanyCity;
use App\Models\Common\CompanyCountry;
use App\Models\Common\Country;
use Illuminate\Validation\Rule;
use App\Models\Common\Setting;
use App\Models\Common\AuthLog;
use Illuminate\Http\Request;
use App\Models\Common\User;
use Tymon\JWTAuth\JWTAuth;
use App\Traits\Encryptable;
use App\Helpers\Helper;
use Auth;

class UserAuthController extends Controller
{
    protected $jwt;
    use Encryptable;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    }

    public function login(Request $request) {
        $re=$request->all();
        if($request->has('email')) {
            $request->merge([
                'email' => strtolower($request->email)
            ]);
        }

        $this->validate($request, [
            'email'    => 'email|max:255',
            'password' => 'required',
            'salt_key' => 'required',
        ]);

       
        if($request->has('email') && $request->email != '') {
            $request->merge([
                'email' => $this->cusencrypt($request->email,env('DB_SECRET'))            
            ]);
        }


        if($request->has('mobile')) {
            $request->merge([
                'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET'))            
            ]);
        }

        if(!$request->has('email') && !$request->has('mobile')) {
            $this->validate($request, [
                'email'    => 'required|email|max:255',
                'mobile' => 'required',
                'country_code' => 'required'
            ]);
        } else if(!$request->has('mobile')) {
            $this->validate($request, [
                'email'    => ['required', 'max:255', Rule::exists('users')]
            ]);
        } else if(!$request->has('email')) {
            $this->validate($request, [
                'mobile' => ['required', Rule::exists('users')],
                'country_code' => 'required'
            ],['mobile.exists'=>'Please Enter a Valid Mobile Number','email.exists'=>'Please Enter a Valid Email']);
        }

        $settings = json_decode(json_encode(Setting::where('company_id', base64_decode($request->salt_key))->first()->settings_data));
        $siteConfig = $settings->site;
        $transportConfig = $settings->transport;
        try {
            $request->request->add(['company_id' => base64_decode($request->salt_key)]);
            // $request->request->add(['status' => '1']);
            $request->request->remove('salt_key');
            if($request->has('email') && $request->email != '') {
                if (! $token = Auth::guard('user')->attempt($request->only('email', 'password', 'company_id', 'status')) ) {
                    return Helper::getResponse(['status' => 422, 'message' => 'Invalid Credentials']);
                }
            } else {
                if (! $token = Auth::guard('user')->attempt($request->only('country_code', 'mobile', 'password', 'company_id', 'status')) ) {
                    return Helper::getResponse(['status' => 422, 'message' => 'Invalid Credentials']);
                }
            }            

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return Helper::getResponse(['status' => 500, 'message' => 'Token Expired']);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return Helper::getResponse(['status' => 500, 'message' => 'Token Invalid']);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return Helper::getResponse(['status' => 500, 'message' => $e->getMessage()]);

        }

        \Log::info("First Signin--------------");

        $User = User::find(Auth::guard('user')->user()->id);
        if($User->status == 0){
            return Helper::getResponse(['status' => 422, 'message' => 'Account Disabled']);
        }
        $User->device_type = $request->device_type;
        $User->device_token = $request->device_token;
        $User->login_by = ($request->login_by != null) ? $request->login_by : 'MANUAL' ;
        $User->jwt_token = $token;
        $User->save();

        AuthLog::create(['user_type' => 'User', 'user_id' => \Auth::guard('user')->id(), 'type' => 'login', 'data' => json_encode(
            ['data' => [ $request->getMethod() =>  $request->getPathInfo(). " " . $request->getProtocolVersion(), 
            'host' => $request->getHost(), 
            'ip' => $request->getClientIp(), 
            'user_agent' => $request->userAgent(), 
            'date' => \Carbon\Carbon::now()->format('Y-m-d H:i:s')]]
        )]);

        $siteConfig = $siteConfig;
        $data['cbs_url']=@$siteConfig->cbs_url;
        $data['cbs_username']=@$siteConfig->cbs_username;
        $data['cbs_passwprd']=@$siteConfig->cbs_passwprd;

        \Log::info("Before CBS Login-----");

        $data['username']=$User->mobile;

        $data['password']=$request->password;

        $CBSlogin = Helper::CBSselflogin($data);

        \Log::info("User Client Id--------".$User->client_id);

        if($User->client_id == null ) {
        \Log::info("Get CBS User Details");
                $authentication = Helper::CBSauthentication($data);
            
                    if(@$authentication->base64EncodedAuthenticationKey){
        
                        $data['base64EncodedAuthenticationKey'] = @$authentication->base64EncodedAuthenticationKey;

                        if($User->resource_id){
                            $data['resourceId']=$User->resource_id;

                        }else{

                            $data['mobile']=$User->mobile;

                            $getresource = Helper::CBSgetresource($data);
                            if(@$getresource['id']){
                                $data['resourceId']=@$getresource['id'];
                                $User->resource_id = $getresource['id'];
                            }

                        }

                        \Log::info("Resource Id====".$data['resourceId']);
                        $getclient = Helper::CBSgetclient($data);
                        \Log::info("GEt Client-------------------");
                        // \Log::info($getclient);
                                                
                        if(@$getclient['clients']){
                            \Log::info($getclient['clients']);
                            \Log::info($getclient['clients'][0]['id']);
                            $data['clientId']=@$getclient['clients'][0]['id'];
                            $User->client_id = $data['clientId'];
                            $savingsaccount = Helper::CBSsavingsaccount($data);
                            \Log::info("Get Account Idddd-----------------");
                            if(@$savingsaccount['savingsAccountId']){
                                \Log::info($savingsaccount['savingsAccountId']."----------------");
                                $data['savingsAccountId']=@$savingsaccount['savingsAccountId'];
                                $User->account_id = $data['savingsAccountId'];
                            }
                        }

                        $User->save();
        
                        // $getDetails = Helper::CBSGetUserDetails($data);
                        // \Log::info("==============");

                        // \Log::info($getDetails); 
                }
            }

        return Helper::getResponse(['data' => ["token_type" => "Bearer", "expires_in" => (config('jwt.ttl', '0') * 60), "access_token" => $token, 'user' => Auth::guard('user')->user(), "CBSlogin" => $CBSlogin]]);

    }

    public function signup(Request $request) {

        if($request->has('email')) {
            $request->merge([
                'email' => strtolower($request->email)
            ]);
        }

        $this->validate($request, [
            'social_unique_id' => ['required_if:login_by,GOOGLE,FACEBOOK','unique:users'],
            'device_type' => 'in:ANDROID,IOS',
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'mobile' => 'required',
            'country_code' => 'required',
            'email' => 'required|email|max:255',
            'password' => ['required_if:login_by,MANUAL','min:6'],
            'salt_key' => 'required',
        ]);

        try {

        $settings = json_decode(json_encode(Setting::where('company_id', base64_decode($request->salt_key))->first()->settings_data));

        $siteConfig = $settings->site;

        $transportConfig = $settings->transport;
        $company_id = base64_decode($request->salt_key); 

        if($request->has('referral_code') && $request->referral_code != ""){
            $validate['referral_unique_id']=$request->referral_code;
            $validate['company_id']=$company_id;        
            $validator  = (new ReferralResource)->checkReferralCode($validate);        
            if (!$validator->fails()) { 
                $validator->errors()->add('referral_code', 'Invalid Referral Code');
                throw new \Illuminate\Validation\ValidationException($validator);
            }  
        }
        
        $referral_unique_id=(new ReferralResource)->generateCode($company_id);

        $request->merge([
            'email' => $this->cusencrypt($request->email,env('DB_SECRET')),
            'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET')),
        ]);


        $currentUser = null;

        $email_case = User::where('email', $request->email)->where('country_code', $request->country_code)->where('mobile', $request->mobile)->first();

        $registeredEmail = User::where('email', $request->email)->where('user_type', 'INSTANT')->first();
        $registeredMobile = User::where('country_code', $request->country_code)->where('mobile', $request->mobile)->where('user_type', 'INSTANT')->first();

        $registeredEmailNormal = User::where('email', $request->email)->where('user_type', 'NORMAL')->first();
        $registeredMobileNormal = User::where('country_code', $request->country_code)->where('mobile', $request->mobile)->where('user_type', 'NORMAL')->first();

        $validator  = Validator::make([],[],[]);

        //User Already Exists
       

        if($registeredEmail != null && $registeredMobile != null) {
            //User Already Registerd with same credentials
            if($registeredEmail != null) {       
                $validator->errors()->add('email', 'User already registered with given email-Id!');
                throw new \Illuminate\Validation\ValidationException($validator);
            } else if($registeredMobile != null) {       
                $validator->errors()->add('mobile', 'User already registered with given mobile number!');
                throw new \Illuminate\Validation\ValidationException($validator);
            }

        } else {
            if($registeredEmail != null) $currentUser = $registeredEmail;
            else if($registeredMobile != null) $currentUser = $registeredMobile;
        }

        if($registeredEmailNormal != null) {
            $validator->errors()->add('email', 'User already registered with given email-Id!');
            throw new \Illuminate\Validation\ValidationException($validator); 
        }

        if($registeredMobileNormal != null) {
            $validator->errors()->add('mobile', 'User already registered with given mobile number!');
                throw new \Illuminate\Validation\ValidationException($validator);
        } 

        $request->merge([
            'email' => $this->cusdecrypt($request->email,env('DB_SECRET')),
            'mobile' => $this->cusdecrypt($request->mobile,env('DB_SECRET')),
        ]);

        $city = CompanyCity::where('city_id', $request->city_id)->first();

        if($city == null) {
            $validator->errors()->add('city', 'City does not exist!');
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $country = CompanyCountry::where('company_id',$company_id)->where('country_id', $request->country_id)->first();

        if($currentUser == null) {
            $User = new User();
        } else {
            $User = $currentUser;
        }        

        //For MLM Feature
        if($request->has('referral_code') && $request->referral_code != ""){
           
            $get_referred_user = User::where('referral_unique_id', $request->referral_code)->first();
            //print_r($get_referred_user);exit;
            $Areferral_userid=$get_referred_user->id;
            $Breferral_userid=$get_referred_user->Areferral_userid;          
            $Creferral_userid=$get_referred_user->Breferral_userid; 

            $User->Areferral_userid = $Areferral_userid;
            $User->Breferral_userid = $Breferral_userid;
            $User->Creferral_userid = $Creferral_userid;
        }

        $User->first_name = ucfirst($request->first_name);
        $User->last_name = ucfirst($request->last_name);
        $User->email = $request->email;
        $User->gender = $request->gender;
        $User->country_code = $request->country_code;
        $User->mobile = $request->mobile;
        $User->password = ($request->social_unique_id != null)  ? Hash::make($request->social_unique_id) : Hash::make($request->password) ;
        $User->payment_mode = 'CASH';
        $User->user_type = 'NORMAL';
        $User->referral_unique_id = $referral_unique_id;
        $User->company_id = base64_decode($request->salt_key);
        $User->device_type = $request->device_type;
        $User->device_token = $request->device_token;
        $User->social_unique_id = ($request->social_unique_id != null)  ? $request->social_unique_id : null ;
        $User->login_by = ($request->login_by != null) ? $request->login_by : 'MANUAL' ;
        $User->currency_symbol = $country->currency;
        $User->country_id = $request->country_id;
        $User->state_id = $city->state_id;
        $User->city_id = $request->city_id;
        $User->save();

        //User details stored to 3rd party API
        $store_api=$this->store_api(ucfirst($request->first_name),ucfirst($request->last_name),$request->mobile,$request->email,$request->password,$siteConfig);

        if($store_api){
            \Log::info("Store APi-----");
            \Log::info("Client ID------------".@$store_api['clientId']);

            \Log::info("Account  ID------------".@$store_api['savingsAccountId']);
            $User->api_response =@$store_api['api_response'];        
            $User->client_id =@$store_api['clientId'];        
            $User->resource_id =@$store_api['resourceId'];       
            $User->account_id =@$store_api['savingsAccountId'];       
            $User->save();
        }

        if($request->hasFile('picture')) {
            $User->picture = Helper::upload_file($request->file('picture'), 'user/profile', $User->id.'.'.$request->file('picture')->getClientOriginalExtension(), base64_decode($request->salt_key));
        }
        $User->unique_id=$siteConfig->user_unique_id.$User->id;
        $User->qrcode_url = Helper::qrCode(json_encode(["country_code" => $request->country_code, 'phone_number' => $request->mobile, 'id' => base64_encode($User->unique_id)]), $User->id.'.png', base64_decode($request->salt_key));

        $User->save();

        AuthLog::create(['user_type' => 'User', 'user_id' => \Auth::guard('user')->id(), 'type' => 'login', 'data' => json_encode(
            ['data' => [ $request->getMethod() =>  $request->getPathInfo(). " " . $request->getProtocolVersion(), 
            'host' => $request->getHost(), 
            'ip' => $request->getClientIp(), 
            'user_agent' => $request->userAgent(), 
            'date' => \Carbon\Carbon::now()->format('Y-m-d H:i:s')]]
        )]);

        $request->request->add(['company_id' => base64_decode($request->salt_key)]);
        $request->request->remove('salt_key');

        $request->merge([
            'email' => $this->cusencrypt($request->email,env('DB_SECRET'))            
        ]);

        $credentials = ['email' => $this->cusencrypt($User->email,env('DB_SECRET')) , 'password' => ($request->social_unique_id != null)  ? $request->social_unique_id : $request->password, 'company_id' => $User->company_id];

        $token = Auth::guard('user')->attempt($credentials);

        if( !empty($siteConfig->send_email) && $siteConfig->send_email == 1) {
            // send welcome email here
          //  Helper::siteRegisterMail($User);
        }    

        //check user referrals
        if( !empty($siteConfig->referral) && $siteConfig->referral == 1) {
            if($request->referral_code){

                \Log::info("Referral Concept=======");
                //call referral function
                (new ReferralResource)->create_referral($request->referral_code, $User, $settings, 'user');                
            }
        } 
        $newUser = User::find($User->id); 
        $siteConfig = $siteConfig;
        $data['cbs_url']=$siteConfig->cbs_url;//'https://104.211.112.202';
        $data['username']=$newUser->mobile;
        $data['password']=($request->social_unique_id != null)  ? $request->social_unique_id : $request->password;           

        $CBSlogin = Helper::CBSselflogin($data);  

        return Helper::getResponse(['data' => ["token_type" => "Bearer", "expires_in" => (config('jwt.ttl', '0') * 60), "access_token" => $token, 'user' => $newUser,'CBSlogin' => $CBSlogin]]);
        }catch(Exception $e){
            \Log::info($e);
            return $e->getMessage();
        }
    }
    public function store_api_test(Request $request){
        //$store_api=$this->login_api($request->mobile,$request->password,1);
        $store_api=$this->store_api($request->first_name,$request->last_name,$request->mobile,$request->email,$request->password,1);
        return $store_api;
    }
    public function store_api($first_name,$last_name,$mobile,$email,$password,$siteConfig){
        try{
            $data['first_name']=$first_name;
            $data['last_name']=$last_name;
            $data['mobile']=$mobile;
            $data['password']=$password;
            $data['email']=$email;
            $data['resourceId']='';
            $data['clientId']='';
            $data['savingsAccountId']='';

            $data['cbs_url']=@$siteConfig->cbs_url;
            $data['cbs_username']=@$siteConfig->cbs_username;
            $data['cbs_passwprd']=@$siteConfig->cbs_passwprd;

            /*$data['cbs_url']='https://104.211.112.202';
            $data['cbs_username']='indiateam';
            $data['cbs_passwprd']='123456789'; */         


            //password
            /*$data['resourceId']=878;
            $data['base64EncodedAuthenticationKey']='aW5kaWF0ZWFtOjEyMzQ1Njc4OQ==';
            $changepassword = Helper::CBSchangepassword($data);*/ 

            $authentication = Helper::CBSauthentication($data);
            if(@$authentication->base64EncodedAuthenticationKey){
                $data['base64EncodedAuthenticationKey']=@$authentication->base64EncodedAuthenticationKey;

                \Log::info("CBS Auth");
                $createclient = Helper::CBScreateclient($data);

                $data['api_response']=json_encode($createclient);
                \Log::info($data['api_response']);

                if(@$createclient['clientId']){
                    \Log::info("Created Client Id");
                    $data['clientId']=@$createclient['clientId'];
                    $data['savingsAccountId']=@$createclient['savingsId'];
                    \Log::info("Activate Client");
                    $activateclient = Helper::CBSactivateclient($data);
                    \Log::info($activateclient);
                    \Log::info("Saving account");
                    $savingsaccounts = Helper::CBSsavingsaccounts($data);
                    \Log::info($savingsaccounts);

                    \Log::info("Self User");
                    $createselfuser = Helper::CBScreateselfuser($data);
                    \Log::info($createselfuser);
                    if(@$createselfuser['resourceId']){
                        $data['resourceId']=$createselfuser['resourceId'];
                        \Log::info("Resource Id======".$data['resourceId']);
                    }                   
                }else{
                  
                    \Log::info("Else Part");

                    $getresource = Helper::CBSgetresource($data);             
                    if(@$getresource['id']){
                        $data['resourceId']=@$getresource['id'];

                        \Log::info("Else Resource Id====".$data['resourceId']);
                        $getclient = Helper::CBSgetclient($data);
                        \Log::info("GEt Client-------------------");
                        // \Log::info($getclient);
                                                
                        if(@$getclient['clients']){
                            \Log::info($getclient['clients']);
                            \Log::info($getclient['clients'][0]['id']);
                            $data['clientId']=@$getclient['clients'][0]['id'];
                            $savingsaccount = Helper::CBSsavingsaccount($data);
                            \Log::info("Get Account Idddd-----------------");
                            // \Log::info($savingsaccount);

                            if(@$savingsaccount['savingsAccountId']){
                                \Log::info($savingsaccount['savingsAccountId']."----------------");
                                $data['savingsAccountId']=@$savingsaccount['savingsAccountId'];
                            }
                        }
                    }
                }                
                $changepassword = Helper::CBSchangepassword($data);   
                return $data;
            }else{
                \Log::info("No Authentication-----");
                return $authentication;
            }           
                       
        }catch(Exception $e){
            \Log::info($e);
            return $e->getMessage();
        }
    }
    public function changepassword_api($mobile,$first_name,$resourceId,$password,$siteConfig){
        try{
            $data['mobile']=$mobile;
            $data['first_name']=$first_name;
            $data['password']=$password;
            $data['resourceId']=$resourceId;

            \Log::info("Pass resource id-----".$resourceId);

            $data['cbs_url']=@$siteConfig->cbs_url;
            $data['cbs_username']=@$siteConfig->cbs_username;
            $data['cbs_passwprd']=@$siteConfig->cbs_passwprd;

            $authentication = Helper::CBSauthentication($data);
            if(@$authentication->base64EncodedAuthenticationKey){
                $data['base64EncodedAuthenticationKey']=@$authentication->base64EncodedAuthenticationKey;            
                if(empty($data['resourceId']) || $data['resourceId'] == 'c'){
                    $getresource = Helper::CBSgetresource($data);
                    if(@$getresource['id']){
                        \Log::info("Get Resource Id------".@$getresource['id']);
                        $data['resourceId']=@$getresource['id'];                
                    }
                }                   
                $changepassword = Helper::CBSchangepassword($data); 
       
                return $changepassword;
            }
            else{
                return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $authentication]);
            }

        }catch(Exception $e){
            return $e->getMessage();
        }
    }

    public function refresh(Request $request) {

        Auth::guard('user')->setToken(Auth::guard('user')->getToken());

        return Helper::getResponse(['data' => [
                "token_type" => "Bearer", "expires_in" => (config('jwt.ttl', '0') * 60), "access_token" => Auth::guard('user')->refresh()
            ]]);
    }

    public function logout(Request $request) {
        try {

            Auth::guard('user')->setToken(Auth::guard('user')->getToken());
            $User = User::find(Auth::guard('user')->user()->id);
            Auth::guard('user')->invalidate();
            $User->jwt_token = null;
            $User->save();

            AuthLog::create(['user_type' => 'User', 'user_id' => \Auth::guard('user')->id(), 'type' => 'logout', 'data' => json_encode(
                ['data' => [ $request->getMethod() =>  $request->getPathInfo(). " " . $request->getProtocolVersion(), 
                'host' => $request->getHost(), 
                'user_agent' => $request->userAgent(), 
                'date' => \Carbon\Carbon::now()->format('Y-m-d H:i:s')]]
            )]);


            return Helper::getResponse(['message' => 'Successfully logged out']);

        } catch (JWTException $e) {

            return Helper::getResponse(['status' => 403, 'message' => $e->getMessage()]);
        }
    }

    public function forgotPasswordOTP(Request $request){
        $account_type = isset($request->account_type)?$request->account_type:'';
        if($account_type =='mobile'){
            $response =  $this->forgotPasswordMobile($request);
        }else{
            $response = $this->forgotPasswordEmail($request);
        }
        return $response;
    }

    public function forgotPasswordEmail($request) {
        $this->validate($request, [
            'email' => 'required|email|max:255',
            'salt_key' => 'required',
        ]);
        $emaildata['username'] = $toEmail = isset($request->email)?$request->email:'';
        $emaildata['account_type'] = isset($request->account_type)?$request->account_type:'';
        try {
            $request->merge([
                'email' => $this->cusencrypt($request->email,env('DB_SECRET'))            
            ]);
            $request->request->add(['company_id' => base64_decode($request->salt_key)]);
            $request->request->remove('salt_key');
            $settings = json_decode(json_encode(Setting::where('company_id', $request->company_id)->first()->settings_data));
            $siteConfig = $settings->site;            
            $otp = mt_rand(100000, 999999);
            $userQuery = User::where('email' , $request->email)->first();
            //User Not Exists
            $validator  = Validator::make([],[],[]);
            if($userQuery == null) {
                $validator->errors()->add('mobile', 'User not found');
                throw new \Illuminate\Validation\ValidationException($validator); 
            }
            $userQuery->otp = $otp;
            $userQuery->save();
            $emaildata['otp'] = $otp;
            if( !empty($siteConfig->send_email) && $siteConfig->send_email == 1) {
                
                //  SEND OTP TO MAIL
                    $subject='Forgot|OTP';
                    $templateFile='mails/forgotpassmail';
                    $data=['body'=>$otp,'username'=>$userQuery->first_name,'salt_key'=>$request->company_id];
                    $result= Helper::send_emails($templateFile,$toEmail,$subject, $data);               
                  
            }else{
                $errMessage = 'Mail configuration disabled';
            }
            return Helper::getResponse(['status' => 200, 'message'=>'success','data'=>$emaildata]);              
        }catch (Exception $e){
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function forgotPasswordMobile($request) {
        $this->validate($request, [
            'mobile' => 'required|numeric|min:6',
            'country_code' => 'required',
        ]);
        try {
            $smsdata['country_code'] = isset($request->country_code)?$request->country_code:'';
            $smsdata['username'] = isset($request->mobile)?$request->mobile:'';
            $smsdata['account_type'] = isset($request->account_type)?$request->account_type:'';
            // if($smsdata['username'] == "7995349453" || $smsdata['username'] == "9566310540"){

            //     $plusCodeMobileNumber = '+91'.$smsdata['username'];
            //     \Log::info($plusCodeMobileNumber);

            // }else{

                $plusCodeMobileNumber = $smsdata['username'];

            // }
            
            $request->merge([
                'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET'))            
            ]);
            $request->request->add(['company_id' => base64_decode($request->salt_key)]);
            $request->request->remove('salt_key');
            $settings = json_decode(json_encode(Setting::where('company_id', $request->company_id)->first()->settings_data));
            $siteConfig = $settings->site; 
            $companyId = $request->company_id;
            $otp = mt_rand(100000, 999999);
            $userQuery = User::where('mobile' , $request->mobile)->first();
            //User Not Exists
            $validator  = Validator::make([],[],[]);
            if($userQuery == null) {         
                $validator->errors()->add('mobile', 'User not found');
                throw new \Illuminate\Validation\ValidationException($validator); 
            }
            $userQuery->otp = $otp;
            $saveQuery = $userQuery->save();
            if($saveQuery){
                $smsdata['otp'] = $otp;
                $smsMessage ='Your Otp to reset password is '.$otp;
                if( !empty($siteConfig->send_sms) && $siteConfig->send_sms == 1) {
                    // send OTP SMS here            
                    $result= Helper::send_sms($companyId,$plusCodeMobileNumber, $smsMessage);
                    $smsdata['smsresult']=$result;
                }else{
                    $errMessage = 'SMS configuration disabled';
                }
                return Helper::getResponse(['status' => 200, 'message'=>'success','data'=>$smsdata]);              
            }else{
                $errMessage =trans('admin.something_wrong');
            }            
        }catch (Exception $e){
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }   
        return Helper::getResponse(['status' => 404, 'message' => $errMessage]);            
    }
public function testotp(Request $request) {

    $request->request->add(['company_id' => base64_decode($request->salt_key)]);
    $request->request->remove('salt_key');
    
    $companyId = $request->company_id;

    $plusCodeMobileNumber = isset($request->mobile)?$request->mobile:'';

    $smsMessage ='Your Otp to reset password is ';

    $result= Helper::send_sms($companyId,$plusCodeMobileNumber, $smsMessage);

    dd($result);

}
    public function resetPasswordOTP(Request $request) {
        $this->validate($request, [
            'username' => 'required',
            'otp' => 'required|numeric',
            'account_type' => 'required',
            'password' => 'required|min:6|confirmed|max:36',
        ]);
        $responseData=$request->all();
        try {
            $account_type = isset($request->account_type)?$request->account_type:'';
            $username = isset($request->username)?$request->username:'';
            $newpassword = isset($request->password)?$request->password:'';
            $otp = isset($request->otp)?$request->otp:'';
            $request->merge([
                'loginUser' => $this->cusencrypt($username,env('DB_SECRET'))            
            ]);
            if($account_type =='mobile'){
                $where = ['mobile'=>$request->loginUser];
            }else{
                $where = ['email'=>$request->loginUser];
            }
            $userQuery = User::where($where)->first();
                //User Not Exists
            $validator  = Validator::make([],[],[]);
            if($userQuery == null) {         
                $validator->errors()->add('Result', 'User not found');
                throw new \Illuminate\Validation\ValidationException($validator); 
            }else{
                $dbOtpCode = $userQuery->otp;
                if($dbOtpCode != $otp){
                    $validator->errors()->add('Result', 'Invalid Credentials');
                    throw new \Illuminate\Validation\ValidationException($validator);
                }
                //CBS Change password
                $settings = json_decode(json_encode(Setting::where('company_id', base64_decode($request->salt_key))->first()->settings_data));
                $siteConfig = $settings->site;

                $CBS_changepassword=$this->changepassword_api($userQuery->mobile,$userQuery->first_name,$userQuery->resource_id,$newpassword,$siteConfig);

                // \Log::info("------------".$CBS_changepassword);

                if(empty(@$CBS_changepassword['changes']['passwordEncoded'])){

                    \Log::info("CBS Pass Error---");
                    \Log::info(@$CBS_changepassword['defaultUserMessage']);
                    \Log::info("------------");
                   
                    $data_err=@$CBS_changepassword['defaultUserMessage']?@$CBS_changepassword['defaultUserMessage']:'The submitted password has already been used in the past'; 
                    return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $data_err]);               
                }

                $enc_newpassword = Hash::make($newpassword);
                $input =['password' => $enc_newpassword];
                $userQuery->password = $enc_newpassword;
                $userQuery->login_by = 'MANUAL';
                $userQuery->social_unique_id = NULL;
                $userQuery->resource_id = $CBS_changepassword['resourceId'];
                $userQuery->otp = 0;
                $userQuery->save();
                $responseData['CBS_changepassword']=$CBS_changepassword;
                                
            }            
            return Helper::getResponse(['status' => 200, 'message'=>'Password changed successfully','data'=>$responseData]);              
        }catch (Exception $e){
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }            
    }

    public function verify(Request $request) {

        if($request->has('email')) {
            $request->merge([
                'email' => strtolower($request->email)
            ]);
        }

        $this->validate($request, [
            'mobile' => 'sometimes',
            'email' => 'sometimes|email|max:255',
            'salt_key' => 'required',
        ]);


        $company_id=base64_decode($request->salt_key);

        if($request->has('email')) {

            $request->merge([                
                'email' => $this->cusencrypt($request->email,env('DB_SECRET')),
            ]);

            $email=$request->email;

            $this->validate($request, [          
                'email' =>[ Rule::unique('users')->where(function ($query) use($email,$company_id) {
                                return $query->where('email', $email)->where('company_id', $company_id)->where('user_type', 'NORMAL');
                             }),
                           ],
                
            ],['email.unique'=>'User already registered with given email-Id!']);
        }    

        if($request->has('mobile')) {

            $request->merge([            
                'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET')),
            ]);

            $mobile=$request->mobile;
            $country_code=$request->country_code;

            $this->validate($request, [
                'mobile' =>[ Rule::unique('users')->where(function ($query) use($mobile,$company_id,$country_code) {
                                return $query->where('mobile', $mobile)->where('country_code', $country_code)->where('company_id', $company_id)->where('user_type', 'NORMAL');
                             }),
                           ],
            ],['mobile.unique'=>'User already registered with given mobile number!']);
        }

        return Helper::getResponse();
    }


    public function user_sms_check(Request $request)
    {

        try{
            $otp = mt_rand(1000, 9999);
           
            $request->request->add(['company_id' => base64_decode($request->salt_key)]);
            $settings = json_decode(json_encode(Setting::where('company_id', $request->company_id)->first()->settings_data));
            
            $smsMessage =$otp.' is your '.$settings->site->site_title. ' OTP password ';
            $siteConfig = $settings->site;    
            $companyId = $request->company_id;
            $plusCodeMobileNumber = $request->mobile;


             $request->merge([
                    'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET'))            
                ]);

            $userQuery = User::where('mobile' , $request->mobile)->first();
           
            if($userQuery == null) {      

                 if(!empty($siteConfig->send_sms) && $siteConfig->send_sms == 1){
                    $result= Helper::send_sms($companyId,$plusCodeMobileNumber, $smsMessage);
                    $data['smsresult']=$result;
                    $data['otp'] = $otp;

                }else{
                    $errMessage = 'SMS configuration disabled';
                }
                return Helper::getResponse(['status' => 200, 'message'=>'OTP Sent Successfully', 'data' => $data]);  
            }else{
                return Helper::getResponse(['status' => 422, 'message'=>'Mobile Number Already Exist']);  
            }
        }
        catch (Exception $e){
                return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }   
    }


}
