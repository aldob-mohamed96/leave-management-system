<?php

use App\Enums\ApprovalRule;
use App\Enums\EntitlementGrade;
use App\Enums\LeaveStatus;
use App\Enums\OrganizationType;
use App\Enums\StepStatus;
use App\Enums\TransactionType;

// ---------------------------------------------------------------------------
// OrganizationType
// ---------------------------------------------------------------------------

describe('OrganizationType', function () {
    it('has Arabic labels', function () {
        expect(OrganizationType::DIRECTORATE->label())->toBe('مديرية');
        expect(OrganizationType::ADMINISTRATION->label())->toBe('إدارة تعليمية');
        expect(OrganizationType::SCHOOL->label())->toBe('مدرسة');
    });

    it('returns correct depth', function () {
        expect(OrganizationType::DIRECTORATE->depth())->toBe(0);
        expect(OrganizationType::ADMINISTRATION->depth())->toBe(1);
        expect(OrganizationType::SCHOOL->depth())->toBe(2);
    });

    it('returns correct child type', function () {
        expect(OrganizationType::DIRECTORATE->childType())->toBe(OrganizationType::ADMINISTRATION);
        expect(OrganizationType::ADMINISTRATION->childType())->toBe(OrganizationType::SCHOOL);
        expect(OrganizationType::SCHOOL->childType())->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// LeaveStatus
// ---------------------------------------------------------------------------

describe('LeaveStatus', function () {
    it('has Arabic labels for all cases', function () {
        expect(LeaveStatus::DRAFT->label())->toBe('مسودة');
        expect(LeaveStatus::SUBMITTED->label())->toBe('مقدّم');
        expect(LeaveStatus::IN_REVIEW->label())->toBe('قيد المراجعة');
        expect(LeaveStatus::RETURNED->label())->toBe('مُعاد للتعديل');
        expect(LeaveStatus::APPROVED->label())->toBe('معتمد');
        expect(LeaveStatus::REJECTED->label())->toBe('مرفوض');
        expect(LeaveStatus::CANCELLED->label())->toBe('تم حذفه');
    });

    it('identifies terminal statuses correctly', function () {
        expect(LeaveStatus::APPROVED->isTerminal())->toBeTrue();
        expect(LeaveStatus::REJECTED->isTerminal())->toBeTrue();
        expect(LeaveStatus::CANCELLED->isTerminal())->toBeTrue();
        expect(LeaveStatus::SUBMITTED->isTerminal())->toBeFalse();
        expect(LeaveStatus::IN_REVIEW->isTerminal())->toBeFalse();
    });

    it('identifies cancellable statuses', function () {
        expect(LeaveStatus::DRAFT->canBeCancelled())->toBeTrue();
        expect(LeaveStatus::SUBMITTED->canBeCancelled())->toBeTrue();
        expect(LeaveStatus::IN_REVIEW->canBeCancelled())->toBeTrue();
        expect(LeaveStatus::RETURNED->canBeCancelled())->toBeTrue();
        expect(LeaveStatus::APPROVED->canBeCancelled())->toBeTrue();  // approved can be cancelled (refunds balance)
        expect(LeaveStatus::REJECTED->canBeCancelled())->toBeFalse();
        expect(LeaveStatus::CANCELLED->canBeCancelled())->toBeFalse();
    });

    it('identifies editable statuses', function () {
        expect(LeaveStatus::DRAFT->canBeEdited())->toBeTrue();
        expect(LeaveStatus::RETURNED->canBeEdited())->toBeTrue();
        expect(LeaveStatus::SUBMITTED->canBeEdited())->toBeFalse();
        expect(LeaveStatus::APPROVED->canBeEdited())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// EntitlementGrade
// ---------------------------------------------------------------------------

describe('EntitlementGrade', function () {
    it('returns correct yearly days per grade', function () {
        expect(EntitlementGrade::TEACHER->yearlyDays())->toBe(28);
        expect(EntitlementGrade::TEACHER_FIRST->yearlyDays())->toBe(30);
        expect(EntitlementGrade::TEACHER_FIRST_A->yearlyDays())->toBe(35);
        expect(EntitlementGrade::TEACHER_EXPERT->yearlyDays())->toBe(40);
        expect(EntitlementGrade::TEACHER_SENIOR->yearlyDays())->toBe(45);
        expect(EntitlementGrade::ADMIN_4->yearlyDays())->toBe(28);
        expect(EntitlementGrade::ADMIN_3->yearlyDays())->toBe(30);
        expect(EntitlementGrade::ADMIN_OVER_50->yearlyDays())->toBe(50);
    });

    it('correctly identifies teacher grades', function () {
        expect(EntitlementGrade::TEACHER->isTeacher())->toBeTrue();
        expect(EntitlementGrade::TEACHER_SENIOR->isTeacher())->toBeTrue();
        expect(EntitlementGrade::ADMIN_4->isTeacher())->toBeFalse();
        expect(EntitlementGrade::ADMIN_OVER_50->isTeacher())->toBeFalse();
    });

    it('has Arabic labels', function () {
        expect(EntitlementGrade::TEACHER->label())->toBe('معلم');
        expect(EntitlementGrade::TEACHER_FIRST->label())->toBe('معلم أول');
        expect(EntitlementGrade::TEACHER_FIRST_A->label())->toBe('معلم أول (أ)');
        expect(EntitlementGrade::TEACHER_EXPERT->label())->toBe('معلم خبير');
        expect(EntitlementGrade::TEACHER_SENIOR->label())->toBe('كبير معلمين');
        expect(EntitlementGrade::ADMIN_OVER_50->label())->toBe('موظف (فوق 50 سنة)');
    });
});

// ---------------------------------------------------------------------------
// ApprovalRule
// ---------------------------------------------------------------------------

describe('ApprovalRule', function () {
    it('ALL requires all approvers', function () {
        expect(ApprovalRule::ALL->isStageApproved(3, 3))->toBeTrue();
        expect(ApprovalRule::ALL->isStageApproved(2, 3))->toBeFalse();
        expect(ApprovalRule::ALL->isStageApproved(0, 3))->toBeFalse();
    });

    it('ANY requires at least one approver', function () {
        expect(ApprovalRule::ANY->isStageApproved(1, 3))->toBeTrue();
        expect(ApprovalRule::ANY->isStageApproved(0, 3))->toBeFalse();
    });

    it('MAJORITY requires more than half', function () {
        expect(ApprovalRule::MAJORITY->isStageApproved(2, 3))->toBeTrue();  // 2 > 1.5
        expect(ApprovalRule::MAJORITY->isStageApproved(1, 3))->toBeFalse(); // 1 < 1.5
        expect(ApprovalRule::MAJORITY->isStageApproved(3, 4))->toBeTrue();  // 3 > 2
        expect(ApprovalRule::MAJORITY->isStageApproved(2, 4))->toBeFalse(); // 2 = 2, not > 2
        expect(ApprovalRule::MAJORITY->isStageApproved(0, 0))->toBeFalse(); // edge: no approvers
    });
});

// ---------------------------------------------------------------------------
// TransactionType
// ---------------------------------------------------------------------------

describe('TransactionType', function () {
    it('identifies credit types correctly', function () {
        expect(TransactionType::ACCRUAL->isCredit())->toBeTrue();
        expect(TransactionType::REFUND->isCredit())->toBeTrue();
        expect(TransactionType::CARRYOVER->isCredit())->toBeTrue();
        expect(TransactionType::DEDUCTION->isCredit())->toBeFalse();
        expect(TransactionType::ADJUSTMENT->isCredit())->toBeFalse();
    });

    it('identifies debit types correctly', function () {
        expect(TransactionType::DEDUCTION->isDebit())->toBeTrue();
        expect(TransactionType::ACCRUAL->isDebit())->toBeFalse();
    });
});
