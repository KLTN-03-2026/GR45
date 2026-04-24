<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\KhachHang;
use App\Models\NhaXe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    private const PASSWORD_RESET_TABLE = 'dat_lai_mat_khau_tokens';

    public function requestReset(string $role, string $email): void
    {
        $normalizedRole = $this->normalizeRole($role);
        $normalizedEmail = strtolower(trim($email));

        $user = $this->findUser($normalizedRole, $normalizedEmail);
        if (!$user) {
            return;
        }

        $token = Str::random(64);
        $expiredAt = now()->addMinutes(30);

        DB::table(self::PASSWORD_RESET_TABLE)
            ->where('role', $normalizedRole)
            ->where('email', $normalizedEmail)
            ->whereNull('used_at')
            ->delete();

        DB::table(self::PASSWORD_RESET_TABLE)->insert([
            'role' => $normalizedRole,
            'email' => $normalizedEmail,
            'token' => $token,
            'expired_at' => $expiredAt,
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $resetLink = $frontendUrl . '/auth/reset-password?token=' . urlencode($token)
            . '&role=' . urlencode($normalizedRole)
            . '&email=' . urlencode($normalizedEmail);

        $expiresMinutes = 30;

        Mail::send(
            'emails.reset-password',
            [
                'resetLink' => $resetLink,
                'expiresMinutes' => $expiresMinutes,
            ],
            function ($message) use ($normalizedEmail) {
                $message->to($normalizedEmail)->subject('GoBus — Đặt lại mật khẩu');
            }
        );
    }

    public function resetPassword(string $role, string $email, string $token, string $newPassword): void
    {
        $normalizedRole = $this->normalizeRole($role);
        $normalizedEmail = strtolower(trim($email));

        $record = DB::table(self::PASSWORD_RESET_TABLE)
            ->where('role', $normalizedRole)
            ->where('email', $normalizedEmail)
            ->where('token', $token)
            ->whereNull('used_at')
            ->first();

        if (!$record || now()->gt($record->expired_at)) {
            throw ValidationException::withMessages([
                'token' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',
            ]);
        }

        $user = $this->findUser($normalizedRole, $normalizedEmail);
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản không tồn tại.',
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->save();
        $user->tokens()->delete();

        DB::table(self::PASSWORD_RESET_TABLE)
            ->where('id', $record->id)
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function normalizeRole(string $role): string
    {
        $normalized = strtolower(trim($role));
        $allowed = ['khach_hang', 'nha_xe', 'admin'];
        if (!in_array($normalized, $allowed, true)) {
            throw ValidationException::withMessages([
                'role' => 'Vai trò không hợp lệ.',
            ]);
        }
        return $normalized;
    }

    protected function findUser(string $role, string $email): Admin|KhachHang|NhaXe|null
    {
        if ($role === 'khach_hang') {
            return KhachHang::where('email', $email)->first();
        }
        if ($role === 'nha_xe') {
            return NhaXe::where('email', $email)->first();
        }
        return Admin::where('email', $email)->first();
    }
}

