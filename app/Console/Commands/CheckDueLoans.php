<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;

class CheckDueLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'riwaq:check-due-loans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فحص الإعارات النشطة وإرسال إشعارات قبل موعد الاستحقاق وبعد التأخير وفق سياسة المكتبة';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('جاري فحص الإعارات النشطة ومواعيد الاستحقاق...');

        $notifiedCount = Loan::checkAndSendDueNotifications();

        $this->info("تم إرسال {$notifiedCount} إشعاراً للقراء بنجاح.");

        return Command::SUCCESS;
    }
}
