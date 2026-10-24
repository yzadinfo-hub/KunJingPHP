<?php
/**
 * Phase 6 测试夹具：迁移文件
 */

use KunJing\Framework\Database\Migrations\Migration;
use KunJing\Framework\Database\Schema\Blueprint;

class CreateP6DemoTable extends Migration
{
    public function up(): void
    {
        $this->create('p6_demo', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->comment('名称');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropIfExists('p6_demo');
    }
}
