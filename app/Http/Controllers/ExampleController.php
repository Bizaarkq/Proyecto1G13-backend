<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class ExampleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    //

    public function index()
    {   
        $pdo = DB::connection()->getPdo();
        dd($pdo);
        
        return json_encode($pdo);
    }

}
