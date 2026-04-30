<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ tài xế – tách biệt khỏi bảng tai_xes (chỉ chứa auth).
 * Dùng để quản lý duyệt hồ sơ, lưu giấy tờ, CCCD, bằng lái.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ho_so_tai_xes', function (Blueprint $table) {
            $table->id();

            // Liên kết 1-1 với tài xe
            $table->unsignedBigInteger('id_tai_xe')->unique();
            $table->foreign('id_tai_xe')
                ->references('id')->on('tai_xes')->onDelete('cascade');

            // Nhà xe quản lý tài xế
            $table->string('ma_nha_xe')->nullable();
            $table->foreign('ma_nha_xe')
                ->references('ma_nha_xe')->on('nha_xes')->onDelete('set null');

            // ------ Thông tin cá nhân ------
            $table->string('ho_va_ten');
            $table->date('ngay_sinh')->nullable();
            $table->string('so_dien_thoai')->nullable();
            $table->string('email')->nullable();
            $table->string('dia_chi')->nullable();
            $table->string('avatar')->nullable();

            // ------ Giấy tờ CCCD ------
            $table->string('so_cccd')->nullable();
            $table->string('anh_cccd_mat_truoc')->nullable();
            $table->string('anh_cccd_mat_sau')->nullable();

            // ------ Bằng lái GPLX ------
            $table->string('so_gplx')->nullable();
            $table->string('anh_gplx')->nullable();
            $table->string('anh_gplx_mat_sau')->nullable();
            $table->string('hang_bang_lai')->nullable();
            $table->date('ngay_cap_gplx')->nullable();
            $table->date('ngay_het_han_gplx')->nullable();

            // ------ Duyệt hồ sơ ------
            $table->enum('trang_thai_duyet', ['pending', 'approved', 'rejected'])
                ->default('pending');
            $table->text('ly_do_tu_choi')->nullable();

            $table->unsignedBigInteger('nguoi_duyet_id')->nullable();
            $table->foreign('nguoi_duyet_id')
                ->references('id')->on('admins')->onDelete('set null');

            $table->unsignedBigInteger('nguoi_tao_id')->nullable();
            $table->foreign('nguoi_tao_id')
                ->references('id')->on('admins')->onDelete('set null');

            $table->timestamp('ngay_duyet')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ho_so_tai_xes');
    }
};
