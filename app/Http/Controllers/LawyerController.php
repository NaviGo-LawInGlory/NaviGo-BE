<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lawyer;

class LawyerController extends Controller
{
    public function index()
{
    return response()->json(Lawyer::all());
}

}
