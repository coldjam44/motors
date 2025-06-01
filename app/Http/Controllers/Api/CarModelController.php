<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    public function getByMakeId($makeId)
    {
        // تحديد اللغة من إعدادات التطبيق أو من الهيدر، والافتراضية "ar"
        $locale = request()->header('Accept-Language', 'ar');
        $orderColumn = $locale === 'en' ? 'value_en' : 'value_ar';
    
        $models = CarModel::where('category_field_id', $makeId)
            ->orderBy($orderColumn, 'asc')
            ->get();
    
        return response()->json($models);
    }
    
}
