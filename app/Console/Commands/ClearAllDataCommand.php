<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class ClearAllDataCommand extends Command
{
    protected $signature   = 'app:clear-all-data';
    protected $description = 'حذف كل البيانات مع الاحتفاظ بحساب السوبر أدمن فقط';

    public function handle(): int
    {
        $adminEmail = 'admin@rtltec.com';
        $admin = \App\Models\User::where('email', $adminEmail)->first();

        if (! $admin) {
            $this->error("السوبر أدمن ($adminEmail) غير موجود — أوقف العملية.");
            return 1;
        }

        $adminId = $admin->id;
        $this->info("✓ حساب الأدمن محفوظ: {$admin->email} (ID: {$adminId})");

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // حذف طلبات الإجازة
        DB::table('leave_balance_transactions')->truncate();
        DB::table('leave_request_steps')->truncate();
        DB::table('leave_requests')->truncate();
        DB::table('leave_balances')->truncate();
        $this->info('✓ طلبات الإجازة والأرصدة محذوفة');

        // حذف الموظفين
        DB::table('employees')->truncate();
        $this->info('✓ الموظفون محذوفون');

        // حذف المستخدمين ماعدا الأدمن
        DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', '!=', $adminId)
            ->delete();
        DB::table('model_has_permissions')
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', '!=', $adminId)
            ->delete();
        DB::table('users')->where('id', '!=', $adminId)->delete();
        $this->info('✓ المستخدمون محذوفون ماعدا الأدمن');

        // حذف المنظمات والـ workflow
        DB::table('workflow_configurations')->truncate();
        DB::table('organizations')->truncate();
        $this->info('✓ المدارس والمنظمات محذوفة');

        // إعادة تعيين الأدمن بدون org
        DB::table('users')->where('id', $adminId)->update(['organization_id' => null]);
        $this->info('✓ الأدمن بدون مؤسسة الآن');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->newLine();
        $this->info('══════════════════════════════════');
        $this->info('  تم الحذف بنجاح');
        $this->info('  المستخدمون المتبقيون: ' . \App\Models\User::count());
        $this->info('  الأدمن: ' . $admin->email);
        $this->info('  الباسورد: Admin@2026!');
        $this->info('══════════════════════════════════');

        return 0;
    }
}
