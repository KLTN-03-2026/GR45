<?php

namespace App\Http\Controllers;

use App\Models\ViNhaXe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViNhaXeController extends Controller
{
    /**
     * Lấy thông tin ví và lịch sử giao dịch
     */
    public function getWalletInfo(Request $request)
    {
        $nhaXe = Auth::user()->nhaXe;
        if (!$nhaXe) {
            return response()->json(['message' => 'Không tìm thấy nhà xe'], 404);
        }

        $viNhaXe = ViNhaXe::firstOrCreate(
            ['ma_nha_xe' => $nhaXe->ma_nha_xe],
            [
                'ma_vi_nha_xe' => 'V' . time() . rand(10, 99),
                'so_du' => 0,
                'han_muc_toi_thieu' => 500000,
            ]
        );

        // Lấy lịch sử giao dịch
        $limit = $request->input('limit', 50);
        $transactions = $viNhaXe->lichSu()
            ->with(['chuyenXe', 'nguoiThucHien'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return response()->json([
            'wallet' => $viNhaXe,
            'transactions' => $transactions
        ]);
    }

    /**
     * Cập nhật thông tin ngân hàng nhận tiền của nhà xe
     */
    public function updateBankInfo(Request $request)
    {
        $request->validate([
            'ngan_hang' => 'required|string',
            'ten_tai_khoan' => 'required|string',
            'so_tai_khoan' => 'required|string',
        ]);

        $nhaXe = Auth::user()->nhaXe;
        if (!$nhaXe) {
            return response()->json(['message' => 'Không tìm thấy nhà xe'], 404);
        }

        $viNhaXe = ViNhaXe::where('ma_nha_xe', $nhaXe->ma_nha_xe)->firstOrFail();
        
        $viNhaXe->update([
            'ngan_hang' => $request->ngan_hang,
            'ten_tai_khoan' => $request->ten_tai_khoan,
            'so_tai_khoan' => $request->so_tai_khoan,
        ]);

        return response()->json([
            'message' => 'Cập nhật thông tin ngân hàng thành công',
            'wallet' => $viNhaXe
        ]);
    }

    /**
     * Yêu cầu rút tiền về tài khoản ngân hàng
     */
    public function requestWithdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
        ]);

        $nhaXe = Auth::user()->nhaXe;
        $viNhaXe = ViNhaXe::where('ma_nha_xe', $nhaXe->ma_nha_xe)->firstOrFail();

        $amount = $request->amount;

        // Kiem tra thong tin ngan hang
        if (!$viNhaXe->ngan_hang || !$viNhaXe->so_tai_khoan) {
            return response()->json(['message' => 'Vui lòng cập nhật thông tin ngân hàng trước khi rút tiền'], 400);
        }

        if ($viNhaXe->so_du < $amount) {
            return response()->json(['message' => 'Số dư không đủ để thực hiện rút tiền'], 400);
        }

        try {
            DB::beginTransaction();

            $transaction = $viNhaXe->taoYeuCauRutTien($amount, 'Yêu cầu rút tiền từ ví nhà xe');

            DB::commit();

            return response()->json([
                'message' => 'Đã gửi yêu cầu rút tiền thành công, vui lòng chờ hệ thống xử lý',
                'transaction' => $transaction,
                'wallet' => $viNhaXe->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Yêu cầu nạp tiền (sinh thông tin chuyển khoản VietQR)
     */
    public function requestTopup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
        ]);

        $nhaXe = Auth::user()->nhaXe;
        $viNhaXe = ViNhaXe::where('ma_nha_xe', $nhaXe->ma_nha_xe)->firstOrFail();
        $amount = $request->amount;

        try {
            DB::beginTransaction();

            // Tao log giao dich o trang thai cho_xac_nhan
            $transaction = $viNhaXe->taoYeuCauNapTien($amount, 'Nạp tiền vào ví qua chuyển khoản');

            DB::commit();

            // Tra ve thong tin de FE tao ma QR
            return response()->json([
                'message' => 'Tạo yêu cầu nạp tiền thành công',
                'transaction' => $transaction,
                'qr_data' => [
                    'bank_code' => '970422', // MB Bank
                    'account_no' => '0123456789',
                    'account_name' => 'HE THONG VEXE',
                    'amount' => $amount,
                    'content' => $transaction->transaction_code
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }
}
