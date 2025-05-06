<?php
namespace App\Http\Controllers\columns_control;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ColumnsControlController extends RootController
{
    public function saveItem(Request $request): JsonResponse
    {
        $itemOld=[];
        $itemNew=[];
        $itemNew['user_id']=$this->user->id;
        $itemNew['url'] =$request->input('url');
        $itemNew['method'] =$request->input('method');
        $itemNew['hidden_columns']=json_encode($request->input('hidden_columns',[]));

        $result = DB::table(TABLE_USER_HIDDEN_COLUMNS)->select('id','url','method','hidden_columns')
        ->where('user_id',$itemNew['user_id'])
        ->where('url',$itemNew['url'])
        ->where('method',$itemNew['method'])
        ->first();
        if($result){
            $itemOld=(array)$result;
            if($itemNew['hidden_columns']==$itemOld['hidden_columns']){
                return response()->json(['error' => 'INPUT_UNCHANGED', 'messages'=>__('Nothing changed to save.')]);
            }
        }
        DB::beginTransaction();
        try
        {
            $dataHistory=[];
            $dataHistory['table_name']=TABLE_USER_HIDDEN_COLUMNS;
            $dataHistory['controller']=(new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method']=__FUNCTION__;
            if($itemOld)
            {
                $itemNew['updated_at']=Carbon::now();
                DB::table(TABLE_USER_HIDDEN_COLUMNS)->where('id',$itemOld['id'])->update($itemNew);

                $dataHistory['table_id']=$itemOld['id'];
                $dataHistory['action']=DB_ACTION_EDIT;
            }
            else
            {
                $itemNew['created_at']=Carbon::now();
                $id = DB::table(TABLE_USER_HIDDEN_COLUMNS)->insertGetId($itemNew);
                $itemNew['id']=$id;

                $dataHistory['table_id']=$id;
                $dataHistory['action']=DB_ACTION_ADD;
            }
            $dataHistory['data_old']=json_encode($itemOld);
            $dataHistory['data_new']=json_encode($itemNew);
            $dataHistory['created_at']=Carbon::now();
            $dataHistory['created_by']=$this->user->id;
            $this->dBSaveHistory($dataHistory,TABLE_SYSTEM_HISTORIES);
            DB::commit();
            return response()->json(['error' => '','item' =>$itemNew]);
        }
        catch (\Exception $ex)
        {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages'=>__('Failed to save.')]);
        }
    }

}

