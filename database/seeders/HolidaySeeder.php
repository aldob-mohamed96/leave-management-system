<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        $holidays = [
            // أعياد ثابتة
            ['date' => "{$year}-01-01", 'name' => 'رأس السنة الميلادية'],
            ['date' => "{$year}-01-07", 'name' => 'عيد الميلاد المجيد (الأرثوذكس)'],
            ['date' => "{$year}-01-25", 'name' => 'عيد الشرطة وذكرى ثورة يناير'],
            ['date' => "{$year}-04-25", 'name' => 'عيد تحرير سيناء'],
            ['date' => "{$year}-05-01", 'name' => 'عيد العمال'],
            ['date' => "{$year}-06-30", 'name' => 'ذكرى ثورة 30 يونيو'],
            ['date' => "{$year}-07-23", 'name' => 'عيد ثورة يوليو'],
            ['date' => "{$year}-10-06", 'name' => 'عيد القوات المسلحة'],
            // أعياد متحركة تقريبية لسنة 2026
            ['date' => "{$year}-03-30", 'name' => 'شم النسيم'],
            ['date' => "{$year}-06-06", 'name' => 'عيد الفطر المبارك (اليوم الأول)'],
            ['date' => "{$year}-06-07", 'name' => 'عيد الفطر المبارك (اليوم الثاني)'],
            ['date' => "{$year}-06-08", 'name' => 'عيد الفطر المبارك (اليوم الثالث)'],
            ['date' => "{$year}-08-12", 'name' => 'عيد الأضحى المبارك (اليوم الأول)'],
            ['date' => "{$year}-08-13", 'name' => 'عيد الأضحى المبارك (اليوم الثاني)'],
            ['date' => "{$year}-08-14", 'name' => 'عيد الأضحى المبارك (اليوم الثالث)'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(['date' => $holiday['date']], $holiday);
        }
    }
}
