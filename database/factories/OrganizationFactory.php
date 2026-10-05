<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    private static array $directorates = [
        'مديرية الأقصر التعليمية',
        'مديرية القاهرة التعليمية',
        'مديرية الإسكندرية التعليمية',
        'مديرية الجيزة التعليمية',
        'مديرية أسوان التعليمية',
    ];

    private static array $administrations = [
        'إدارة أرمنت التعليمية',
        'إدارة الطود التعليمية',
        'إدارة إسنا التعليمية',
        'إدارة الأقصر التعليمية',
        'إدارة الزينية التعليمية',
        'إدارة القرنة التعليمية',
    ];

    private static array $schools = [
        'مدرسة النيل الابتدائية',
        'مدرسة الأمل الإعدادية',
        'مدرسة الشهيد الثانوية',
        'مدرسة الفجر الابتدائية المشتركة',
        'مدرسة الوحدة الإعدادية بنات',
        'مدرسة القدس الثانوية بنين',
        'مدرسة الرسالة الابتدائية',
        'مدرسة النصر الإعدادية المشتركة',
        'مدرسة الربيع الثانوية',
        'مدرسة الوفاء الابتدائية',
        'مدرسة الجيل الجديد الإعدادية',
        'مدرسة المستقبل الثانوية',
    ];

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type'      => OrganizationType::SCHOOL,
            'name'      => $this->faker->unique()->randomElement(self::$schools),
            'code'      => strtoupper($this->faker->bothify('SCH-###')),
            'path'      => '/',   // placeholder — observer rebuilds this
            'depth'     => 2,
            'is_active' => true,
        ];
    }

    // -------------------------------------------------------------------------
    // States
    // -------------------------------------------------------------------------

    public function directorate(): static
    {
        return $this->state(fn() => [
            'parent_id' => null,
            'type'      => OrganizationType::DIRECTORATE,
            'name'      => $this->faker->unique()->randomElement(self::$directorates),
            'code'      => strtoupper($this->faker->bothify('DIR-###')),
            'depth'     => 0,
        ]);
    }

    public function administration(Organization $parent): static
    {
        return $this->state(fn() => [
            'parent_id' => $parent->id,
            'type'      => OrganizationType::ADMINISTRATION,
            'name'      => $this->faker->unique()->randomElement(self::$administrations),
            'code'      => strtoupper($this->faker->bothify('ADM-###')),
            'depth'     => 1,
        ]);
    }

    public function school(Organization $parent): static
    {
        return $this->state(fn() => [
            'parent_id' => $parent->id,
            'type'      => OrganizationType::SCHOOL,
            'name'      => $this->faker->unique()->randomElement(self::$schools),
            'code'      => strtoupper($this->faker->bothify('SCH-###')),
            'depth'     => 2,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }
}
