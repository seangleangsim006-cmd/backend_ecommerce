<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PhoneVerificationCode;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    //
    public function register(Request $request){
       $validate = Validator::make($request->all(),[
          'name' => 'required|string',
          'email' => 'nullable|email|string|required_without:phone|unique:users,email',
          'phone' => 'nullable|string|max:30|required_without:email|unique:users,phone',
          'password' => 'required|string',
          'profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
       ]);
        if($validate->fails()){
            return response()->json([
              'success' => false,
              'message'=> "Invalid input",
              'error' => $validate->errors()
            ],422);
        }
        $user = User::create([
           'name'=> $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'profile_image' => $request->hasFile('profile_image')
                ? app(CloudinaryImageService::class)->upload($request->file('profile_image'), 'profiles')
                : null,
        ]);
        if ($user->phone) {
            if (!$this->dispatchPhoneVerificationCode($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to send phone verification code',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to your phone',
                'data' => [
                    'phone' => $user->phone,
                    'expires_in' => 600,
                ],
            ], 202);
        }

        // Generate token for email registration.
        $token = $user->createToken("auth_token")->plainTextToken;
       return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'profile_image' => $user->profile_image,
                    'created_at' => $user->created_at,
                ],
                'token' => $token
            ]
        ], 201);
    }
    public function login(Request $request){
        $validate = Validator::make($request->all(),[
           'email'=>"nullable|email|string|required_without:phone",
           'phone'=>"nullable|string|max:30|required_without:email",
           'password' => "required|string"
        ]);
        if($validate->fails()){
            return response()->json([
              'success' => false,
              'message'=>"Invalid Input",
              'error' => $validate->errors()
            ]);
        }
        $user = User::where(function ($query) use ($request) {
    if ($request->filled('email')) {
        $query->where('email', $request->email);
    }
    if ($request->filled('phone')) {
        $query->orWhere('phone', $request->phone);
    }
})->first();
        if(!$user){
            return response()->json(
                ['success'=> false,
                'message'=>'Invalid credentials'

                ],401
            );
        }

        if ($user->phone && !$user->phone_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your phone number first',
            ], 403);
        }
        if(!Hash::check($request->password,$user->password)){
            return response()->json(
                ['success'=> false,
                'message'=>'Invalid credentials'

                ],401
            );
        }
        $token = $user->createToken("auth_token")->plainTextToken;
        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                        'profile_image' => $user->profile_image,
                    'created_at' => $user->created_at,
                ],
                'token' => $token
            ]
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'error' => $validate->errors(),
            ], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another reset link',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for this email, a password reset link has been sent',
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'error' => $validate->errors(),
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset token',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ], 200);
    }

    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully',
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validate = Validator::make($request->all(), [
            'name' => 'sometimes|required|string',
            'profile_image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'error' => $validate->errors(),
            ], 422);
        }

        if ($request->hasFile('profile_image')) {
            $oldProfileImage = $user->profile_image;
            $user->profile_image = app(CloudinaryImageService::class)
                ->upload($request->file('profile_image'), 'profiles');
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        $user->save();

        if (isset($oldProfileImage)) {
            app(CloudinaryImageService::class)->delete($oldProfileImage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user,
            ],
        ], 200);
    }

    public function sendPhoneVerificationCode(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'phone' => 'required|string|max:30|exists:users,phone',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number',
                'error' => $validate->errors(),
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        if ($user->phone_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number is already verified',
            ], 409);
        }

        if (!$this->dispatchPhoneVerificationCode($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send phone verification code',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your phone',
            'data' => ['expires_in' => 600],
        ], 200);
    }

    public function verifyPhone(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'phone' => 'required|string|max:30|exists:users,phone',
            'code' => 'required|digits:6',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification details',
                'error' => $validate->errors(),
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        $verification = PhoneVerificationCode::where('phone', $user->phone)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$verification || $verification->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code is invalid or expired',
            ], 422);
        }

        if ($verification->attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many verification attempts. Request a new code.',
            ], 429);
        }

        if (!Hash::check($request->code, $verification->code_hash)) {
            $verification->increment('attempts');

            return response()->json([
                'success' => false,
                'message' => 'Verification code is invalid or expired',
            ], 422);
        }

        $verification->update(['verified_at' => now()]);
        $user->update(['phone_verified_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully',
            'data' => [
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
            ],
        ], 200);
    }

    private function dispatchPhoneVerificationCode(User $user): bool
    {
        $code = (string) random_int(100000, 999999);

        PhoneVerificationCode::where('phone', $user->phone)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);

        $verification = PhoneVerificationCode::create([
            'phone' => $user->phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (!$sid || !$token || !$from) {
            $verification->delete();
            return false;
        }

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $user->phone,
                'Body' => "Your verification code is {$code}. It expires in 10 minutes.",
            ]);

        if (!$response->successful()) {
            $verification->delete();
        }

        return $response->successful();
    }

    public function redirectToSocialProvider(string $provider)
    {
        if (!in_array($provider, ['google', 'facebook'], true)) {
            return response()->json(['message' => 'Unsupported social provider'], 422);
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleSocialProviderCallback(string $provider)
    {
        if (!in_array($provider, ['google', 'facebook'], true)) {
            return response()->json(['message' => 'Unsupported social provider'], 422);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to authenticate with '.ucfirst($provider),
            ], 401);
        }

        $user = User::where('social_provider', $provider)
            ->where('social_id', $socialUser->getId())
            ->first();

        if (!$user && $socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();
        }

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'Customer',
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(bin2hex(random_bytes(32))),
                'role' => 'customer',
                'social_provider' => $provider,
                'social_id' => $socialUser->getId(),
            ]);
        } else {
            $user->update([
                'social_provider' => $provider,
                'social_id' => $socialUser->getId(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User authenticated successfully',
            'data' => [
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
            ],
        ], 200);
    }
}
