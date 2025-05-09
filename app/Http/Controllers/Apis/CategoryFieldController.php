<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\CategoryFieldValue;
use Illuminate\Http\Request;

class CategoryFieldController extends Controller
{
    // ✅ عرض جميع الحقول لفئة معينة
 public function index($categoryId)
{
    $category = Category::with('fields.values')->findOrFail($categoryId);

    // ترتيب الحقول بحيث يكون "Model Year" في الفهرس 0 و "Make" في الفهرس 1
    $fields = $category->fields->sortBy(function ($field) {
        if ($field->field_en === 'Model Year') return 0;
        if ($field->field_en === 'Make') return 1;
             // if ($field->field_en === 'Kilometers') return 2;

        return 2; // باقي الحقول تأتي بعدهم
    })->values(); // إعادة ضبط الفهارس بعد الفرز

    return response()->json([
        'success' => true,
        'data' => $fields
    ]);
}



    // ✅ إضافة حقل جديد إلى الفئة
    public function store(Request $request, $categoryId)
    {
        $request->validate([
            'field_ar' => 'required|string',
            'field_en' => 'required|string',
            'values' => 'required|array',
            'values.*.value_ar' => 'required|string',
            'values.*.value_en' => 'required|string',
        ]);

        $category = Category::findOrFail($categoryId);

        $field = new CategoryField();
        $field->category_id = $category->id;
        $field->field_ar = $request->field_ar;
        $field->field_en = $request->field_en;
        $field->save();

        foreach ($request->values as $value) {
            CategoryFieldValue::create([
                'category_field_id' => $field->id,
                'value_ar' => $value['value_ar'],
                'value_en' => $value['value_en'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة الحقل بنجاح',
            'data' => $field
        ]);
    }

    // ✅ تحديث حقل معين وقيمه
    public function update(Request $request, $categoryId, $fieldId)
    {
        $request->validate([
            'field_ar' => 'required|string',
            'field_en' => 'required|string',
            'values' => 'required|array',
            'values.*.value_ar' => 'required|string',
            'values.*.value_en' => 'required|string',
        ]);

        $field = CategoryField::where('category_id', $categoryId)->findOrFail($fieldId);
        $field->field_ar = $request->field_ar;
        $field->field_en = $request->field_en;
        $field->save();

        // حذف القيم القديمة وإضافة الجديدة
        $field->values()->delete();
        foreach ($request->values as $value) {
            CategoryFieldValue::create([
                'category_field_id' => $field->id,
                'value_ar' => $value['value_ar'],
                'value_en' => $value['value_en'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الحقل بنجاح',
            'data' => $field
        ]);
    }

    // ✅ حذف حقل معين
    public function destroy($categoryId, $fieldId)
    {
        $field = CategoryField::where('category_id', $categoryId)->findOrFail($fieldId);
        $field->values()->delete();
        $field->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الحقل بنجاح'
        ]);
    }
}
