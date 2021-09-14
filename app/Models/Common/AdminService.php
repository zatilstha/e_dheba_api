<?php

namespace App\Models\Common;

use App\Models\BaseModel;
use Auth;

class AdminService extends BaseModel
{
	protected $connection = 'common';

	protected $hidden = [
     	'company_id','created_type', 'created_by', 'modified_type', 'modified_by', 'deleted_type', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'
     ];

    public function providerservices() {
    	return $this->hasone('App\Models\Common\ProviderService','admin_service','admin_service')->where('provider_id',Auth::guard('provider')->user()->id);
    }

    public function menu() {
    	return $this->hasmany('App\Models\Common\Menu','admin_service','admin_service')->where('company_id', Auth::guard('user')->user()->company_id)->where('status',1)->orderby('sort_order');
    }
	
}
