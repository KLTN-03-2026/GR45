<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->redirectUrl(config('services.google.redirect'))
            ->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback()
    {
        try {
            // Tạm thời bỏ qua kiểm tra SSL (cho môi trường local/XAMPP)
            $googleUser = Socialite::driver('google')
                ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                ->user();
            
            // Tìm user theo google_id hoặc email
            $user = KhachHang::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if (!$user) {
                // Tạo user mới nếu chưa tồn tại
                $user = KhachHang::create([
                    'ho_va_ten'     => $googleUser->name,
                    'email'         => $googleUser->email,
                    'google_id'     => $googleUser->id,
                    'avatar'        => $googleUser->avatar,
                    'password'      => Hash::make(Str::random(16)), // Mật khẩu ngẫu nhiên
                    'tinh_trang'    => 'hoat_dong', // Active ngay lập tức
                    'so_dien_thoai' => '', // Không được để null theo DB config
                ]);
            } else {
                // Nếu user đã tồn tại nhưng chưa có google_id, cập nhật lại
                if (empty($user->google_id)) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar'    => $googleUser->avatar,
                    ]);
                }
            }

            // Tạo Sanctum Token
            $token = $user->createToken('google-auth')->plainTextToken;

            // Chuyển hướng về Frontend kèm Token
            $feUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
            
            return redirect()->away($feUrl . "/auth/google/callback?token=" . $token);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google Auth Error: ' . $e->getMessage());
            $feUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
            return redirect()->away($feUrl . "/dang-nhap?error=google_auth_failed");
        }
    }
}
