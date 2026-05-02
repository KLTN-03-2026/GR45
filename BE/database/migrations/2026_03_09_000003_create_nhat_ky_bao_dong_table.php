<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Bang Nhat Ky Bao Dong: Luu toan bo su co phat hien tren chuyen xe.
         * Khi AI phat hien ngu gat / qua toc do, API duoc goi NGAY LAP TUC de ghi vao bang nay.
         */
        Schema::create('nhat_ky_bao_dong', function (Blueprint $table) {
            $table->id();

            // Lien ket hanh trinh
            $table->unsignedBigInteger('id_chuyen_xe');
            $table->foreign('id_chuyen_xe')->references('id')->on('chuyen_xes')->cascadeOnDelete();
            $table->unsignedBigInteger('id_tai_xe');
            $table->foreign('id_tai_xe')->references('id')->on('tai_xes')->restrictOnDelete();
            $table->unsignedBigInteger('id_xe');
            $table->foreign('id_xe')->references('id')->on('xes')->restrictOnDelete();

            // Loai su co va muc
            $table->enum('loai_bao_dong', [
                'ngu_gat',              // AI camera phat hien mat nham / ngu gat
                'qua_toc_do',          // Vuot van toc gioi han
                'phanh_gap',           // Giam toc dot ngot
                'lac_lan',             // Sai lech hanh trinh bat thuong
                'roi_khoi_hanh_trinh', // Ra khoi lo trinh quy dinh
                'khong_phan_hoi',      // Tai xe khong phan hoi canh bao
                'thiet_bi_loi',        // Thiet bi giam sat mat ket noi
                'bao_hiem_het_han',    // Bao hiem xe sap het han
                'dang_kiem_het_han',   // Dang kiem sap het han
                'gplx_het_han',        // Bang lai tai xe sap het han
                'su_dung_dien_thoai',  // Tai xe su dung dien thoai
                'hut_thuoc',           // Tai xe hut thuoc
                'mang_vu_khi',         // Tai xe mang vu khi
                'vi_pham_khac',        // Tai xe vi pham khac
                'khong_quan_sat',      // Tai xe khong quan sat
                'bao_dong_khan_cap',   // Tai xe gui bao dong khan cap
            ]);
            $table->enum('muc_do', ['thong_tin', 'canh_bao', 'nguy_hiem', 'khan_cap'])->default('canh_bao');
            $table->enum('trang_thai', ['moi', 'da_xem', 'da_xu_ly', 'bo_qua'])->default('moi');

            // Du lieu chi tiet luc bao dong (JSON linh hoat tuy loai)
            // Vi du ngu_gat: {"ear": 0.18, "thoi_gian_ms": 2300}
            // Vi du qua_toc_do: {"van_toc_thuc": 95, "gioi_han": 80}
            $table->json('du_lieu_phat_hien')->nullable();

            // Vi tri xe luc bao dong
            $table->decimal('vi_do_luc_bao', 10, 8)->nullable();
            $table->decimal('kinh_do_luc_bao', 11, 8)->nullable();

            // Trang thai thong bao
            $table->boolean('da_canh_bao_tai_xe')->default(false)->comment('Da rung/bip cho tai xe');
            $table->boolean('da_thong_bao_nha_xe')->default(false);
            $table->boolean('da_thong_bao_admin')->default(false);

            // Xu ly su co
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('nha_xe_id')->nullable();
            // người xử lý có thể là nhà xe hoặc admin
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('nha_xe_id')->references('id')->on('nha_xes')->nullOnDelete();

            $table->timestamp('thoi_gian_xu_ly')->nullable();
            $table->text('ghi_chu_xu_ly')->nullable();
            $table->text('anh_url')->nullable();

            $table->timestamps();

            // Index cho query hieu qua
            $table->index(['id_chuyen_xe', 'loai_bao_dong']);
            $table->index(['trang_thai', 'muc_do']);
            $table->index(['id_tai_xe', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhat_ky_bao_dong');
    }
};
