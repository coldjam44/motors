<?php

namespace App\Http\Controllers;

use App\Models\Userauth;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use App\Models\Follower;
use Illuminate\Support\Facades\DB;

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
            'role' => 'nullable|in:admin,user',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = $request->role ?? 'user';

        $user = Userauth::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => $role,
        ]);

        // حفظ إشعار لكل الأدمنز
        //if ($role === 'user') {
        $adminUsers = Userauth::where('role', 'admin')->get();

        foreach ($adminUsers as $admin) {
            DB::table('notifications')->insert([
                'user_id' => $admin->id, // الأدمن المستهدف
                'from_user_id' => $user->id, // المستخدم الجديد
                'ad_id' => null,
                'type' => 'new_user_registered',
                'message_ar' => 'تم تسجيل مستخدم جديد: ' . $user->first_name . ' ' . $user->last_name,
                'message_en' => 'New user registered: ' . $user->first_name . ' ' . $user->last_name,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        //}

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember_me' => 'nullable|boolean',
        ]);

        $user = Userauth::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials | بيانات الدخول غير صحيحة'], 401);
        }

        // مثال لو حبيت تضيف حظر المستخدم بعدين:
        if ($user->is_blocked) {
            return response()->json([
                'message_en' => 'Your account is blocked',
                'message_ar' => 'حسابك محظور'
            ], 403);
        }


        $ttl = $request->remember_me ? 0 : 3600;

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

        $following = Follower::where('follower_id', $user->id)
            ->pluck('following_id')
            ->toArray();

        return response()->json([
            'message' => 'Login successful | تم تسجيل الدخول بنجاح',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role, // ✅ تم إضافة نوع الحساب
                'profile_image' => $user->profile_image ? asset('profile_images/' . $user->profile_image) : null,
                'cover_image' => $user->cover_image ? asset('cover_images/' . $user->cover_image) : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'followers_count' => $followers_count,
                'following_count' => $following_count,
            ],
            'followers' => $followers,
            'following' => $following,
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
                    'role' => $user->role,
                    'phone_number' => $user->phone_number,
                    'is_blocked' => $user->is_blocked, // ✅ تمت الإضافة هنا
                    'profile_image' => $user->profile_image ? asset('profile_images/' . $user->profile_image) : null,
                    'cover_image' => $user->cover_image ? asset('cover_images/' . $user->cover_image) : null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'followers_count' => $followers_count,
                    'following_count' => $following_count,
                ],
                'followers' => $followers,
                'following' => $following, // ✅ قائمة المتابَعين
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
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:userauths,email,' . $user->id,
            'phone_number' => 'sometimes|unique:userauths,phone_number,' . $user->id,
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cover_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'old_password' => 'sometimes|required_with:new_password|string',
            'new_password' => 'sometimes|required_with:old_password|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // تحديث البيانات إن وُجدت في الطلب
        if ($request->has('first_name')) {
            $user->first_name = $request->first_name;
        }
        if ($request->has('last_name')) {
            $user->last_name = $request->last_name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone_number')) {
            $user->phone_number = $request->phone_number;
        }

        // تحديث كلمة المرور بعد التحقق من القديمة
        $passwordChanged = false;
        if ($request->filled('old_password') && $request->filled('new_password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'message' => 'كلمة المرور القديمة غير صحيحة / Old password is incorrect.'
                ], 400);
            }

            $user->password = Hash::make($request->new_password);
            $passwordChanged = true;
        }

        // تحديث الصورة الشخصية
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                $oldProfileImage = public_path('profile_images/' . $user->profile_image);
                if (File::exists($oldProfileImage)) {
                    File::delete($oldProfileImage);
                }
            }

            $profileImage = $request->file('profile_image');
            $imageName = time() . '_profile.' . $profileImage->getClientOriginalExtension();
            $profileImage->move(public_path('profile_images'), $imageName);
            $user->profile_image = $imageName;
        }

        // تحديث صورة الغلاف
        if ($request->hasFile('cover_image')) {
            if ($user->cover_image) {
                $oldCoverImage = public_path('cover_images/' . $user->cover_image);
                if (File::exists($oldCoverImage)) {
                    File::delete($oldCoverImage);
                }
            }

            $coverImage = $request->file('cover_image');
            $imageName = time() . '_cover.' . $coverImage->getClientOriginalExtension();
            $coverImage->move(public_path('cover_images'), $imageName);
            $user->cover_image = $imageName;
        }

        $user->save();

        $message = $passwordChanged
            ? 'تم تغيير كلمة المرور بنجاح / Password changed successfully'
            : 'تم تحديث الملف الشخصي بنجاح / Profile updated successfully';

        return response()->json([
            'message' => $message,
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



    public function listUsers(Request $request)
    {
        $publicPath = public_path(); // full path to public

        // استخدم withCount لجلب عدد الإعلانات
        $users = Userauth::withCount('ads')->get()->map(function ($user) use ($publicPath) {
            // دالة البحث عن الصورة
            $findImagePath = function ($filename) use ($publicPath) {
                $results = File::allFiles($publicPath);
                foreach ($results as $file) {
                    if ($file->getFilename() === $filename) {
                        $relativePath = str_replace($publicPath, '', $file->getPathname());
                        return asset(trim($relativePath, '/\\'));
                    }
                }
                return null;
            };

            // تحديث الصور
            $user->profile_image = $user->profile_image
                ? $findImagePath($user->profile_image)
                : null;

            $user->cover_image = $user->cover_image
                ? $findImagePath($user->cover_image)
                : null;

            return $user;
        });

        return response()->json([
            'message' => 'Users list retrieved successfully',
            'users' => $users,
        ]);
    }



    public function blockUser(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:userauths,id',
        ]);

        $user = Userauth::find($request->id);

        if (!$user) {
            return response()->json(['message' => 'User not found | المستخدم غير موجود'], 404);
        }

        $user->is_blocked = !$user->is_blocked;
        $user->save();

        $message = $user->is_blocked
            ? 'User blocked successfully | تم الحظر'
            : 'User unblocked successfully | تم إلغاء الحظر';

        return response()->json([
            'message' => $message,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'is_blocked' => $user->is_blocked,
            ],
        ]);
    }

    public function countUsers()
    {
        $count = Userauth::count();

        return response()->json([
            'message' => 'Total users count | العدد الكلي للمستخدمين',
            'count' => $count,
        ]);
    }
}
