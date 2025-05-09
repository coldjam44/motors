<?php

namespace App\Http\Controllers;

use App\Models\Userauth;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use App\Models\Follower;

class UserauthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function register(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'first_name' => 'required|string|max:255',
    //         'last_name' => 'required|string|max:255',
    //         'email' => 'required|email|unique:userauths,email',
    //         'phone_number' => 'required|unique:userauths,phone_number',
    //         'password' => 'required|min:6|confirmed',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     $user = UserAuth::create([
    //         'first_name' => $request->first_name,
    //         'last_name' => $request->last_name,
    //         'email' => $request->email,
    //         'phone_number' => $request->phone_number,
    //         'password' => Hash::make($request->password),
    //     ]);

    //     // إنشاء توكن JWT
    //     $token = JWTAuth::fromUser($user);

    //     return response()->json([
    //         'message' => 'User registered successfully',
    //         'user' => $user,
    //         'token' => $token, // إرجاع التوكن للمستخدم
    //     ], 201);
    // }

    // public function login(Request $request)
    // {
    //     $credentials = $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required'
    //     ]);

    //     if (!$token = auth('api')->attempt($credentials)) {
    //         return response()->json(['message' => 'Invalid credentials'], 401);
    //     }

    //     return response()->json([
    //         'message' => 'Login successful',
    //         'token' => $token,
    //     ]);
    // }



    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:userauths,email',
            'phone_number' => 'required|unique:userauths,phone_number',
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Userauth::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
        ]);

        // إنشاء التوكن JWT
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token, // إرجاع التوكن للمستخدم
        ], 201);
    }

  public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'remember_me' => 'nullable|boolean', // إضافة هذا السطر للتأكد من الخيار
    ]);

    $user = Userauth::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // تحديد مدة صلاحية التوكن بناءً على خيار "remember me"
    $ttl = $request->remember_me ? 0 : 3600; // 0 تعني التوكن يدوم للأبد، 3600 تعني 1 ساعة

    $token = JWTAuth::fromUser($user, ['exp' => now()->addSeconds($ttl)->timestamp]);
    $authUser = $user;

    $followers = Follower::where('following_id', $user->id)
        ->with('follower')
        ->get()
        ->map(function ($follow) use ($authUser) {
            $follower = $follow->follower;

            $isFollowing = Follower::where('follower_id', $authUser->id)
                ->where('following_id', $follower->id)
                ->exists();

            return [
                'id' => $follower->id,
                'first_name' => $follower->first_name,
                'last_name' => $follower->last_name,
                'email' => $follower->email,
                'phone_number' => $follower->phone_number,
                'profile_image' => $follower->profile_image ? asset('profile_images/' . $follower->profile_image) : null,
                'created_at' => $follower->created_at,
                'is_following' => $isFollowing,
            ];
        });

    $followers_count = Follower::where('following_id', $user->id)->count();
    $following_count = Follower::where('follower_id', $user->id)->count();

    // 👇 قائمة الأشخاص اللي المستخدم متابعهم (ID فقط)
    $following = Follower::where('follower_id', $user->id)
        ->pluck('following_id')
        ->toArray();

    return response()->json([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'profile_image' => $user->profile_image ? asset('profile_images/' . $user->profile_image) : null,
            'cover_image' => $user->cover_image ? asset('cover_images/' . $user->cover_image) : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'followers_count' => $followers_count,
            'following_count' => $following_count,
        ],
        'followers' => $followers,
        'following' => $following, // ✅ إضافة قائمة المتابَعين
    ]);
}

  public function logout(Request $request)
{
    try {
        // إبطال التوكن الحالي (تسجيل الخروج)
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'User logged out successfully'], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Failed to log out'], 500);
    }
}

  
  public function me(Request $request)
{
    try {
        // جلب المستخدم من التوكن
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // جلب المتابعين للمستخدم
        $followers = Follower::where('following_id', $user->id)
            ->with('follower')
            ->get()
            ->map(function ($follow) use ($user) {
                $follower = $follow->follower;

                // التحقق مما إذا كان المستخدم يتابع هذا المتابع
                $isFollowing = Follower::where('follower_id', $user->id)
                    ->where('following_id', $follower->id)
                    ->exists();

                return [
                    'id' => $follower->id,
                    'first_name' => $follower->first_name,
                    'last_name' => $follower->last_name,
                    'email' => $follower->email,
                    'phone_number' => $follower->phone_number,
                    'profile_image' => $follower->profile_image ? asset('profile_images/' . $follower->profile_image) : null,
                    'created_at' => $follower->created_at,
                    'is_following' => $isFollowing,
                ];
            });

        // عدد المتابعين
        $followers_count = Follower::where('following_id', $user->id)->count();

        // عدد الذين يتابعهم المستخدم
        $following_count = Follower::where('follower_id', $user->id)->count();
      
       $following = Follower::where('follower_id', $user->id)
        ->pluck('following_id')
        ->toArray();

        return response()->json([
            'message' => 'User data retrieved successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'profile_image' => $user->profile_image ? asset('profile_images/' . $user->profile_image) : null,
                'cover_image' => $user->cover_image ? asset('cover_images/' . $user->cover_image) : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'followers_count' => $followers_count,
                'following_count' => $following_count,
            ],
            'followers' => $followers,
                  'following' => $following, // ✅ إضافة قائمة المتابَعين

        ]);
    } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        return response()->json(['message' => 'Token has expired'], 401);
    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        return response()->json(['message' => 'Token is invalid'], 401);
    } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
        return response()->json(['message' => 'Token is missing'], 401);
    }
}


    public function update(Request $request)
    {
        // الحصول على المستخدم الحالي من التوكن
        $user = auth()->user();

        // التحقق من صحة البيانات المدخلة
        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|email|unique:userauths,email,' . $user->id,
            'phone_number' => 'sometimes|unique:userauths,phone_number,' . $user->id,
            'password' => 'sometimes|min:6|confirmed',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cover_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // تحديث البيانات إن وُجدت في الطلب
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone_number')) {
            $user->phone_number = $request->phone_number;
        }
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

       if ($request->hasFile('profile_image')) {
    // حذف الصورة القديمة إن وجدت
    if ($user->profile_image) {
        $oldProfileImage = public_path('profile_images/' . $user->profile_image);
        if (File::exists($oldProfileImage)) {
            File::delete($oldProfileImage);
        }
    }

    // رفع الصورة الجديدة
    $profileImage = $request->file('profile_image');
    $imageName = time() . '_profile.' . $profileImage->getClientOriginalExtension();
    $profileImage->move(public_path('profile_images'), $imageName);

    // حفظ اسم الصورة فقط
    $user->profile_image = $imageName;
}

if ($request->hasFile('cover_image')) {
    // حذف الصورة القديمة إن وجدت
    if ($user->cover_image) {
        $oldCoverImage = public_path('cover_images/' . $user->cover_image);
        if (File::exists($oldCoverImage)) {
            File::delete($oldCoverImage);
        }
    }

    // رفع الصورة الجديدة
    $coverImage = $request->file('cover_image');
    $imageName = time() . '_cover.' . $coverImage->getClientOriginalExtension();
    $coverImage->move(public_path('cover_images'), $imageName);

    // حفظ اسم الصورة فقط
    $user->cover_image = $imageName;
}

// حفظ التعديلات
$user->save();

    // إعادة البيانات مع الروابط الصحيحة
    return response()->json([
        'message' => 'Profile updated successfully',
        'user' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'profile_image' => $user->profile_image ? url('profile_images/' . $user->profile_image) : null,
            'cover_image' => $user->cover_image ? url('cover_images/' . $user->cover_image) : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]
    ]);
}


}
