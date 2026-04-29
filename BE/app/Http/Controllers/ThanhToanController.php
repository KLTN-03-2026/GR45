<?php

namespace App\Http\Controllers;

use App\Services\ThanhToanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\Ve;

class ThanhToanController extends Controller
{
    public function __construct(protected ThanhToanService $service) {}

    /**
     * GET /api/v1/admin/thanh-toan
     * Query: ?search=...&trang_thai=1&phuong_thuc=1&tu_ngay=...&den_ngay=...&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getAll($request->only([
                'search',
                'trang_thai',
                'phuong_thuc',
                'tu_ngay',
                'den_ngay',
                'per_page',
            ]));

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/v1/admin/thanh-toan/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $thanhToan = $this->service->getById($id);

            return response()->json([
                'success' => true,
                'data'    => $thanhToan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/v1/admin/thanh-toan/thong-ke
     * Query: ?tu_ngay=...&den_ngay=...&phuong_thuc=1&trang_thai=1
     */
    public function thongKe(Request $request): JsonResponse
    {
        try {
            $data = $this->service->thongKe($request->only([
                'tu_ngay',
                'den_ngay',
                'phuong_thuc',
                'trang_thai',
            ]));

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle SePay Webhook
     * POST /api/v1/sepay/webhook
     */
    public function sepayWebhook(Request $request): JsonResponse
    {
        try {
            // 1. Verify API Key
            $apiKey = env('SEPAY_API_KEY');
            $headerKey = $request->header('Authorization');

            if (!$headerKey || $headerKey !== 'Apikey ' . $apiKey) {
                Log::warning('SePay Webhook: Unauthorized access attempt', ['header' => $headerKey]);
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // 2. Extract Data
            $data = $request->all();

            // 3. Prevent duplicate transaction
            $exists = Transaction::where('sepay_id', $data['id'])->exists();
            if ($exists) {
                // Return success so SePay doesn't retry
                return response()->json(['success' => true, 'message' => 'Transaction already processed'], 200);
            }

            // 4. Save Transaction to DB
            $transaction = Transaction::create([
                'sepay_id' => $data['id'],
                'gateway' => $data['gateway'] ?? '',
                'transaction_date' => $data['transactionDate'] ?? now(),
                'account_number' => $data['accountNumber'] ?? null,
                'sub_account' => $data['subAccount'] ?? null,
                'amount_in' => isset($data['transferType']) && $data['transferType'] === 'in' ? $data['transferAmount'] : 0,
                'amount_out' => isset($data['transferType']) && $data['transferType'] === 'out' ? $data['transferAmount'] : 0,
                'accumulated' => $data['accumulated'] ?? 0,
                'code' => $data['code'] ?? null,
                'transaction_content' => $data['content'] ?? null,
                'reference_number' => $data['referenceCode'] ?? null,
                'body' => $data['description'] ?? null,
            ]);

            // 5. Update Ticket Status
            if (isset($data['transferType']) && $data['transferType'] === 'in' && !empty($data['content'])) {
                $content = strtoupper($data['content']);
                
                // Find ticket by matching ma_ve inside the transaction content
                $ve = Ve::where('tinh_trang', 'dang_cho')
                    ->whereRaw("? LIKE CONCAT('%', ma_ve, '%')", [$content])
                    ->first();

                if ($ve) {
                    $amountRequired = $ve->tong_tien > 0 ? $ve->tong_tien : ($ve->tien_ban_dau - $ve->tien_khuyen_mai);
                    
                    if ($data['transferAmount'] >= $amountRequired) {
                        $ve->tinh_trang = 'da_thanh_toan';
                        $ve->phuong_thuc_thanh_toan = 'chuyen_khoan';
                        $ve->thoi_gian_thanh_toan = now();
                        $ve->save();

                        // Phát sự kiện realtime qua Pusher
                        event(new \App\Events\VeDaThanhToanEvent($ve));

                        Log::info('SePay Webhook: Ticket paid successfully', ['ma_ve' => $ve->ma_ve, 'sepay_id' => $data['id']]);
                    } else {
                        Log::warning('SePay Webhook: Insufficient amount', [
                            'ma_ve' => $ve->ma_ve, 
                            'expected' => $amountRequired, 
                            'received' => $data['transferAmount']
                        ]);
                    }
                }
            }

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('SePay Webhook Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Return 400 so SePay will retry if there's a temporary DB error
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 400);
        }
    }
}
