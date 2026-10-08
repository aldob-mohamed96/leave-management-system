<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\LeaveBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnualLeaveRollover extends Command
{
    /**
     * اسم وتوقيع الأمر.
     *
     * @var string
     */
    protected $signature = 'leave:year-end
                            {--year= : السنة المستهدفة (افتراضي: السنة الحالية)}
                            {--dry-run : معاينة بدون حفظ}';

    /**
     * وصف الأمر.
     *
     * @var string
     */
    protected $description = 'تجديد أرصدة الإجازات السنوية لجميع الموظفين النشطين';

    /**
     * إنشاء نسخة جديدة من الأمر.
     */
    public function __construct(private readonly LeaveBalanceService $service)
    {
        parent::__construct();
    }

    /**
     * تنفيذ الأمر.
     */
    public function handle(): int
    {
        $year      = (int) ($this->option('year') ?? now()->year);
        $isDryRun  = (bool) $this->option('dry-run');

        $employees = Employee::with(['leaveBalances', 'entitlementGrade'])
            ->where('is_active', true)
            ->get();

        if ($isDryRun) {
            $this->warn("⚠️  وضع المعاينة (dry-run) — لن يتم حفظ أي تغييرات.");
            DB::beginTransaction();
        }

        $count = 0;

        $this->withProgressBar($employees, function (Employee $emp) use (&$count, $year, $isDryRun) {
            try {
                DB::transaction(function () use ($emp, $year) {
                    $this->service->carryOver($emp, $year - 1, $year);
                    $this->service->accrueAnnual($emp, $year);
                });
                $count++;
            } catch (\Throwable $e) {
                Log::warning("AnnualLeaveRollover: فشل معالجة الموظف [{$emp->id}] — " . $e->getMessage());
                $this->warn("\nخطأ أثناء معالجة الموظف [{$emp->id}]: " . $e->getMessage());
            }
        });

        $this->newLine();

        if ($isDryRun) {
            DB::rollBack();
            $this->info("(معاينة) سيتم تجديد أرصدة {$count} موظف لسنة {$year} — لم يُحفَظ شيء.");
        } else {
            $this->info("تم تجديد أرصدة {$count} موظف لسنة {$year} بنجاح.");
        }

        return Command::SUCCESS;
    }
}
