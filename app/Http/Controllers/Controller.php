<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    function generateOTP()
    {
        return rand(10000, 99999);
    }

    public static function generateUID()
    {
        $randomNumber = random_int(100, 1000);
        $id = uniqid($randomNumber);
        return $id;
    }
}