<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sua bang vi_nha_xes: them cot moi de phu hop muc dich vi top-up / ky quy
        Schema::table('vi_nha_xes', function (Blueprint $table) {
            // Doi ten cot cu va them cac cot moi
            $table->after('tong_rut', function (Blueprint $table) {
                $table->decimal('tong_phi_hoa_hong', 15, 2)->default(0)->comment('Tong phi hoa hong da tru');
                $table->decimal('han_muc_toi_thieu', 15, 2)->default(500000)->comment('So du toi thieu de mo ban ve (VND)');
                $table->enum('trang_thai', ['hoat_dong', 'tam_khoa', 'khoa_vinh_vien'])->default('hoat_dong');
                $table->text('ghi_chu_khoa')->nullable();
            });
        });

        // Tao bang lich su giao dich vi nha xe
        Schema::create('lich_su_vi_nha_xes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vi_nha_xe')->constrained('vi_nha_xes')->cascadeOnDelete();
            $table->unsignedBigInteger('id_chuyen_xe')->nullable()->comment('Chuyen xe lien quan (neu co)');
            $table->foreign('id_chuyen_xe')->references('id')->on('chuyen_xes')->nullOnDelete();

            $table->enum('loai', ['nap_tien', 'phi_hoa_hong', 'hoan_tien', 'khac']);
            $table->decimal('so_tien_truoc', 15, 2);
            $table->decimal('so_tien_giao_dich', 15, 2)->comment('Am neu tru, duong neu cong');
            $table->decimal('so_tien_sau', 15, 2);
            $table->string('mo_ta')->nullable();
            $table->unsignedBigInteger('nguoi_thuc_hien')->nullable()->comment('Admin id');
            $table->foreign('nguoi_thuc_hien')->references('id')->on('admins')->nullOnDelete();
            $table->enum('trang_thai', ['thanh_cong', 'that_bai'])->default('thanh_cong');
            $table->text('ghi_chu')->nullable();

            $table->timestamps();

            $table->index('id_vi_nha_xe');
            $table->index('loai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_vi_nha_xes');
        Schema::table('vi_nha_xes', function (Blueprint $table) {
            $table->dropColumn(['tong_phi_hoa_hong', 'han_muc_toi_thieu', 'trang_thai', 'ghi_chu_khoa']);
        });
    }
};
