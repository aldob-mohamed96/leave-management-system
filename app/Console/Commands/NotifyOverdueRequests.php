<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Console\Command;

class NotifyOverdueRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:notify-overdue {--days=3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إشعار المستخدمين بطلبات الإجازة المتأخرة';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        $requests = LeaveRequest::withoutGlobalScopes()
            ->overdue($days)
            ->with(['createdBy', 'organization', 'employee'])
            ->get();

        $count = 0;

        foreach ($requests as $request) {
            try {
                $message = "طلب الإجازة رقم {$request->number} لم يُتخذ فيه إجراء منذ {$days} أيام";

                // Notify the submitter
                if ($request->createdBy) {
                    $request->createdBy->notify(
                        new LeaveRequestNotification($request, 'overdue', $message)
                    );
                }

                // Notify organization managers
                if ($request->organization_id) {
                    try {
                        setPermissionsTeamId($request->organization_id);
                        $managers = User::where('organization_id', $request->organization_id)
                            ->where('is_active', true)
                            ->get()
                            ->filter(fn($u) => $u->hasRole('مدير الإدارة'));
                        setPermissionsTeamId(null);

                        foreach ($managers as $manager) {
                            $manager->notify(
                                new LeaveRequestNotification($request, 'overdue', $message)
                            );
                        }
                    } catch (\Throwable $e) {
                        setPermissionsTeamId(null);
                        $this->warn("خطأ أثناء إرسال إشعارات المدير لطلب {$request->number}: " . $e->getMessage());
                    }
                }

                $count++;
            } catch (\Throwable $e) {
                $this->warn("خطأ أثناء معالجة طلب {$request->number}: " . $e->getMessage());
            }
        }

        $this->info("تم إرسال التنبيهات لـ {$count} طلب متأخر.");

        return Command::SUCCESS;
    }
}
