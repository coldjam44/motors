<?php

namespace App\Http\Controllers\Apis;

use App\Models\country;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class CountryController extends Controller
{
    public function index()
    {
        $categories = country::all();

        $categories->transform(function ($country) {
            $country->image = url('countrys/' . $country->image);
            return $country;
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
           'currency_ar' => 'required|string',
            'currency_en' => 'required|string',
            'image'   => 'required|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        $country = new country();
        $country->name_ar = $request->name_ar;
        $country->name_en = $request->name_en;
       $country->currency_ar = $request->currency_ar;
        $country->currency_en = $request->currency_en;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('countrys'), $imageName);
            $country->image = $imageName;
        }

        $country->save();

        return response()->json([
            'message' => 'تمت إضافة التصنيف بنجاح',
            'country' => [
                'id' => $country->id,
                'name_ar' => $country->name_ar,
                'name_en' => $country->name_en,
                'image' => url('countrys/' . $country->image)
            ]
        ], 201);
    }

    /**
     * تحديث تصنيف موجود.
     */
    public function update(Request $request, $id)
    {
        $country = country::findOrFail($id);

        $request->validate([
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
           'currency_ar' => 'required|string',
            'currency_en' => 'required|string',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        $country->name_ar = $request->name_ar;
        $country->name_en = $request->name_en;
       $country->currency_ar = $request->currency_ar;
        $country->currency_en = $request->currency_en;

        if ($request->hasFile('image')) {
            $oldImage = public_path('countrys/' . $country->image);
            if (File::exists($oldImage)) {
                File::delete($oldImage);
            }

            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('countrys'), $imageName);
            $country->image = $imageName;
        }

        $country->save();

        return response()->json([
            'message' => 'تم تحديث التصنيف بنجاح',
            'country' => [
                'id' => $country->id,
                'name_ar' => $country->name_ar,
                'name_en' => $country->name_en,
                'image' => url('countrys/' . $country->image)
            ]
        ], 200);
    }

    /**
     * حذف تصنيف.
     */
    public function destroy($id)
    {
        $country = country::findOrFail($id);

        $imagePath = public_path('countrys/' . $country->image);
        if (File::exists($imagePath)) {
            File::delete($imagePath);
        }

        $country->delete();

        return response()->json([
            'message' => 'تم حذف التصنيف بنجاح'
        ], 200);
    }
}

