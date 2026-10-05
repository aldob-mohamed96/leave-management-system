<?php

namespace App\Exports;

use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeaveRequestsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private array $filters = []) {}

    public function query(): Builder
    {
        return LeaveRequest::withoutGlobalScopes()
            ->with(['employee', 'organization', 'leaveType', 'createdBy'])
            ->when($this->filters['ids'] ?? null,
                fn($q, $v) => $q->whereIn('id', $v))
            ->when($this->filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->when($this->filters['organization_id'] ?? null,
                fn($q, $v) => $q->where('organization_id', $v))
            ->when($this->filters['employee_name'] ?? null,
                fn($q, $v) => $q->whereHas('employee', fn($eq) => $eq->where('full_name', 'like', "%{$v}%")))
            ->when($this->filters['leave_type_id'] ?? null,
                fn($q, $v) => $q->where('leave_type_id', $v))
            ->when($this->filters['date_range']['from'] ?? null,
                fn($q, $v) => $q->whereDate('start_date', '>=', $v))
            ->when($this->filters['date_range']['to'] ?? null,
                fn($q, $v) => $q->whereDate('start_date', '<=', $v));
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'رقم الطلب',
            'اسم الموظف',
            'كود الموظف',
            'المدرسة',
            'نوع الإجازة',
            'من',
            'إلى',
            'عدد الأيام',
            'الحالة',
            'مقدم بواسطة',
            'تاريخ التقديم',
            'القرار',
        ];
    }

    /** @return list<mixed> */
    public function map($row): array
    {
        return [
            $row->number,
            $row->employee?->full_name ?? '—',
            $row->employee?->employee_code ?? '—',
            $row->organization?->name ?? '—',
            $row->leaveType?->name ?? '—',
            $row->start_date?->toDateString(),
            $row->end_date?->toDateString(),
            $row->days,
            $row->status->label(),
            $row->createdBy?->name ?? '—',
            $row->submitted_at?->toDateString(),
            $row->decided_at?->toDateString() ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType'   => 'solid',
                'startColor' => ['argb' => 'FF2563EB'],
            ],
        ]);

        return [];
    }
}
