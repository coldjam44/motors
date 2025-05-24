<?php

namespace App\Http\Controllers\Apis;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class CategoryController extends Controller
{
    /**
     * عرض جميع التصنيفات مع روابط الصور.
     */
    public function index()
    {
        $customOrder = [
            1 => 'Cars',
            3 => 'Classic Cars',
            10 => 'Number Plates',
            5 => 'Bikes',
            7 => 'Jet Ski',
            8 => 'Marine Engine',
            2 => 'Boot & Yacht',
            9 => 'Heavy Machinery',
            13 => 'Rent a Car',
            14 => 'Taxi on Apps',
            11 => 'Spare Parts',
            12 => 'Accessories',
            15 => 'Service and Repair',
            6 => 'Trailers',
            16 => 'Scrap',
        ];

        $orderedIds = array_keys($customOrder);

        // Get categories in the custom order
        $orderedCategories = Category::whereIn('id', $orderedIds)
            ->orderByRaw('FIELD(id, ' . implode(',', $orderedIds) . ')')
            ->get();

        // Get categories not in the custom order
        $remainingCategories = Category::whereNotIn('id', $orderedIds)
            ->orderBy('id', 'asc') // or any other order
            ->get();

        // Merge both collections
        $categories = $orderedCategories->concat($remainingCategories);

        // Transform the image URLs
        $categories->transform(function ($category) {
            $category->image = url('categorys/' . $category->image);
            return $category;
        });

        return response()->json([
            'categories' => $categories
        ], 200);
    }


    /**
     * إضافة تصنيف جديد.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'image'   => 'required|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        $category = new Category();
        $category->name_ar = $request->name_ar;
        $category->name_en = $request->name_en;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('categorys'), $imageName);
            $category->image = $imageName;
        }

        $category->save();

        return response()->json([
            'message' => 'تمت إضافة التصنيف بنجاح',
            'category' => [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'image' => url('categorys/' . $category->image)
            ]
        ], 201);
    }

    /**
     * تحديث تصنيف موجود.
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        $category->name_ar = $request->name_ar;
        $category->name_en = $request->name_en;

        if ($request->hasFile('image')) {
            $oldImage = public_path('categorys/' . $category->image);
            if (File::exists($oldImage)) {
                File::delete($oldImage);
            }

            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('categorys'), $imageName);
            $category->image = $imageName;
        }

        $category->save();

        return response()->json([
            'message' => 'تم تحديث التصنيف بنجاح',
            'category' => [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'image' => url('categorys/' . $category->image)
            ]
        ], 200);
    }

    /**
     * حذف تصنيف.
     */
    public function destroy($id)
    {
        $category = category::findOrFail($id);

        $imagePath = public_path('categorys/' . $category->image);
        if (File::exists($imagePath)) {
            File::delete($imagePath);
        }

        $category->delete();

        return response()->json([
            'message' => 'تم حذف التصنيف بنجاح'
        ], 200);
    }

    public function toggleKilometersApi($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $category->has_kilometers = !$category->has_kilometers;
        $category->save();

        return response()->json([
            'message' => 'Kilometer field status updated successfully',
            'category_id' => $category->id,
            'has_kilometers' => $category->has_kilometers,
        ]);
    }

    public function listMakes($categoryId)
    {
        // جلب الفئة مع الحقول والقيم الخاصة بها (نفترض أن 'Make' هو اسم الحقل)
        $category = Category::with(['fields.values'])->findOrFail($categoryId);

        // البحث عن حقل 'Make'
        $makeField = $category->fields->firstWhere('field_en', 'Make');

        if (!$makeField) {
            return response()->json([
                'success' => false,
                'message' => 'Make field not found for this category.'
            ], 404);
        }

        // جلب القيم الخاصة بحقل الـ Make (الشركات المصنعة)
        $makes = $makeField->values->map(function ($value) {
            return [
                'id' => $value->id,
                'name_ar' => $value->value_ar,
                'name_en' => $value->value_en,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $makes
        ]);
    }
}
