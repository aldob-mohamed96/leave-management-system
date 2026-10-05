<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    private static array $arabicNames = [
        'محمد أحمد السيد', 'علي حسن محمود', 'فاطمة إبراهيم الشيخ',
        'أحمد عبدالله زيدان', 'مريم خالد العربي', 'عمر يوسف مرسي',
        'نور الدين طارق البسيوني', 'هند سامي الجمل', 'خالد وليد صالح',
        'سارة كريم جمال', 'إبراهيم ماهر فاروق', 'دينا حسن العوضي',
    ];

    public function definition(): array
    {
        return [
            'name'              => $this->faker->randomElement(self::$arabicNames),
            'email'             => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'organization_id'   => null,
            'is_active'         => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn() => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function inOrganization(Organization $organization): static
    {
        return $this->state(fn() => ['organization_id' => $organization->id]);
    }

    /** Super-admin: no organization_id (sees everything) */
    public function superAdmin(): static
    {
        return $this->state(fn() => ['organization_id' => null]);
    }
}
