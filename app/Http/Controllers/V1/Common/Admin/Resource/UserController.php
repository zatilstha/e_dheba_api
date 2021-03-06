<?php

namespace App\Http\Controllers\V1\Common\Admin\Resource;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Traits\Actions;
use App\Helpers\Helper;
use App\Models\Common\User;
use App\Models\Common\CompanyCity;
use App\Models\Common\CompanyCountry;
use App\Models\Common\Setting;
use App\Models\Common\AuthLog;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;
use App\Services\SendPushNotification;
use Auth;
use DB;
use App\Traits\Encryptable;
use Illuminate\Validation\Rule;
use App\Services\ReferralResource;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Http\Controllers\V1\Common\User\UserAuthController;


class UserController extends Controller
{
    use Actions;
    use Encryptable;

    private $model;
    private $request;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function index(Request $request)
    {

        $datum = User::where('company_id', Auth::user()->company_id);

        if($request->has('search_text') && $request->search_text != null) {
            $datum->Search($request->search_text);
        }

        if($request->has('order_by')) {
            $datum->orderby($request->order_by, $request->order_direction);
        }

        if($request->has('page') && $request->page == 'all') {
            $datum = $datum->get();
        } else {
            $datum = $datum->paginate(10);
        }

        return Helper::getResponse(['data' => $datum]);
    }
    public function users_export(){
        $data = User::select('id','first_name','last_name','email','mobile','rating','wallet_balance')->get();
        $filename = 'users.csv';
        Excel::store(new UsersExport($data), $filename, 'public');
        return redirect("storage/{$filename}");
        
    }

    public function store(Request $request)
    {

        $this->validate($request, [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'email' => $request->email != null ?'sometimes|required|email|max:255':'',
            'mobile' => $request->mobile != null ?'sometimes|required|digits_between:6,13':'',
            'gender' => 'required|in:MALE,FEMALE',
            'country_code' => 'required|max:25',
            'picture' => 'mimes:jpeg,jpg,bmp,png|max:5242880',
            'password' => 'required|min:6|confirmed',
            'country_id' => 'required',
            'city_id' => 'required',
        ]);

         if($request->has('email')) {
            $request->merge([
                'email' => strtolower($request->email)
            ]);
        }

        $company_id=Auth::user()->company_id;
        if($request->has('email') && $request->has('mobile')) {
            $request->merge([
                'email' => $this->cusencrypt($request->email,env('DB_SECRET')),
                'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET')),
            ]); 


            
            $email=$request->email;
            $mobile=$request->mobile;
            

            $this->validate($request, [          
                'email' =>[ Rule::unique('users')->where(function ($query) use($email,$company_id) {
                                return $query->where('email', $email)->where('company_id', $company_id);
                             }),
                           ],
                'mobile' =>[ Rule::unique('users')->where(function ($query) use($mobile,$company_id) {
                                return $query->where('mobile', $mobile)->where('company_id', $company_id);
                             }),
                           ],
            ]);
        }

        try{

            if($request->has('email') && $request->has('mobile')) {
                $request->merge([
                    'email' => $this->cusdecrypt($request->email,env('DB_SECRET')),
                    'mobile' => $this->cusdecrypt($request->mobile,env('DB_SECRET')),
                ]);
            }

            $request->request->add(['company_id' => Auth::user()->company_id]);
            $user = $request->all();

            $user['payment_mode'] = 'CASH';
            $user['password'] = Hash::make($request->password);
           
            $user['referral_unique_id']=(new ReferralResource)->generateCode($company_id);

            $user = User::create($user);

            if($request->has('state_id')){
                $user->state_id = $request->state_id;
            }

            $user->qrcode_url = Helper::qrCode(json_encode(["country_code" => $request->country_code, 'phone_number' => $request->mobile]), $user->id.'.png', Auth::user()->company_id);
            if($request->hasFile('picture')) {
                $user->picture = Helper::upload_file($request->file('picture'), 'user/profile', $user->id.'.png');
            }

            $country = CompanyCountry::where('company_id',Auth::user()->company_id)->where('country_id', $request->country_id)->first();
            $user->currency_symbol = $country->currency;
            if($request->has('resource_id')){
                $user->resource_id = $request->resource_id;
            }

            if($request->has('client_id')){
                $user->client_id = $request->client_id;
            }

            $user->save();


            $settings = json_decode(json_encode(Setting::where('company_id', $company_id)->first()->settings_data));
            $siteConfig = $settings->site;

            $store_api= $this->store_api($request->first_name,$request->last_name,$request->mobile,$request->email,$request->password,$siteConfig);

            if($store_api){
                
                $user->api_response =@$store_api['api_response'];        
                $user->client_id =@$store_api['clientId'];        
                $user->resource_id =@$store_api['resourceId'];       
                $user->account_id =@$store_api['savingsAccountId'];       
                $user->save();
            }

            $request->merge(["body" => "registered"]);
            if($request->has('email') && $request->has('mobile')) {
            $this->sendUserData($request->all());
            }
           
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);

        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }

    }

    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
           $user['city_data']=CompanyCity::where("country_id",$user['country_id'])->with('city')->get();

            return Helper::getResponse(['data' => $user]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
      
        $this->validate($request, [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'country_code' => 'required|max:25',
            'email' => $request->email != null ?'sometimes|required|email|max:255':'',
//            'mobile' => $request->mobile != null ?'sometimes|digits_between:6,13':'',
            'country_id' => 'required',
            'city_id' => 'required',
            // 'picture' => 'mimes:jpeg,jpg,bmp,png|max:5242880',
        ]);

         if($request->has('email')) {
            $request->merge([
                'email' => strtolower($request->email)
            ]);
        }
        $company_id=Auth::user()->company_id;
        if($request->has('email') && $request->has('mobile')) {
            $request->merge([
                'email' => $this->cusencrypt($request->email,env('DB_SECRET')),
                'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET')),
            ]);

            
            $email=$request->email;
            $mobile=$request->mobile;
            

            $this->validate($request, [          
                'email' =>[ Rule::unique('users')->where(function ($query) use($email,$company_id,$id) {
                                return $query->where('email', $email)->where('company_id', $company_id)->whereNotIn('id', [$id]);
                             }),
                           ],
//                'mobile' =>[ Rule::unique('users')->where(function ($query) use($mobile,$company_id,$id) {
//                                return $query->where('mobile', $mobile)->where('company_id', $company_id)->whereNotIn('id', [$id]);
//                             }),
  //                         ],
            ]);
        }

        try {
            if($request->has('email') && $request->has('mobile')) {
                $request->merge([
                    'email' => $this->cusdecrypt($request->email,env('DB_SECRET')),
                    'mobile' => $this->cusdecrypt($request->mobile,env('DB_SECRET')),
                ]);
            }
          
            $user = User::findOrFail($id);

            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            if($request->has('email') && $request->has('mobile')) {
            $user->country_code = $request->country_code;
            $user->email = $request->email;
            $user->mobile = $request->mobile;
            }
            $user->country_id = $request->country_id;
            $user->city_id = $request->city_id;
            $user->gender = $request->gender;

            if($request->has('resource_id')){
                $user->resource_id = $request->resource_id;
            }

            if($request->has('client_id')){
                $user->client_id = $request->client_id;
            }
            // if($request->password != "" && $request->password != null){

            //     $user->password = Hash::make($request->password);
            // }
            $user->qrcode_url = Helper::qrCode(json_encode(["country_code" => $request->country_code, 'phone_number' => $request->mobile]), $user->id.'.png', Auth::user()->company_id);
            if($request->hasFile('picture')) {
                $user->picture = Helper::upload_file($request->file('picture'), 'user/profile', $user->id.'.png');
            }

            $country = CompanyCountry::where('company_id', Auth::user()->company_id)->where('country_id', $request->country_id)->first();
            $user->currency_symbol = $country->currency;

            //CBS Change password
            if($request->password != "" && $request->password != null){
                $settings = json_decode(json_encode(Setting::where('company_id', $company_id)->first()->settings_data));
                $siteConfig = $settings->site;

                $CBS_changepassword=$this->changepassword_api($user->mobile,$user->first_name,$user->resource_id,$request->password,$siteConfig);
		\Log::info(@$CBS_changepassword);	
                \Log::info("------------".@$CBS_changepassword['resourceId']);

                if(empty(@$CBS_changepassword['changes']['passwordEncoded'])){

                    \Log::info("CBS Pass Error---");
                    \Log::info(@$CBS_changepassword['defaultUserMessage']);
                    \Log::info("------------");
                    
                    $data_err=@$CBS_changepassword['defaultUserMessage']?@$CBS_changepassword['defaultUserMessage']:'The submitted password has already been used in the past'; 
                    return Helper::getResponse(['status' => 404, 'message' => $data_err, 'error' => $data_err]);               
                }

                $enc_newpassword = Hash::make($request->password);
                $user->password = $enc_newpassword;
                $user->resource_id = @$CBS_changepassword['resourceId'];
                $user->save();
            }

            $user->save();

            $request->merge(["body"=>"updated"]);
            if($request->has('email') && $request->has('mobile')) {
            $this->sendUserData($request->all());
            }
            
            app('redis')->publish('message', json_encode($request->all()));
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        
        try {

            $datum = User::findOrFail($id);
            
            if($request->has('status')){
                if($request->status == 1){
                    $datum->status = 0;
                }else{
                    $datum->status = 1;
                }
            }
            $datum->save();

            if($request->status == 1){
                $status = "disabled";
                if($datum->jwt_token != null) {
                    Auth::guard('user')->setToken($datum->jwt_token);
                    try {
                        Auth::guard('user')->invalidate();
                    } catch (\Throwable $e) { }
                    
                    $datum->jwt_token = null;
                    $datum->save();
                }
            }else{
                $status = "enabled";
            }

            $datum['body'] = $status;
            
            $this->sendUserData($datum);

            (new SendPushNotification)->UserStatus($datum->id, 'provider', 'Account '.$status); 

            Helper::getResponse(['status' => 200, 'message' => trans('admin.activation_status')]);

        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
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
                \Log::info("Auth Token-----------");
                \Log::info($authentication->base64EncodedAuthenticationKey);
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
    public function destroy($id)
    {
        $datum = User::findOrFail($id);
       
        $datum['body'] = "deleted";
        $this->sendUserData($datum);

        return $this->removeModel($id);
    }


    public function multidestroy(Request $request)
    {
        $this->request = $request;
        return $this->removeMultiple();
    }

    public function statusChange(Request $request)
    {
        $this->request = $request;
        return $this->changeStatus();
    }

    public function statusChangeMultiple(Request $request)
    {
        $this->request = $request;
        return $this->changeStatusAll();
    }
    public function companyuser(Request $request)
    {
        $role = new Role;
        $role->name = strtoupper($request->name);
        $role->guard_name = $request->guard_name;
        $role->company_id = $request->company_id;
        $role->save();
        return Helper::getResponse(['status' => 200, 'message' => trans('Roles with company details created successfully')]);

    }

}
