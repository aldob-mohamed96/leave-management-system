<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LeaveRequestPdfService
{
    /**
     * Generate a PDF binary string for the given leave request.
     */
    public function generate(LeaveRequest $request): string
    {
        // Pre-generate the QR code as inline SVG
        $qrCode = '';
        try {
            $qrCode = QrCode::format('svg')
                ->size(80)
                ->generate(route('leave.verify', ['number' => $request->number]));
        } catch (\Throwable) {
            // QR generation failure is non-fatal — PDF will render without QR
        }

        // Convert days to Arabic words
        $daysInWords = $this->daysToArabicWords((int) $request->days);

        $pdf = Pdf::loadView('pdf.leave-request', [
            'leaveRequest' => $request,
            'qrCode'       => $qrCode,
            'daysInWords'  => $daysInWords,
        ]);

        return $pdf->output();
    }

    /**
     * Convert an integer number of days to Arabic words.
     */
    private function daysToArabicWords(int $days): string
    {
        $ones = [
            '', 'يوم واحد', 'يومان', 'ثلاثة أيام', 'أربعة أيام', 'خمسة أيام',
            'ستة أيام', 'سبعة أيام', 'ثمانية أيام', 'تسعة أيام', 'عشرة أيام',
            'أحد عشر يوماً', 'اثنا عشر يوماً', 'ثلاثة عشر يوماً', 'أربعة عشر يوماً',
            'خمسة عشر يوماً', 'ستة عشر يوماً', 'سبعة عشر يوماً', 'ثمانية عشر يوماً',
            'تسعة عشر يوماً',
        ];

        $tens = [
            '', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون',
            'ستون', 'سبعون', 'ثمانون', 'تسعون',
        ];

        if ($days <= 0) {
            return 'صفر أيام';
        }

        if ($days < 20) {
            return $ones[$days];
        }

        if ($days < 100) {
            $ten  = (int) ($days / 10);
            $rest = $days % 10;

            if ($rest === 0) {
                return $tens[$ten] . ' يوماً';
            }

            return $ones[$rest] . ' و' . $tens[$ten];
        }

        return "{$days} يوماً";
    }
}
