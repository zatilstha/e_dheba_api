<?php

namespace App\Http\Controllers\V1\Common\Admin\Resource;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Traits\Actions;
use App\Models\Common\Document;
use App\Helpers\Helper;
use Auth;

class DocumentController extends Controller
{
    use Actions;
    private $model;
    private $request;
    
    public function __construct(Document $model)
    {
        $this->model = $model;
    }

    public function index(Request $request)
    {
        

        $datum = Document::whereHas('service' ,function($query){  
            $query->where('status',1);  
        })->where('company_id', Auth::user()->company_id);


        if($request->has('search_text') && $request->search_text != null) {
            $datum->Search($request->search_text);
        }

        if($request->has('order_by')) {
            $datum->orderby($request->order_by, $request->order_direction);
        }
        
        if($request->has('page') && $request->page == 'all') {
            $data = $datum->get();
        } else {
            $data = $datum->paginate(10);
        }
        
        return Helper::getResponse(['data' => $data]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
             'name' => 'required|max:255',
             'type' => 'required|in:TRANSPORT,ORDER,SERVICE,ALL',
        ]);

        try{
            $document = new Document;
            $document->name = $request->name;  
            $document->company_id = Auth::user()->company_id;                    
            $document->type = $request->type;
            $document->service = $request->type;   
            $document->file_type = $request->file_type;
            $document->is_backside = $request->is_backside;
            $document->save();
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $document = Document::findOrFail($id);
            return Helper::getResponse(['data' => $document]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'type' => 'required|in:TRANSPORT,ORDER,SERVICE,ALL',
        ]);
    
        try {
            Document::where('id',$id)->update([
                    'name' => $request->name,
                    'type' => $request->type,
                ]);
            
            $document = Document::where('id',$id)->first();
            $document->name = $request->name;                   
            $document->type = $request->type;  
            $document->service = $request->type;  
            $document->file_type = $request->file_type;
            if($request->has('is_backside')){
                $document->is_backside = $request->is_backside;
            }else{
                $document->is_backside = null;
            }
            
            $document->save();

            return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
            } catch (\Throwable $e) {
                return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
            }
    }

    public function destroy($id)
    {
        return $this->removeModel($id);
    }

    public function updateStatus(Request $request, $id)
    {
        
        try {

            $datum = Document::findOrFail($id);
            
            if($request->has('status')){
                if($request->status == 1){
                    $datum->status = 0;
                }else{
                    $datum->status = 1;
                }
            }
            $datum->save();
           
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.activation_status')]);

        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }
   
}
