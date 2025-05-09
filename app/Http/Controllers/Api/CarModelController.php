<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    public function getByMakeId($makeId)
    {
        $models = CarModel::where('category_field_id', $makeId)->get();
        return response()->json($models);
    }
}
