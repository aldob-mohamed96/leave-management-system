<?php

namespace App\Console\Commands;

use App\Support\ArabicPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateArmantCredentialsPdf extends Command
{
    protected $signature = 'armant:credentials-pdf
                            {--path= : Output PDF path (default: project root)}';

    protected $description = 'Generate Armant schools login credentials PDF (plain-text emails, password 12345678)';

    public function handle(): int
    {
        $jsonPath = database_path('data/armant-schools.json');

        if (! File::exists($jsonPath)) {
            $this->error("Missing: {$jsonPath}");

            return self::FAILURE;
        }

        $payload = json_decode(File::get($jsonPath), true, flags: JSON_THROW_ON_ERROR);
        $adminName = $payload['administration'] ?? 'إدارة أرمنت التعليمية';
        $password = '12345678';

        $rows = [
            [
                'org' => $adminName,
                'role' => 'مدير الإدارة',
                'email' => 'armant.manager@armant-schools.edu',
                'email_display' => $this->plainEmail('armant.manager@armant-schools.edu'),
            ],
            [
                'org' => $adminName,
                'role' => 'مسؤول الإجازات',
                'email' => 'armant.leaves@armant-schools.edu',
                'email_display' => $this->plainEmail('armant.leaves@armant-schools.edu'),
            ],
        ];

        foreach ($payload['schools'] as $school) {
            foreach ($school['accounts'] as $account) {
                $role = match ($account['role'] ?? '') {
                    'manager' => 'مدير مدرسة',
                    'specialist' => 'أخصائي',
                    default => $account['title'] ?? $account['role'],
                };

                $email = $account['email'];
                $rows[] = [
                    'org' => $school['name'],
                    'role' => $role,
                    'email' => $email,
                    'email_display' => $this->plainEmail($email),
                ];
            }
        }

        $html = view('pdf.armant-credentials', [
            'rows' => $rows,
            'loginUrl' => 'https://leave.rtltec.com/admin',
        ])->render();

        $html = ArabicPdf::shapeHtml($html);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $out = $this->option('path')
            ?: base_path('بيانات-تسجيل-الدخول-أرمنت.pdf');

        File::put($out, $pdf->output());

        // Plain-text companion for easy copy (no mailto behavior).
        $txtPath = preg_replace('/\.pdf$/i', '.txt', $out) ?: ($out.'.txt');
        $txt = "إدارة أرمنت التعليمية — بيانات تسجيل الدخول\n";
        $txt .= "كلمة المرور الموحدة: {$password}\n";
        $txt .= "رابط الدخول: https://leave.rtltec.com/admin\n";
        $txt .= str_repeat('-', 72)."\n";
        foreach ($rows as $row) {
            $txt .= "{$row['org']} | {$row['role']} | {$row['email']} | {$password}\n";
        }
        File::put($txtPath, $txt);

        $this->info('PDF: '.$out);
        $this->info('TXT: '.$txtPath);
        $this->info('Accounts: '.count($rows));

        return self::SUCCESS;
    }

    /**
     * Insert zero-width spaces so PDF viewers do not treat the address as mailto:.
     */
    private function plainEmail(string $email): string
    {
        // ZWSP (U+200B) around @ breaks auto-link detection in most PDF viewers.
        return str_replace('@', "\u{200B}@\u{200B}", $email);
    }
}
