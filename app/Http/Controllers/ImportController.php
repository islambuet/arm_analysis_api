<?php
namespace App\Http\Controllers;
use App\Helpers\ConfigurationHelper;
use App\Helpers\TaskHelper;
use App\Helpers\UserHelper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{

    public $user;
    public function __construct()
    {
        ConfigurationHelper::load_config();
    }
    public function sendErrorResponse($errorResponse){
        $response = response()->json($errorResponse);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->send();
        exit;
    }
    public function distributors_sales()
    {
        die();
        //DB::table(TABLE_DISTRIBUTORS_SALES)->truncate();
        $time = Carbon::now();
        $page=4;
        $limit=20000;
        $start=($page-1)*$limit+1;//+1 means ignore first(0 row) record
        $row=$num_records = 0;
        $csvFile = file('F:\arm documents\analysis docs/Sale_Data_01.06.22_to_10.08.2025.csv');

        echo '<table border="1" cellpadding="2" cellspacing="2">';
        echo '<tr><td>#</td><td>$distributor_id</td><td>$pack_size_id</td><td>$sales_at</td><td>$invoice_no</td><td>Report</td></tr>';
        foreach ($csvFile as $line) {
            $row++;
            if($row<$start){
                continue;
            }
            if($row>=($start+$limit))
            {
                echo 'break';
                break;
            }
            $entry = str_getcsv($line);
            $itemNew['sales_at']=trim($entry[0]);
            $itemNew['invoice_no']=trim($entry[1]);
            $itemNew['distributor_id']= trim($entry[2]);
            $itemNew['pack_size_id']= trim($entry[6]);
            $itemNew['quantity']=trim($entry[8]);
            $itemNew['unit_price']=trim($entry[9]);
            $itemNew['amount']=trim($entry[10]);
            $itemNew['created_by'] = 1;
            $itemNew['created_at'] = $time;

            if(is_numeric($itemNew['distributor_id']) && $itemNew['distributor_id'] > 0 && is_numeric($itemNew['pack_size_id']) && $itemNew['pack_size_id']>0)
            {
                $num_records++;
                $insert_result  = DB::table(TABLE_DISTRIBUTORS_SALES)->insertGetId($itemNew);
                if($insert_result)
                {
                    $report = "Inserted";
                }
                else
                {
                    $report = "Failed";
                }
                echo '<tr><td>'.$row.'</td><td>'.$itemNew['distributor_id'].'</td><td>'.$itemNew['pack_size_id'].'</td><td>'.$itemNew['sales_at'].'</td><td>'.$itemNew['invoice_no'].'</td><td>'.$report.'</td></tr>';
            }
        }
        echo '</table>';
        echo 'Total Records: '.$num_records;
    }
    public function distributors_targets()
    {
        //die();
        DB::table(TABLE_DISTRIBUTORS_TARGETS)->truncate();
        $time = Carbon::now();
        $page=1;
        $limit=20000;
        $start=($page-1)*$limit+1;//+1 means ignore first(0 row) record
        $row=$num_records = 0;
        $csvFile = file('F:\arm documents\analysis docs/Target_FY-25-26.csv');

        echo '<table border="1" cellpadding="2" cellspacing="2">';
        echo '<tr><td>#</td><td>$distributor_id</td><td>variety_id</td><td>Report</td></tr>';
        foreach ($csvFile as $line) {
            $row++;
            if($row<$start){
                continue;
            }
            if($row>=($start+$limit))
            {
                echo 'break';
                break;
            }
            $entry = str_getcsv($line);
            $itemNew['distributor_id']= trim($entry[3]);
            $itemNew['fiscal_year']= 2025;
            $itemNew['variety_id']= trim($entry[6]);
            $itemNew['quantity']=is_numeric(trim($entry[8]))?trim($entry[8]):0;

            $itemNew['created_by'] = 1;
            $itemNew['created_at'] = $time;

            if(is_numeric($itemNew['distributor_id']) && $itemNew['distributor_id'] > 0 && is_numeric($itemNew['variety_id']) && $itemNew['variety_id']>0)
            {
                $num_records++;
                $insert_result  = DB::table(TABLE_DISTRIBUTORS_TARGETS)->insertGetId($itemNew);
                if($insert_result)
                {
                    $report = "Inserted";
                }
                else
                {
                    $report = "Failed";
                }
                echo '<tr><td>'.$row.'</td><td>'.$itemNew['distributor_id'].'</td><td>'.$itemNew['variety_id'].'</td><td>'.$report.'</td></tr>';
            }
        }
        echo '</table>';
        echo 'Total Records: '.$num_records;
    }
    public function incentive_varieties()
    {
        //die();
        DB::table(TABLE_INCENTIVE_VARIETIES)->truncate();
        $time = Carbon::now();
        $page=1;
        $limit=20000;
        $start=($page-1)*$limit+1;//+1 means ignore first(0 row) record
        $row=$num_records = 0;
        $csvFile = file('F:\arm documents\analysis docs/Criteria Sales Incentive Calculation Sheet FY-25-26.csv');

        echo '<table border="1" cellpadding="2" cellspacing="2">';
        echo '<tr><td>#</td><td>variety_id</td><td>incentive</td><td>Report</td></tr>';
        foreach ($csvFile as $line) {
            $row++;
            if($row<$start){
                continue;
            }
            if($row>=($start+$limit))
            {
                echo 'break';
                break;
            }
            $entry = str_getcsv($line);
            $itemNew['fiscal_year']= 2025;
            $itemNew['variety_id']= trim($entry[4]);
            $itemNew['incentive']=json_encode(["1"=>substr(trim($entry[7]),0,-1),"2"=>substr(trim($entry[8]),0,-1),"3"=>substr(trim($entry[9]),0,-1)]);

            $itemNew['created_by'] = 1;
            $itemNew['created_at'] = $time;

            if(is_numeric($itemNew['variety_id']) && $itemNew['variety_id'] > 0)
            {
                $num_records++;
                $insert_result  = DB::table(TABLE_INCENTIVE_VARIETIES)->insertGetId($itemNew);
                if($insert_result)
                {
                    $report = "Inserted";
                }
                else
                {
                    $report = "Failed";
                }
                echo '<tr><td>'.$row.'</td><td>'.$itemNew['variety_id'].'</td><td>'.$itemNew['incentive'].'</td><td>'.$report.'</td></tr>';
            }
        }
        echo '</table>';
        echo 'Total Records: '.$num_records;
    }
    public function market_size_setup_territory(){
        die();
        $query = DB::table(TABLE_LOCATION_UPAZILAS . ' as upazilas');
        $query->select('upazilas.*');
        $results = $query->get();
        $upazilas=[];
        $district_territory=[];
        $items=[];
        foreach ($results as $result) {
            $upazilas[$result->id]=$result;
            $district_territory[$result->district_id][$result->territory_id]=$result->territory_id;

            $items[$result->territory_id]=[];
        }


        $query=DB::table(TABLE_MARKET_SIZE_DATA.' as ad');
        $query->select('ad.*');
        $results=$query->get();
        foreach ($results as $result) {
            if (strlen($result->upazila_market_size) > 1) {
                $temp = explode(",", $result->upazila_market_size);
                foreach ($temp as $t) {
                    if (str_contains($t, '_')) {
                        $upazila_id=substr($t, 0, strpos($t, "_"));
                        $market_size=substr($t, strpos($t, "_") + 1);
                        if($upazila_id>0){
                            $territory_id=$upazilas[$upazila_id]->territory_id;
                            if(isset($items[$territory_id][$result->type_id])){
                                $items[$territory_id][$result->type_id]+=(+$market_size);
                            }
                            else{
                                $items[$territory_id][$result->type_id]=(+$market_size);
                            }
                        }
                        else{
                            $district_id=$result->district_id;
                            if(isset($district_territory[$district_id])){
                                foreach ($district_territory[$district_id] as $territory_id){
                                    if(isset($items[$territory_id][$result->type_id])){
                                        $items[$territory_id][$result->type_id]+=(+$market_size);
                                    }
                                    else{
                                        $items[$territory_id][$result->type_id]=(+$market_size);
                                    }
                                }
                            }
                        }


                    }
                }
            }
        }
        $time = Carbon::now();
        foreach ($items as $territory_id=>$info) {
            foreach ($info as $type_id=>$market_size){
                $itemNew=[];
                $itemNew['fiscal_year']=2025;
                $itemNew['type_id']=$type_id;
                $itemNew['territory_id']=$territory_id;
                $itemNew['market_size_total']=$market_size;
                $itemNew['created_by'] = 1;
                $itemNew['created_at'] = $time;
                DB::table(TABLE_MARKET_SIZE_TERRITORY)->insertGetId($itemNew);
            }
        }
        echo 'The end';
    }
}
