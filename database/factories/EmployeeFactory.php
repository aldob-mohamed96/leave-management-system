<?php

namespace Database\Factories;

use App\Enums\EntitlementGrade as EntitlementGradeEnum;
use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    private static array $maleFirstNames = [
        'محمد', 'أحمد', 'علي', 'عمر', 'إبراهيم', 'خالد', 'يوسف', 'عبدالله',
        'حسن', 'حسين', 'مصطفى', 'طارق', 'كريم', 'سامي', 'وليد', 'ماهر',
    ];

    private static array $femaleFirstNames = [
        'فاطمة', 'مريم', 'نور', 'هند', 'سارة', 'دينا', 'رنا', 'منى',
        'نهى', 'إيمان', 'سمر', 'هبة', 'ريم', 'لمياء', 'أسماء', 'شيماء',
    ];

    private static array $lastNames = [
        'محمود', 'إبراهيم', 'السيد', 'عبدالعزيز', 'عبدالرحمن', 'حسن',
        'عبدالله', 'الشيخ', 'الخطيب', 'العوضي', 'البسيوني', 'زيدان',
        'مرسي', 'صالح', 'جمال', 'فاروق', 'العربي', 'الجمل',
    ];

    private static array $jobTitles = [
        'معلم أول',
        'معلم',
        'معلم مساعد',
        'معلم أول أ',
        'معلم تربية بدنية',
        'معلم تربية فنية',
        'معلم لغة إنجليزية',
        'معلم رياضيات',
        'معلم علوم',
        'معلم عربي',
        'أخصائي اجتماعي',
        'أمين مكتبة',
        'سكرتير مدرسة',
        'ناظر',
        'وكيل مدرسة',
    ];

    private static array $grades = [
        'الدرجة الأولى',
        'الدرجة الثانية',
        'الدرجة الثالثة',
        'الكادر الخاص',
        'درجة ممتازة',
    ];

    public function definition(): array
    {
        $isMale    = $this->faker->boolean(60);
        $firstName = $isMale
            ? $this->faker->randomElement(self::$maleFirstNames)
            : $this->faker->randomElement(self::$femaleFirstNames);
        $lastName  = $this->faker->randomElement(self::$lastNames);
        $fatherName = $this->faker->randomElement(self::$maleFirstNames);

        $hireDate      = $this->faker->dateTimeBetween('-20 years', '-1 year');
        $workStartDate = $this->faker->dateTimeBetween($hireDate, 'now');

        return [
            'organization_id'   => Organization::factory(),
            'user_id'           => null,
            'employee_code'     => $this->faker->unique()->numerify('EMP-######'),
            'full_name'         => "{$firstName} {$fatherName} {$lastName}",
            'job_title'         => $this->faker->randomElement(self::$jobTitles),
            'grade'             => $this->faker->randomElement(self::$grades),
            'entitlement_grade' => $this->faker->randomElement(
                array_column(EntitlementGradeEnum::cases(), 'value')
            ),
            'birth_date'        => $this->faker->dateTimeBetween('-55 years', '-25 years'),
            'hire_date'         => $hireDate,
            'work_start_date'   => $workStartDate,
            'phone'             => '01'.$this->faker->numerify('#########'),
            'is_active'         => true,
        ];
    }

    /**
     * Override store() to suppress model observers when creating via factory.
     *
     * The EmployeeObserver fires accrueAnnual on every created event. Test
     * fixtures set up their own balance state explicitly, so the observer
     * must not run during factory-driven employee creation.
     */
    protected function store(Collection $results): void
    {
        Employee::withoutEvents(function () use ($results) {
            parent::store($results);
        });
    }

    // -------------------------------------------------------------------------
    // States
    // -------------------------------------------------------------------------

    public function inOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function teacher(): static
    {
        return $this->state(fn () => [
            'job_title' => $this->faker->randomElement(['معلم أول', 'معلم', 'معلم مساعد']),
        ]);
    }

    public function principal(): static
    {
        return $this->state(fn () => [
            'job_title' => $this->faker->randomElement(['ناظر', 'وكيل مدرسة']),
        ]);
    }

    public function withGrade(EntitlementGradeEnum|string $grade): static
    {
        $code = $grade instanceof EntitlementGradeEnum ? $grade->value : $grade;

        return $this->state(fn () => ['entitlement_grade' => $code]);
    }

    public function over50(): static
    {
        return $this->state(fn () => [
            'birth_date'        => $this->faker->dateTimeBetween('-60 years', '-51 years'),
            'entitlement_grade' => EntitlementGradeEnum::ADMIN_OVER_50->value,
        ]);
    }
}
