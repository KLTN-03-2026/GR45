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
        $nhaXe = Auth::guard('nha_xe')->user();
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

        $nhaXe = Auth::guard('nha_xe')->user();
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

        $nhaXe = Auth::guard('nha_xe')->user();
        $viNhaXe = ViNhaXe::where('ma_nha_xe', $nhaXe->ma_nha_xe)->firstOrFail();

        $amount = $request->amount;

        // Kiem tra thong tin ngan hang
        if (!$viNhaXe->ngan_hang || !$viNhaXe->so_tai_khoan) {
            return response()->json(['message' => 'Vui lòng cập nhật thông tin ngân hàng trước khi rút tiền'], 400);
        }

        if ($viNhaXe->so_du - $amount < 1000000) {
            return response()->json(['message' => 'Số dư còn lại sau khi rút phải tối thiểu là ' . number_format(1000000, 0, ',', '.') . ' VNĐ'], 400);
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

        $nhaXe = Auth::guard('nha_xe')->user();
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
                    'bank_code' => env('SEPAY_BANK_ID', 'MBBank'), 
                    'account_no' => env('SEPAY_ACCOUNT_NUMBER', '0377417720'),
                    'account_name' => env('SEPAY_ACCOUNT_NAME', 'NGUYENHUUTHAI'),
                    'amount' => $amount,
                    'content' => $transaction->transaction_code
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Chi tiết giao dịch
     */
    public function getTransactionDetail(int $id)
    {
        $nhaXe = Auth::guard('nha_xe')->user();
        if (!$nhaXe) {
            return response()->json(['message' => 'Không tìm thấy nhà xe'], 404);
        }

        $viNhaXe = ViNhaXe::where('ma_nha_xe', $nhaXe->ma_nha_xe)->firstOrFail();

        $transaction = $viNhaXe->lichSu()
            ->with(['chuyenXe', 'nguoiThucHien'])
            ->findOrFail($id);

        $data = [
            'transaction' => $transaction
        ];

        // Nếu là nạp tiền và đang chờ, kèm theo data QR
        if ($transaction->loai_giao_dich === 'nap_tien' && $transaction->tinh_trang === 'cho_xac_nhan') {
            $data['qr_data'] = [
                'bank_code' => env('SEPAY_BANK_ID', 'MBBank'),
                'account_no' => env('SEPAY_ACCOUNT_NUMBER', '0377417720'),
                'account_name' => env('SEPAY_ACCOUNT_NAME', 'NGUYENHUUTHAI'),
                'amount' => (float)$transaction->so_tien,
                'content' => $transaction->transaction_code
            ];
        }

        return response()->json($data);
    }
}
