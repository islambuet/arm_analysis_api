<?php
namespace App\Http\Controllers;
use App\Helpers\ConfigurationHelper;
use App\Helpers\TaskHelper;
use App\Helpers\UserHelper;
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
        $csvFile = file('D:\xampp8\htdocs\arm_exceltodb\analysis\excel/DIstributor Wise District Code.csv');
        foreach ($csvFile as $line) {
            $entry = str_getcsv($line);
            echo '<pre>';
            print_r($entry);
            echo '</pre>';

        }

    }
}
