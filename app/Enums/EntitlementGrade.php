<?php

namespace App\Enums;

/**
 * الدرجة الوظيفية التي تحدد عدد أيام الإجازة الاعتيادية المستحقة.
 * مستقلة عن حقل `grade` النصي الحر — هذه قيم مقيّدة لحساب الرصيد.
 */
enum EntitlementGrade: string
{
    case TEACHER        = 'teacher';        // معلم           → 28 يوم
    case TEACHER_FIRST  = 'teacher_first';  // معلم أول        → 30 يوم
    case TEACHER_FIRST_A = 'teacher_first_a'; // معلم أول أ    → 35 يوم
    case TEACHER_EXPERT = 'teacher_expert'; // معلم خبير       → 40 يوم
    case TEACHER_SENIOR = 'teacher_senior'; // معلم كبير       → 45 يوم
    case ADMIN_4        = 'admin_4';        // إداري د. رابعة  → 28 يوم
    case ADMIN_3        = 'admin_3';        // إداري د. ثالثة  → 30 يوم
    case ADMIN_OVER_50  = 'admin_over50';   // فوق 50 سنة      → 50 يوم

    public function label(): string
    {
        return match($this) {
            self::TEACHER         => 'معلم',
            self::TEACHER_FIRST   => 'معلم أول',
            self::TEACHER_FIRST_A => 'معلم أول أ',
            self::TEACHER_EXPERT  => 'معلم خبير',
            self::TEACHER_SENIOR  => 'معلم كبير',
            self::ADMIN_4         => 'إداري الدرجة الرابعة',
            self::ADMIN_3         => 'إداري الدرجة الثالثة',
            self::ADMIN_OVER_50   => 'موظف (فوق 50 سنة)',
        };
    }

    /**
     * عدد أيام الإجازة الاعتيادية المستحقة سنوياً لهذه الدرجة.
     * يُستخدم عند إنشاء/تجديد رصيد الإجازة الاعتيادية كل سنة.
     */
    public function yearlyDays(): int
    {
        return match($this) {
            self::TEACHER         => 28,
            self::TEACHER_FIRST   => 30,
            self::TEACHER_FIRST_A => 35,
            self::TEACHER_EXPERT  => 40,
            self::TEACHER_SENIOR  => 45,
            self::ADMIN_4         => 28,
            self::ADMIN_3         => 30,
            self::ADMIN_OVER_50   => 50,
        };
    }

    /**
     * هل هذه الدرجة تخص الكادر التعليمي (معلمين)؟
     */
    public function isTeacher(): bool
    {
        return in_array($this, [
            self::TEACHER,
            self::TEACHER_FIRST,
            self::TEACHER_FIRST_A,
            self::TEACHER_EXPERT,
            self::TEACHER_SENIOR,
        ]);
    }
}
