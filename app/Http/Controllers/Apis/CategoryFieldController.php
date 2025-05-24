<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\CategoryFieldValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryFieldController extends Controller
{


    public function index($categoryId)
    {
        $category = Category::with('fields.values')->findOrFail($categoryId);

        // نجهز قائمة الفيلدات الحالية
        $fields = $category->fields->sortBy(function ($field) {
            if ($field->field_en === 'Model Year') return 0;
            if ($field->field_en === 'Make') return 1;
            return 2;
        })->values();

        // الحقول المطلوبة دائمًا
        $requiredFields = [
            'Model Year' => 'سنة الصنع',
            'Make' => 'الشركة المصنعة',
        ];

        foreach ($requiredFields as $fieldEn => $fieldAr) {
            if (!$fields->contains('field_en', $fieldEn)) {
                // ✅ إنشاء الفيلد في قاعدة البيانات
                $newField = $category->fields()->create([
                    'field_en' => $fieldEn,
                    'field_ar' => $fieldAr,
                ]);

                // أضفه لقائمة العرض مع قيم فاضية
                $newField->values = collect(); // قيم فاضية
                $fields->push($newField);
            }
        }

        // ترتيب نهائي
        $sortedFields = $fields->sortBy(function ($field) {
            if ($field['field_en'] === 'Model Year') return 0;
            if ($field['field_en'] === 'Make') return 1;
            return 2;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $sortedFields
        ]);
    }




    public function store(Request $request, $categoryId)
    {
        $request->validate([
            'field_ar' => 'required|string',
            'field_en' => 'required|string',
            'values' => 'nullable|array',
            'values.*.value_ar' => 'required_with:values.*.value_en|string|nullable',
            'values.*.value_en' => 'required_with:values.*.value_ar|string|nullable',
        ]);

        $category = Category::findOrFail($categoryId);

        $field = new CategoryField();
        $field->category_id = $category->id;
        $field->field_ar = $request->field_ar;
        $field->field_en = $request->field_en;
        $field->save();

        if ($request->has('values')) {
            foreach ($request->values as $value) {
                // لو القيمتين فاضيين ما تضيف
                if (empty($value['value_ar']) && empty($value['value_en'])) {
                    continue;
                }

                CategoryFieldValue::create([
                    'category_field_id' => $field->id,
                    'value_ar' => $value['value_ar'],
                    'value_en' => $value['value_en'],
                ]);
            }
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
            'values' => 'nullable|array', // allow null or empty array
            'values.*.value_ar' => 'required_with:values.*.value_en|string|nullable',
            'values.*.value_en' => 'required_with:values.*.value_ar|string|nullable',
        ]);

        $field = CategoryField::where('category_id', $categoryId)->findOrFail($fieldId);
        $field->field_ar = $request->field_ar;
        $field->field_en = $request->field_en;
        $field->save();

        // حذف القيم القديمة فقط إذا كانت هناك قيم جديدة
        $field->values()->delete();

        if (!empty($request->values)) {
            foreach ($request->values as $value) {
                if (!empty($value['value_ar']) && !empty($value['value_en'])) {
                    CategoryFieldValue::create([
                        'category_field_id' => $field->id,
                        'value_ar' => $value['value_ar'],
                        'value_en' => $value['value_en'],
                    ]);
                }
            }
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

    public function storeCarModels(Request $request, $categoryId, $makeId)
    {
        $validator = \Validator::make($request->all(), [
            'make_ar' => 'required|array',
            'make_en' => 'required|array',
            'make_ar.*' => 'required|string',
            'make_en.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'ar' => $validator->errors()->toArray(),
                    'en' => $validator->errors()->toArray(),
                ]
            ], 422);
        }

        // بدون تحقق من الفئة أو الشركة المصنعة لتبسيط
        $insertData = [];

        foreach ($request->make_ar as $index => $arName) {
            $enName = $request->make_en[$index];

            $insertData[] = [
                'category_field_id' => $makeId, // تأكد فقط أن $makeId يشير إلى الـ category_field_value الصحيح
                'value_ar' => $arName,
                'value_en' => $enName,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \DB::table('car_models')->insert($insertData);

        return response()->json([
            'success' => true,
            'message' => [
                'ar' => 'تمت إضافة الموديلات بنجاح',
                'en' => 'Models added successfully',
            ],
        ]);
    }

    public function deleteCarModel($modelId)
    {
        $deleted = \DB::table('car_models')->where('id', $modelId)->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => [
                    'ar' => 'تم حذف الموديل بنجاح',
                    'en' => 'Model deleted successfully',
                ],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'errors' => [
                    'ar' => 'الموديل غير موجود',
                    'en' => 'Model not found',
                ],
            ], 404);
        }
    }
}
