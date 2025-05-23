<?php

namespace App\Http\Controllers\Api;

use App\Models\Ad;
use App\Models\Userauth;
use App\Models\Category;
use App\Models\country;
use App\Models\city;
use App\Models\CategoryFieldValue;
use App\Models\Follower;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\DB;

use App\Models\AdImage;
use App\Models\AdView;
use App\Models\Notification;
use App\Models\CategoryField;

use App\Models\Favorite;
use App\Models\AdFieldValue;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\AdFeature;

class AdController extends Controller
{



    public function store(Request $request)
    {
        // استرجاع المستخدم من التوكن
        $token = request()->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent'], 401);
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // التحقق من البيانات المطلوبة
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'address' => 'required|string',
            'price' => 'required|numeric',
            'phone_number' => 'nullable|string|max:20',
            'kilometer' => 'nullable|string',
            'main_image' => 'required|image',
            'sub_images.*' => 'image',
            'fields' => 'required|array',
            'car_model' => 'nullable|string|max:255',
            'fields.*.category_field_id' => 'required|exists:category_fields,id',
            'fields.*.category_field_value_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        // افتراضياً نستخدم ID المستخدم الحالي
        $adUserId = $user->id;

        // إذا تم تمرير user_id في الطلب
        if ($request->has('user_id')) {

            // فقط الإدمن يقدر يحدد user_id
            if ($user->role !== 'admin') {
                return response()->json([
                    'message_ar' => 'فقط المدراء يمكنهم تعيين الإعلانات لمستخدمين آخرين',
                    'message_en' => 'Only admins can assign ads to other users'
                ], 403);
            }

            // التحقق من أن user_id موجود
            $requestedUser = Userauth::find($request->user_id);
            if (!$requestedUser) {
                return response()->json([
                    'message_ar' => 'المستخدم المحدد غير موجود',
                    'message_en' => 'The provided user_id is invalid'
                ], 422);
            }

            // نستخدم الـ user_id المحدد من قبل الإدمن
            $adUserId = $requestedUser->id;
        }


        // حفظ الصورة الرئيسية مع العلامة المائية وتغيير الأبعاد
        $mainImage = $request->file('main_image');
        $mainImageName = time() . '_' . $mainImage->getClientOriginalName();
        $mainImagePath = public_path('ads/' . $mainImageName);

        $image = Image::make($mainImage->getRealPath());
        $image->resize(2000, 1300, function ($constraint) {
            $constraint->aspectRatio(); // الحفاظ على نسبة العرض إلى الارتفاع
            // لا نستخدم $constraint->upsize() للسماح بالتكبير
        });
        $image->insert(public_path('watermark.png'), 'center'); // إضافة العلامة المائية
        $image->save($mainImagePath);

        // إنشاء الإعلان
        $ad = Ad::create([
            'user_id' => $adUserId,
            'category_id' => $request->category_id,
            'country_id' => $request->country_id,
            'city_id' => $request->city_id,
            'title' => $request->title,
            'description' => $request->description,
            'address' => $request->address,
            'kilometer' => $request->kilometer,
            'price' => $request->price,
            'phone_number' => $request->phone_number,
            'car_model' => $request->car_model,
            'status' => 'pending',
            'main_image' => 'ads/' . $mainImageName,
        ]);

        // إنشاء إشعار للمستخدم بأن الإعلان قيد المراجعة
        Notification::create([
            'user_id' => $adUserId,
            'from_user_id' => null,
            'type' => 'ad_status',
            'message_ar' => 'إعلانك قيد المراجعة!',
            'message_en' => 'Your ad is under review!',
            'ad_id' => $ad->id,
            'is_read' => false,
        ]);

        // إذا كانت حالة الإعلان "approved" نرسل إشعار للمتابعين
        if ($ad->status === 'approved') {
            $followers = Follower::where('following_id', $user->id)->pluck('follower_id');
            foreach ($followers as $followerId) {
                Notification::create([
                    'user_id' => $followerId,
                    'from_user_id' => $user->id,
                    'ad_id' => $ad->id,
                    'type' => 'new_ad',
                    'message_ar' => "{$user->first_name} نشر إعلان جديد!",
                    'message_en' => "{$user->first_name} posted a new ad!",
                ]);
            }
        }


        if ($request->hasFile('sub_images')) {
            $subImages = $request->file('sub_images');

            if (is_array($subImages)) {
                foreach ($subImages as $subImage) {
                    $subImageName = 'sub_' . time() . '_' . $subImage->getClientOriginalName();
                    $subImagePath = public_path('ads/' . $subImageName);

                    $subImg = Image::make($subImage->getRealPath());
                    $subImg->resize(2000, 1300, function ($constraint) {
                        $constraint->aspectRatio(); // الحفاظ على نسبة العرض إلى الارتفاع
                        // لا نستخدم $constraint->upsize() للسماح بالتكبير
                    });
                    $subImg->insert(public_path('watermark.png'), 'center'); // إضافة العلامة المائية
                    $subImg->save($subImagePath);

                    // حفظ الصورة في قاعدة البيانات
                    AdImage::create([
                        'ad_id' => $ad->id,
                        'image' => 'ads/' . $subImageName,
                    ]);
                }
            }
        }



        foreach ($request->fields as $field) {
            $categoryField = CategoryField::find($field['category_field_id']);

            // تأكد أن الحقل موجود
            if (!$categoryField) {
                continue;
            }

            // لو الحقل من نوع select أو dropdown → نتوقع ID موجود
            if ($categoryField->input_type === 'select') {
                $categoryFieldValue = CategoryFieldValue::find($field['category_field_value_id']);

                if (!$categoryFieldValue) {
                    return response()->json([
                        'message' => 'القيمة المحددة غير موجودة لهذا الحقل',
                        'field_id' => $field['category_field_id']
                    ], 422);
                }
            } else {
                // حقل نصي → خزّن القيمة كـ نص حتى لو كانت رقم
                $categoryFieldValue = CategoryFieldValue::firstOrCreate([
                    'category_field_id' => $field['category_field_id'],
                    'value_ar' => $field['category_field_value_id'],
                    'value_en' => $field['category_field_value_id'],
                    'field_type' => 'text',
                ]);
            }

            AdFieldValue::create([
                'ad_id' => $ad->id,
                'category_field_id' => $field['category_field_id'],
                'category_field_value_id' => $categoryFieldValue->id,
            ]);
        }




        // إضافة المميزات الخاصة بالإعلان إذا كانت موجودة
        if ($request->has('car_options') && !empty($request->car_options)) {
            // تحويل النص المفصول بفواصل إلى مصفوفة، وتأكد من إزالة أي مسافات أو أقواس
            $featureIds = explode(',', $request->car_options);

            // استعراض كل ID في المصفوفة
            foreach ($featureIds as $featureId) {
                // إزالة أي مسافات بيضاء أو أقواس حول الـ ID
                $featureId = trim($featureId, " \t\n\r\0\x0B[]");

                // التحقق من أن الـ ID هو قيمة صحيحة (عدد صحيح)
                if (is_numeric($featureId)) {
                    // إضافة الميزة للإعلان باستخدام الـ ID
                    AdFeature::create([
                        'car_ad_id' => $ad->id,
                        'feature_id' => $featureId,
                    ]);
                }
            }
        }


        // بعد إنشاء الإعلان
        $admins = Userauth::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'from_user_id' => $user->id,  // المستخدم اللي أنشأ الإعلان
                'ad_id' => $ad->id,
                'type' => 'admin_new_ad', // نوع جديد مخصص للإدمنز
                'message_ar' => "تم إنشاء إعلان جديد بعنوان: " . $ad->title,
                'message_en' => "A new ad has been created titled: " . $ad->title,
                'is_read' => false,
            ]);
        }


        return response()->json(['message' => 'Ad created successfully', 'ad' => $ad], 201);
    }



    public function update(Request $request, $id)
    {
        $token = request()->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent'], 401);
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $ad = Ad::where('id', $id)->where('user_id', $user->id)->first();
        if (!$ad) {
            return response()->json(['message' => 'Ad not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'country_id' => 'sometimes|exists:countries,id',
            'city_id' => 'sometimes|exists:cities,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'address' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'phone_number' => 'nullable|string|max:20',
            'kilometer' => 'nullable|string',
            'main_image' => 'nullable|image',
            'sub_images.*' => 'image',
            'car_model' => 'nullable|string|max:255', // Added validation for car_model
            'fields' => 'nullable|array',
            'fields.*.category_field_id' => 'required_with:fields|exists:category_fields,id',
            'fields.*.category_field_value_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update ad with the car_model field included
        $ad->update($request->only([
            'category_id',
            'country_id',
            'city_id',
            'title',
            'description',
            'address',
            'kilometer',
            'price',
            'phone_number',
            'car_model', // Update car_model
        ]));

        // إذا قام المستخدم برفع صورة رئيسية جديدة
        if ($request->hasFile('main_image')) {
            // حذف الصورة القديمة إذا كانت موجودة
            if ($ad->main_image && file_exists(public_path($ad->main_image))) {
                unlink(public_path($ad->main_image));
            }

            // رفع الصورة الجديدة
            $mainImage = $request->file('main_image');
            $mainImageName = time() . '_' . $mainImage->getClientOriginalName();
            $mainImagePath = public_path('ads/' . $mainImageName);

            $image = Image::make($mainImage->getRealPath());
            $image->resize(2000, 1300, function ($constraint) {
                $constraint->aspectRatio(); // الحفاظ على نسبة العرض إلى الارتفاع
            });
            $image->insert(public_path('watermark.png'), 'center'); // إضافة العلامة المائية
            $image->save($mainImagePath);

            $ad->status = 'pending';
            $ad->main_image = 'ads/' . $mainImageName;
            $ad->save();
        }

        // إذا قام المستخدم برفع صور فرعية جديدة
        if ($request->hasFile('sub_images')) {
            // حذف الصور الفرعية القديمة
            foreach ($ad->subImages as $image) {
                if (file_exists(public_path($image->image))) {
                    unlink(public_path($image->image));
                }
                $image->delete();
            }

            // رفع الصور الفرعية الجديدة
            foreach ($request->file('sub_images') as $subImage) {
                $subImageName = 'sub_' . time() . '_' . $subImage->getClientOriginalName();
                $subImagePath = public_path('ads/' . $subImageName);

                $subImg = Image::make($subImage->getRealPath());
                $subImg->resize(2000, 1300, function ($constraint) {
                    $constraint->aspectRatio(); // الحفاظ على نسبة العرض إلى الارتفاع
                });
                $subImg->insert(public_path('watermark.png'), 'center'); // إضافة العلامة المائية
                $subImg->save($subImagePath);

                AdImage::create([
                    'ad_id' => $ad->id,
                    'image' => 'ads/' . $subImageName,
                ]);
            }
        }

        // إذا كانت car_options موجودة في الطلب وليست فارغة
        if ($request->has('car_options') && !empty($request->car_options)) {
            // تحويل النص المفصول بفواصل إلى مصفوفة
            $featureIds = explode(',', $request->car_options);

            // حذف المميزات القديمة المرتبطة بالإعلان
            DB::table('car_ad_features')->where('car_ad_id', $ad->id)->delete();

            // استعراض كل feature_id في المصفوفة
            foreach ($featureIds as $featureId) {
                // إزالة أي مسافات بيضاء أو أقواس حول الـ ID
                $featureId = trim($featureId, " \t\n\r\0\x0B[]");

                // التحقق من أن الـ ID هو قيمة صحيحة (عدد صحيح)
                if (is_numeric($featureId)) {
                    // إضافة الميزة للإعلان باستخدام الـ feature_id
                    DB::table('car_ad_features')->insert([
                        'car_ad_id' => $ad->id,
                        'feature_id' => $featureId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($request->has('fields')) {
            AdFieldValue::where('ad_id', $ad->id)->delete();

            foreach ($request->fields as $field) {
                $categoryField = CategoryField::find($field['category_field_id']);

                if (!$categoryField) {
                    continue; // إذا الحقل غير موجود، تجاهله
                }

                if ($categoryField->input_type === 'select') {
                    $categoryFieldValue = CategoryFieldValue::find($field['category_field_value_id']);

                    if (!$categoryFieldValue) {
                        return response()->json([
                            'message' => 'القيمة المحددة غير موجودة لهذا الحقل',
                            'field_id' => $field['category_field_id']
                        ], 422);
                    }
                } else {
                    $categoryFieldValue = CategoryFieldValue::firstOrCreate([
                        'category_field_id' => $field['category_field_id'],
                        'value_ar' => $field['category_field_value_id'],
                        'value_en' => $field['category_field_value_id'],
                        'field_type' => 'text',
                    ]);
                }

                AdFieldValue::create([
                    'ad_id' => $ad->id,
                    'category_field_id' => $field['category_field_id'],
                    'category_field_value_id' => $categoryFieldValue->id,
                ]);
            }
        }

        if ($ad->status === 'pending') {
            $admins = Userauth::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'from_user_id' => $user->id,
                    'type' => 'ad_review',
                    'message_ar' => "يوجد إعلان جديد قيد المراجعة من المستخدم {$user->first_name}",
                    'message_en' => "A new ad is pending review from user {$user->first_name}",
                    'ad_id' => $ad->id,
                    'is_read' => false,
                ]);
            }


            // إشعار المستخدم نفسه
            Notification::create([
                'user_id' => $user->id,
                'from_user_id' => null,
                'type' => 'ad_status',
                'message_ar' => 'إعلانك قيد المراجعة!',
                'message_en' => 'Your ad is under review!',
                'ad_id' => $ad->id,
                'is_read' => false,
            ]);
        }


        return response()->json(['message' => 'Ad updated successfully', 'ad' => $ad], 200);
    }

    public function destroyadmin($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired | انتهت صلاحية التوكن'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Invalid token | التوكن غير صالح'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token not provided | لم يتم توفير التوكن'], 401);
        }

        // التحقق إن المستخدم أدمن
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Access denied: admin only | الوصول مرفوض: الأدمن فقط'], 403);
        }

        // البحث عن الإعلان
        $ad = Ad::find($id);
        if (!$ad) {
            return response()->json(['message' => 'Ad not found | الإعلان غير موجود'], 404);
        }

        // حذف الصور الفرعية
        AdImage::where('ad_id', $ad->id)->delete();

        // حذف القيم المرتبطة بالحقول
        AdFieldValue::where('ad_id', $ad->id)->delete();

        // حذف الإعلان
        $ad->delete();

        return response()->json(['message' => 'Ad deleted successfully | تم حذف الإعلان بنجاح'], 200);
    }










    public function index()
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Retrieve ads for the authenticated user, ordered by `created_at` (newest first)
        $ads = Ad::with(['subImages', 'fieldValues', 'views'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc') // Order by `created_at` in descending order
            ->get();

        $ads->transform(function ($ad) {
            // Calculate unique views for the ad
            $ad->views_count = $ad->views()->distinct('user_id')->count();

            // Format main image as a direct URL
            $ad->main_image = $ad->main_image ? url($ad->main_image) : null;

            // Format sub-images as direct URLs
            $ad->subImages->transform(fn($image) => ['image' => url($image->image)]);

            // Retrieve field values associated with the ad
            $ad->fieldValues->transform(fn($fieldValue) => [
                'field_id' => $fieldValue->category_field_id,
                'field_name' => [
                    'ar' => optional($fieldValue->field)->field_ar ?? 'غير معروف',
                    'en' => optional($fieldValue->field)->field_en ?? 'Unknown',
                ],
                'field_value' => [
                    'ar' => optional($fieldValue->fieldValue)->value_ar ?? 'غير معروف',
                    'en' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                ],
            ]);

            return [
                'id' => $ad->id,
                'user_id' => $ad->user_id,
                'title' => $ad->title,
                'description' => $ad->description,
                'address' => $ad->address,
                'price' => $ad->price,
                'phone_number' => $ad->phone_number,
                'kilometer' => $ad->kilometer,
                'status' => $ad->status,
                'main_image' => $ad->main_image,
                'sub_images' => $ad->subImages,
                'details' => $ad->fieldValues,
                'views_count' => $ad->views_count, // Unique views count
            ];
        });

        return response()->json(['ads' => $ads]);
    }

    public function destroy($id)
    {
        // استرجاع المستخدم من التوكن
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // البحث عن الإعلان
        $ad = Ad::with('subImages')->where('id', $id)->where('user_id', $user->id)->first();

        if (!$ad) {
            return response()->json(['message' => 'Ad not found or unauthorized'], 404);
        }

        // حذف الصورة الرئيسية من السيرفر
        if ($ad->main_image && file_exists(public_path($ad->main_image))) {
            unlink(public_path($ad->main_image));
        }

        // حذف الصور الفرعية من السيرفر
        foreach ($ad->subImages as $image) {
            if (file_exists(public_path($image->image))) {
                unlink(public_path($image->image));
            }
            $image->delete();
        }

        // حذف الحقول المرتبطة
        AdFieldValue::where('ad_id', $ad->id)->delete();

        // حذف الإعلان
        $ad->delete();

        return response()->json(['message' => 'Ad deleted successfully'], 200);
    }



    public function indexadsusers(Request $request)
    {
        $query = Ad::with(['subImages', 'fieldValues', 'user'])
            ->withCount('views'); // جلب عدد المشاهدات  

        // ✅ الفلاتر الأساسية
        $filters = [
            'category_id' => Category::where('id', $request->category_id)->exists(),
            'country_id' => Country::where('id', $request->country_id)->exists(),
            'city_id' => City::where('id', $request->city_id)->exists(),
            'status' => in_array($request->status, ['pending', 'approved', 'rejected']), // مثال لحالة الإعلان
        ];

        // ✅ التحقق من القيم المدخلة
        foreach ($filters as $key => $isValid) {
            if ($request->has($key) && !$isValid) {
                return response()->json(['ads' => [], 'pagination' => []], 200);
            }
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }
        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('min_kilometer')) {
            $query->where('kilometer', '>=', (int) $request->min_kilometer);
        }
        if ($request->has('max_kilometer')) {
            $query->where('kilometer', '<=', (int) $request->max_kilometer);
        }


        // ✅ فلترة حسب `id` الحقل و `id` القيمة
        if ($request->has('fields')) {
            $fields = $request->input('fields'); // المصفوفة المستقبلة: [field_id => value_id]

            foreach ($fields as $fieldId => $valueId) {
                $isValidField = CategoryField::where('id', $fieldId)->exists();
                $isValidValue = CategoryFieldValue::where('id', $valueId)->exists();

                // إذا كان أي من القيم غير صالحة، نُرجع استجابة فارغة
                if (!$isValidField || !$isValidValue) {
                    return response()->json(['ads' => [], 'pagination' => []], 200);
                }
            }

            foreach ($fields as $fieldId => $valueId) {
                $query->whereHas('fieldValues', function ($q) use ($fieldId, $valueId) {
                    $q->where('category_field_id', $fieldId)
                        ->where('category_field_value_id', $valueId);
                });
            }
        }

        // تنفيذ الاستعلام مع الترقيم (كل صفحة بها 7 إعلانات)
        $ads = $query->paginate(15)->withQueryString();

        // تحويل البيانات  
        $ads->getCollection()->transform(function ($ad) {
            $ad->main_image = $ad->main_image ? url($ad->main_image) : null;

            // جلب أحدث إعلان للمستخدم  
            $latestAd = Ad::where('user_id', $ad->user_id)->latest('created_at')->first();

            // تحويل الصور الفرعية  
            $ad->subImages->transform(function ($image) {
                $image->image = url($image->image);
                return $image;
            });

            // تحويل تفاصيل الحقول  
            $ad->fieldValues->transform(function ($fieldValue) {
                return [
                    'field_id' => $fieldValue->category_field_id,
                    'field_name' => [
                        'ar' => optional($fieldValue->field)->field_ar ?? 'غير معروف',
                        'en' => optional($fieldValue->field)->field_en ?? 'Unknown',
                    ],
                    'field_value' => [
                        'id' => $fieldValue->category_field_value_id,
                        'ar' => optional($fieldValue->fieldValue)->value_ar ?? 'غير معروف',
                        'en' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                    ],
                ];
            });

            return [
                'id' => $ad->id,
                'user_id' => $ad->user_id,
                'user_name' => trim(optional($ad->user)->first_name . ' ' . optional($ad->user)->last_name) ?: null,
                'user_image' => optional($ad->user)->profile_image ? url('profile_images/' . $ad->user->profile_image) : null,
                'user_registered_at' => optional($ad->user)->created_at ?? null, // أول تسجيل للمستخدم
                'last_ad_posted_at' => optional($latestAd)->created_at ?? null, // آخر إعلان تم نشره
                'title' => $ad->title,
                'description' => $ad->description,
                'address' => $ad->address,
                'price' => $ad->price,
                'phone_number' => $ad->phone_number,
                'kilometer' => $ad->kilometer,

                'status' => $ad->status,
                'main_image' => $ad->main_image,
                'sub_images' => $ad->subImages,
                'details' => $ad->fieldValues,
                'view_count' => $ad->views_count, // عدد المشاهدات الفريدة
            ];
        });

        return response()->json([
            'ads' => $ads->items(),  // الإعلانات في الصفحة الحالية  
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ]);
    }


    public function indexadsusersByViews(Request $request)
    {
        $query = Ad::with(['subImages', 'fieldValues', 'user'])
            ->withCount('views') // جلب عدد المشاهدات
            ->where('status', 'approved') // ✅ جلب الإعلانات الموافق عليها فقط
            ->orderByDesc('views_count'); // ترتيب تنازلي حسب عدد المشاهدات  

        // ✅ التحقق من القيم المدخلة
        $filters = [
            'category_id' => Category::where('id', $request->category_id)->exists(),
            'country_id' => Country::where('id', $request->country_id)->exists(),
        ];

        foreach ($filters as $key => $isValid) {
            if ($request->has($key) && !$isValid) {
                return response()->json(['ads' => [], 'pagination' => []], 200);
            }
        }

        // ✅ تطبيق الفلاتر المطلوبة
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // تنفيذ الاستعلام مع الترقيم (كل صفحة بها 10 إعلانات)
        $ads = $query->paginate(10);

        // تحويل البيانات  
        $ads->getCollection()->transform(function ($ad) {
            $ad->main_image = $ad->main_image ? url($ad->main_image) : null;

            // جلب أحدث إعلان للمستخدم  
            $latestAd = Ad::where('user_id', $ad->user_id)->latest('created_at')->first();

            // تحويل الصور الفرعية  
            $ad->subImages->transform(function ($image) {
                $image->image = url($image->image);
                return $image;
            });

            // تحويل تفاصيل الحقول  
            $ad->fieldValues->transform(function ($fieldValue) {
                return [
                    'field_id' => $fieldValue->category_field_id,
                    'field_name' => [
                        'ar' => optional($fieldValue->field)->field_ar ?? 'غير معروف',
                        'en' => optional($fieldValue->field)->field_en ?? 'Unknown',
                    ],
                    'field_value' => [
                        'id' => $fieldValue->category_field_value_id,
                        'ar' => optional($fieldValue->fieldValue)->value_ar ?? 'غير معروف',
                        'en' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                    ],
                ];
            });

            return [
                'id' => $ad->id,
                'user_id' => $ad->user_id,
                'user_name' => trim(optional($ad->user)->first_name . ' ' . optional($ad->user)->last_name) ?: null,
                'user_image' => optional($ad->user)->profile_image ? url('profile_images/' . $ad->user->profile_image) : null,
                'user_registered_at' => optional($ad->user)->created_at ?? null,
                'last_ad_posted_at' => optional($latestAd)->created_at ?? null,
                'title' => $ad->title,
                'description' => $ad->description,
                'address' => $ad->address,
                'kilometer' => $ad->kilometer,

                'price' => $ad->price,
                'phone_number' => $ad->phone_number,
                'status' => $ad->status,
                'main_image' => $ad->main_image,
                'sub_images' => $ad->subImages,
                'details' => $ad->fieldValues,
                'view_count' => $ad->views_count, // عدد المشاهدات الفريدة
            ];
        });

        return response()->json([
            'ads' => $ads->items(),
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ]);
    }


    public function indexAdsGroupedByCategory(Request $request)
    {
        // جلب كل التصنيفات مع الفلترة حسب الدولة إذا كانت موجودة
        $categories = Category::with(['ads' => function ($query) use ($request) {
            // إذا كان هناك country_id في الطلب، يتم تصفية الإعلانات حسب الدولة
            if ($request->has('country_id')) {
                $query->where('country_id', $request->country_id);
            }

            $query->with(['subImages', 'fieldValues', 'user'])
                ->withCount('views')
                ->where('status', 'approved')
                ->orderByDesc('views_count');
        }])->get();

        // تجهيز البيانات بالشكل المطلوب
        $result = $categories->map(function ($category) {
            return [
                'category_id' => $category->id,
                'category_name_ar' => $category->name_ar,
                'category_name_en' => $category->name_en,

                'ads' => $category->ads->map(function ($ad) {
                    $ad->main_image = $ad->main_image ? url($ad->main_image) : null;

                    // جلب أحدث إعلان للمستخدم
                    $latestAd = Ad::where('user_id', $ad->user_id)->latest('created_at')->first();

                    $ad->subImages->transform(function ($image) {
                        $image->image = url($image->image);
                        return $image;
                    });

                    $ad->fieldValues->transform(function ($fieldValue) {
                        return [
                            'field_id' => $fieldValue->category_field_id,
                            'field_name' => [
                                'ar' => optional($fieldValue->field)->field_ar ?? 'غير معروف',
                                'en' => optional($fieldValue->field)->field_en ?? 'Unknown',
                            ],
                            'field_value' => [
                                'id' => $fieldValue->category_field_value_id,
                                'ar' => optional($fieldValue->fieldValue)->value_ar ?? 'غير معروف',
                                'en' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                            ],
                        ];
                    });

                    return [
                        'id' => $ad->id,
                        'user_id' => $ad->user_id,
                        'user_name' => trim(optional($ad->user)->first_name . ' ' . optional($ad->user)->last_name) ?: null,
                        'user_image' => optional($ad->user)->profile_image ? url('profile_images/' . $ad->user->profile_image) : null,
                        'user_registered_at' => optional($ad->user)->created_at ?? null,
                        'last_ad_posted_at' => optional($latestAd)->created_at ?? null,
                        'title' => $ad->title,
                        'description' => $ad->description,
                        'address' => $ad->address,
                        'kilometer' => $ad->kilometer,
                        'price' => $ad->price,
                        'phone_number' => $ad->phone_number,
                        'status' => $ad->status,
                        'main_image' => $ad->main_image,
                        'sub_images' => $ad->subImages,
                        'details' => $ad->fieldValues,
                        'view_count' => $ad->views_count,
                    ];
                }),
            ];
        });

        return response()->json([
            'categories' => $result,
        ]);
    }



    public function search(Request $request)
    {
        $query = Ad::with(['subImages', 'fieldValues', 'user', 'views']);

        // ✅ فلترة حسب الحالة إذا كانت موجودة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ فلترة حسب التصنيف إذا موجود
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // 🔍 البحث في العنوان أو الوصف أو الحقول
        if ($request->has('query')) {
            $search = $request->query('query');

            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                    ->orWhere('description', 'LIKE', "%$search%")
                    ->orWhereHas('fieldValues', function ($q) use ($search) {
                        $q->whereHas('field', function ($subQuery) use ($search) {
                            $subQuery->where('field_ar', 'LIKE', "%$search%")
                                ->orWhere('field_en', 'LIKE', "%$search%");
                        })
                            ->orWhereHas('fieldValue', function ($subQuery) use ($search) {
                                $subQuery->where('value_ar', 'LIKE', "%$search%")
                                    ->orWhere('value_en', 'LIKE', "%$search%");
                            });
                    });
            });
        }

        $ads = $query->get();

        // تحويل البيانات
        $ads->transform(function ($ad) {
            $ad->main_image = $ad->main_image ? url($ad->main_image) : null;
            $latestAd = Ad::where('user_id', $ad->user_id)->latest('created_at')->first();
            $ad->views_count = $ad->views()->distinct('user_id')->count();

            $ad->subImages->transform(fn($image) => ['image' => url($image->image)]);
            $ad->fieldValues->transform(fn($fieldValue) => [
                'field_id' => $fieldValue->category_field_id,
                'field_name' => [
                    'ar' => optional($fieldValue->field)->field_ar ?? 'غير معروف',
                    'en' => optional($fieldValue->field)->field_en ?? 'Unknown',
                ],
                'field_value' => [
                    'ar' => optional($fieldValue->fieldValue)->value_ar ?? 'غير معروف',
                    'en' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                ],
            ]);

            return [
                'id' => $ad->id,
                'category' => $ad->category->id,
                'user_id' => $ad->user_id,
                'country_id' => $ad->country_id,
                'user_name' => trim(optional($ad->user)->first_name . ' ' . optional($ad->user)->last_name) ?: null,
                'user_image' => optional($ad->user)->profile_image ? url('profile_images/' . $ad->user->profile_image) : null,
                'user_registered_at' => optional($ad->user)->created_at ?? null,
                'last_ad_posted_at' => optional($latestAd)->created_at ?? null,
                'title' => $ad->title,
                'description' => $ad->description,
                'address' => $ad->address,
                'kilometer' => $ad->kilometer,
                'price' => $ad->price,
                'phone_number' => $ad->phone_number,
                'status' => $ad->status,
                'main_image' => $ad->main_image,
                'sub_images' => $ad->subImages,
                'details' => $ad->fieldValues,
                'views_count' => $ad->views_count,
            ];
        });

        return response()->json(['ads' => $ads]);
    }





    public function indexbyuserid(Request $request)
    {
        $query = Ad::with(['subImages', 'fieldValues', 'user']);

        // تطبيق الفلتر بناءً على user_id فقط
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // تنفيذ الاستعلام وجلب النتائج
        $ads = $query->get();

        // تحويل البيانات
        $ads->transform(function ($ad) {
            $ad->main_image = $ad->main_image ? url($ad->main_image) : null;

            // جلب أحدث إعلان للمستخدم
            $latestAd = Ad::where('user_id', $ad->user_id)->latest('created_at')->first();

            // تحويل الصور الفرعية
            $ad->subImages->transform(function ($image) {
                $image->image = url($image->image);
                return $image;
            });

            // تحويل تفاصيل الحقول
            $ad->fieldValues->transform(function ($fieldValue) {
                return [
                    'field_id' => $fieldValue->category_field_id,
                    'field_name' => [
                        'ar' => optional($fieldValue->field)->field_ar ?? 'غير معروف',
                        'en' => optional($fieldValue->field)->field_en ?? 'Unknown',
                    ],
                    'field_value' => [
                        'ar' => optional($fieldValue->fieldValue)->value_ar ?? 'غير معروف',
                        'en' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                    ],
                ];
            });

            return [
                'id' => $ad->id,
                'user_id' => $ad->user_id,
                'user_name' => trim(optional($ad->user)->first_name . ' ' . optional($ad->user)->last_name) ?: null,
                'user_image' => optional($ad->user)->profile_image ? url('profile_images/' . $ad->user->profile_image) : null,
                'user_registered_at' => optional($ad->user)->created_at ?? null, // أول تسجيل للمستخدم
                'last_ad_posted_at' => optional($latestAd)->created_at ?? null, // آخر إعلان تم نشره
                'title' => $ad->title,
                'description' => $ad->description,
                'address' => $ad->address,
                'kilometer' => $ad->kilometer,

                'price' => $ad->price,
                'phone_number' => $ad->phone_number,
                'status' => $ad->status,
                'main_image' => $ad->main_image,
                'sub_images' => $ad->subImages,
                'details' => $ad->fieldValues,
            ];
        });

        return response()->json(['ads' => $ads]);
    }




    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ad = Ad::find($id);
        if (!$ad) {
            return response()->json(['message' => 'Ad not found'], 404);
        }

        $ad->update(['status' => $request->status]);

        $messages = [
            'approved' => [
                'ar' => 'إعلانك تم قبوله!',
                'en' => 'Your ad has been approved!',
            ],
            'rejected' => [
                'ar' => 'إعلانك تم رفضه!',
                'en' => 'Your ad has been rejected!',
            ],
            'pending' => [
                'ar' => 'إعلانك قيد المراجعة!',
                'en' => 'Your ad is under review!',
            ],
        ];

        // إشعار لصاحب الإعلان
        Notification::create([
            'user_id' => $ad->user_id,
            'ad_id' => $ad->id,
            'type' => 'ad_status',
            'message_ar' => $messages[$request->status]['ar'],
            'message_en' => $messages[$request->status]['en'],
            'is_read' => false,
        ]);

        // إشعار للمتابعين إذا الإعلان approved
        if ($request->status === 'approved') {
            $user = $ad->user; // صاحب الإعلان
            $followers = Follower::where('following_id', $user->id)->pluck('follower_id');

            foreach ($followers as $followerId) {
                Notification::create([
                    'user_id' => $followerId,
                    'from_user_id' => $user->id,
                    'ad_id' => $ad->id,
                    'type' => 'new_ad',
                    'message_ar' => "{$user->first_name} نشر إعلان جديد!",
                    'message_en' => "{$user->first_name} posted a new ad!",
                    'is_read' => false,
                ]);
            }
        }

        return response()->json(['message' => 'Ad status updated successfully', 'ad' => $ad], 200);
    }



    public function indexbyadsid(Request $request)
    {
        $query = Ad::with(['subImages', 'fieldValues.field', 'fieldValues.fieldValue', 'user', 'adViews', 'features.value.field']);

        $query->leftJoin('car_models', 'ads.car_model', '=', 'car_models.id')
            ->select('ads.*', 'car_models.id as car_model_id', 'car_models.value_ar as car_model_ar', 'car_models.value_en as car_model_en');

        if ($request->has('ad_id')) {
            $query->where('ads.id', $request->ad_id);
        }

        $ads = $query->get();

        $ads->transform(function ($ad) {
            $ad->main_image = $ad->main_image ? url($ad->main_image) : null;

            $latestAd = Ad::where('user_id', $ad->user_id)->latest('created_at')->first();

            $ad->subImages->transform(function ($image) {
                $image->image = url($image->image);
                return $image;
            });


            $ad->fieldValues->transform(function ($fieldValue) {
                $field = optional($fieldValue->field);
                $fieldValueModel = optional($fieldValue->fieldValue);

                $fieldType = $fieldValueModel->field_type ?? 'Unknown';
                $valueAr = $fieldValueModel->value_ar ?? 'غير معروف';
                $valueEn = $fieldValueModel->value_en ?? 'Unknown';

                // ✅ لو نوع الحقل text والقيمة رقمية (يعني ID لقيمة أخرى)
                if ($fieldType === 'text' && is_numeric($valueAr)) {
                    $realValue = \App\Models\CategoryFieldValue::find($valueAr);
                    if ($realValue) {
                        $valueAr = $realValue->value_ar ?? $valueAr;
                        $valueEn = $realValue->value_en ?? $valueEn;
                    }
                }

                return [
                    'field_id' => $fieldValue->category_field_id,
                    'field_name' => [
                        'ar' => $field->field_ar ?? 'غير معروف',
                        'en' => $field->field_en ?? 'Unknown',
                    ],
                    'field_value_id' => $fieldValue->category_field_value_id,
                    'field_value' => [
                        'ar' => $valueAr,
                        'en' => $valueEn,
                    ],
                    'field_type' => $fieldType,
                ];
            });

            $features = $ad->features->map(function ($feature) {
                return [
                    'feature_id' => $feature->feature_id,
                    'value_ar' => optional($feature->value)->value_ar ?? 'غير معروف',
                    'value_en' => optional($feature->value)->value_en ?? 'Unknown',
                ];
            });


            return [
                'id' => $ad->id,
                'country_id' => $ad->country_id,
                'city_id' => $ad->city_id,
                'user_id' => $ad->user_id,
                'user_name' => trim(optional($ad->user)->first_name . ' ' . optional($ad->user)->last_name) ?: null,
                'user_image' => optional($ad->user)->profile_image ? url('profile_images/' . $ad->user->profile_image) : null,
                'user_registered_at' => optional($ad->user)->created_at ?? null,
                'last_ad_posted_at' => optional($latestAd)->created_at ?? null,
                'title' => $ad->title,
                'category_id' => $ad->category_id,
                'description' => $ad->description,
                'address' => $ad->address,
                'price' => $ad->price,
                'kilometer' => $ad->kilometer,
                'phone_number' => $ad->phone_number,
                'status' => $ad->status,
                'main_image' => $ad->main_image,
                'sub_images' => $ad->subImages,
                'details' => $ad->fieldValues,
                'features' => $features,
                'view_count' => $ad->adViews->count(),
                'car_model_id' => $ad->car_model_id,
                'car_model_ar' => $ad->car_model_ar,
                'car_model_en' => $ad->car_model_en,
            ];
        });

        return response()->json(['ads' => $ads]);
    }

    public function updateCarOptionFeature(Request $request)
    {
        // تحقق من البيانات المطلوبة
        $request->validate([
            'ad_id' => 'required|integer|exists:car_ad_features,car_ad_id',
            'feature_id' => 'required|integer|exists:car_ad_features,feature_id',
            'new_feature_id' => 'required|integer|exists:category_field_values,id',
        ]);

        // محاولة العثور على السجل
        $feature = AdFeature::where('car_ad_id', $request->ad_id)
            ->where('feature_id', $request->feature_id)
            ->first();

        if (!$feature) {
            return response()->json(['message' => 'Feature not found for this ad.'], 404);
        }

        // تحديث قيمة الميزة
        $feature->feature_id = $request->new_feature_id;
        $feature->save();

        return response()->json([
            'message' => 'Car option feature updated successfully.',
            'data' => $feature
        ]);
    }




    public function toggleFavorite(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'ad_id' => 'required|exists:ads,id',
        ]);

        $favorite = Favorite::where('user_id', $user->id)
            ->where('ad_id', $request->ad_id)
            ->first();

        if ($favorite) {
            // إزالة الإعلان من المفضلة
            $favorite->delete();
            return response()->json(['message' => 'Ad removed from favorites']);
        } else {
            // إضافة الإعلان إلى المفضلة
            Favorite::create([
                'user_id' => $user->id,
                'ad_id' => $request->ad_id,
            ]);
            return response()->json(['message' => 'Ad added to favorites']);
        }
    }


    public function getFavorites()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $favorites = Favorite::with(['ad.subImages', 'ad.fieldValues', 'ad.user'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($favorite) {
                $ad = $favorite->ad;
                $latestAd = Ad::where('user_id', $ad->user_id)->latest('created_at')->first();

                return [
                    'id' => $ad->id,
                    'user_id' => $ad->user_id,
                    'user_name' => trim(optional($ad->user)->first_name . ' ' . optional($ad->user)->last_name) ?: null,
                    'user_image' => optional($ad->user)->profile_image ? url('profile_images/' . $ad->user->profile_image) : null,
                    'user_registered_at' => optional($ad->user)->created_at ?? null,
                    'last_ad_posted_at' => optional($latestAd)->created_at ?? null,
                    'title' => $ad->title,
                    'description' => $ad->description,
                    'address' => $ad->address,
                    'price' => $ad->price,
                    'kilometer' => $ad->kilometer,

                    'phone_number' => $ad->phone_number,
                    'status' => $ad->status,
                    'main_image' => $ad->main_image ? url($ad->main_image) : null,
                    'sub_images' => $ad->subImages->map(fn($image) => ['image' => url($image->image)]),
                    'details' => $ad->fieldValues->map(fn($fieldValue) => [
                        'field_id' => $fieldValue->category_field_id,
                        'field_name' => [
                            'ar' => optional($fieldValue->field)->field_ar ?? 'غير معروف',
                            'en' => optional($fieldValue->field)->field_en ?? 'Unknown',
                        ],
                        'field_value' => [
                            'ar' => optional($fieldValue->fieldValue)->value_ar ?? 'غير معروف',
                            'en' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                        ],
                    ]),
                ];
            });

        return response()->json(['favorites' => $favorites]);
    }


    public function seen(Request $request, $ad_id)
    {
        // جلب الإعلان والتأكد من أنه موجود وحالته approved
        $ad = Ad::where('id', $ad_id)->where('status', 'approved')->firstOrFail();

        // جلب المستخدم المسجل (لو موجود)
        $user = auth('api')->user(); // أو auth()->user() حسب نوع التوثيق

        // التأكد إن المستخدم مش هو صاحب الإعلان
        if ($user && $user->id == $ad->user_id) {
            return response()->json(['message' => 'You cannot view your own ad.'], 403);
        }

        // استخدام IP أو session ID لو المستخدم غير مسجل
        $identifier = $user ? 'user_' . $user->id : 'guest_' . $request->ip();

        // تسجيل المشاهدة
        AdView::create([
            'identifier' => $identifier,
            'ad_id' => $ad_id,
        ]);

        return response()->json(['message' => 'Ad view recorded successfully']);
    }


    public function getUserProfile(Request $request, $user_id)
    {
        $authUser = JWTAuth::parseToken()->authenticate();

        $user = Userauth::with(['followers', 'following', 'ads.subImages', 'ads.fieldValues'])->find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // التحقق مما إذا كان المستخدم المصادق عليه يتابع هذا الحساب
        $isFollowing = $authUser ? $user->followers->contains('follower_id', $authUser->id) : false;

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'is_blocked' => $user->is_blocked, // تمت الإضافة هنا
            'profile_image' => $user->profile_image ? url('profile_images/' . $user->profile_image) : null,
            'cover_image' => $user->cover_image ? url('cover_images/' . $user->cover_image) : null,
            'followers_count' => $user->followers->count(),
            'following_count' => $user->following->count(),
            'is_following' => $isFollowing,
            'ads' => $user->ads->map(function ($ad) {
                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'description' => $ad->description,
                    'price' => $ad->price,
                    'kilometer' => $ad->kilometer,
                    'status' => $ad->status,
                    'main_image' => $ad->main_image ? url($ad->main_image) : null,
                    'sub_images' => $ad->subImages->map(fn($image) => url($image->image)),
                    'details' => $ad->fieldValues->map(fn($fieldValue) => [
                        'field_name' => optional($fieldValue->field)->field_en ?? 'Unknown',
                        'field_value' => optional($fieldValue->fieldValue)->value_en ?? 'Unknown',
                    ]),
                ];
            }),
        ]);
    }


    public function stats()
    {
        $totalAds = \App\Models\Ad::count();

        $statusCounts = \App\Models\Ad::select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'total_ads' => $totalAds,
            'status_counts' => [
                'pending' => $statusCounts->get('pending', 0),
                'approved' => $statusCounts->get('approved', 0),
                'rejected' => $statusCounts->get('rejected', 0),
            ],
        ]);
    }
}
