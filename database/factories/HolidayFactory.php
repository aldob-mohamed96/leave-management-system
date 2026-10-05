<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    private static array $egyptianHolidays = [
        ['name' => 'رأس السنة الميلادية',    'month' => 1,  'day' => 1],
        ['name' => 'عيد الشرطة',             'month' => 1,  'day' => 25],
        ['name' => 'ثورة 25 يناير',          'month' => 1,  'day' => 25],
        ['name' => 'عيد تحرير سيناء',        'month' => 4,  'day' => 25],
        ['name' => 'شم النسيم',              'month' => 4,  'day' => 29],
        ['name' => 'عيد العمال',              'month' => 5,  'day' => 1],
        ['name' => 'ثورة 30 يونيو',          'month' => 6,  'day' => 30],
        ['name' => 'عيد ثورة يوليو',         'month' => 7,  'day' => 23],
        ['name' => 'عيد القوات المسلحة',     'month' => 10, 'day' => 6],
    ];

    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->dateTimeBetween('2026-01-01', '2026-12-31'),
            'name' => $this->faker->randomElement(['يوم عطلة', 'إجازة رسمية']),
        ];
    }

    /** Create a specific Egyptian national holiday for the given year */
    public function egyptianHoliday(int $index, int $year = 2026): static
    {
        $h = self::$egyptianHolidays[$index % count(self::$egyptianHolidays)];
        return $this->state(fn() => [
            'date' => sprintf('%d-%02d-%02d', $year, $h['month'], $h['day']),
            'name' => $h['name'],
        ]);
    }
}
