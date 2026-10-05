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
        } catch (\Throwable $e) {
            // QR generation failure is non-fatal — PDF will render without QR
            \Illuminate\Support\Facades\Log::warning('[LeaveRequestPdfService] QR code generation failed.', [
                'number' => $request->number,
                'error'  => $e->getMessage(),
            ]);
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
     * Examples: 1 → يوم واحد, 2 → يومان, 21 → واحد وعشرون يوماً, 45 → خمسة وأربعون يوماً
     */
    private function daysToArabicWords(int $days): string
    {
        // Full phrases for 1–19 (standalone — not used in compound numbers)
        $standalone = [
            '', 'يوم واحد', 'يومان', 'ثلاثة أيام', 'أربعة أيام', 'خمسة أيام',
            'ستة أيام', 'سبعة أيام', 'ثمانية أيام', 'تسعة أيام', 'عشرة أيام',
            'أحد عشر يوماً', 'اثنا عشر يوماً', 'ثلاثة عشر يوماً', 'أربعة عشر يوماً',
            'خمسة عشر يوماً', 'ستة عشر يوماً', 'سبعة عشر يوماً', 'ثمانية عشر يوماً',
            'تسعة عشر يوماً',
        ];

        // Unit words for use in compound numbers (without "أيام" suffix)
        $units = [
            '', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة',
            'ستة', 'سبعة', 'ثمانية', 'تسعة',
        ];

        $tens = [
            '', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون',
            'ستون', 'سبعون', 'ثمانون', 'تسعون',
        ];

        if ($days <= 0) {
            return 'صفر أيام';
        }

        if ($days < 20) {
            return $standalone[$days];
        }

        if ($days < 100) {
            $ten  = (int) ($days / 10);
            $rest = $days % 10;

            if ($rest === 0) {
                return $tens[$ten] . ' يوماً';
            }

            // Arabic grammar: units + و + tens + يوماً
            // e.g. 25 → "خمسة وعشرون يوماً"
            return $units[$rest] . ' و' . $tens[$ten] . ' يوماً';
        }

        return "{$days} يوماً";
    }
}
