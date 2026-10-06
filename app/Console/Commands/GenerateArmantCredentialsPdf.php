<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ArabicPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateArmantCredentialsPdf extends Command
{
    protected $signature = 'armant:credentials-pdf
                            {--path= : Output PDF path (default: project root)}
                            {--sync-db : Also update users passwords/phones in the database}';

    protected $description = 'Generate Armant login credentials PDF/TXT (password 123456789 + Egyptian phones)';

    public function handle(): int
    {
        $jsonPath = database_path('data/armant-schools.json');

        if (! File::exists($jsonPath)) {
            $this->error("Missing: {$jsonPath}");

            return self::FAILURE;
        }

        $payload = json_decode(File::get($jsonPath), true, flags: JSON_THROW_ON_ERROR);
        $adminName = $payload['administration'] ?? 'إدارة أرمنت التعليمية';
        $password = $payload['default_password'] ?? '123456789';

        $rows = [];

        foreach ($payload['admin_accounts'] ?? [] as $account) {
            $rows[] = [
                'org' => $adminName,
                'role' => $account['role'] ?? '—',
                'email' => $account['email'],
                'email_display' => $account['email'],
                'phone' => $account['phone'] ?? '',
            ];
        }

        if ($rows === []) {
            $rows = [
                [
                    'org' => $adminName,
                    'role' => 'مدير الإدارة',
                    'email' => 'armant.manager@armant-schools.edu',
                    'email_display' => 'armant.manager@armant-schools.edu',
                    'phone' => '01000000001',
                ],
                [
                    'org' => $adminName,
                    'role' => 'مسؤول الإجازات',
                    'email' => 'armant.leaves@armant-schools.edu',
                    'email_display' => 'armant.leaves@armant-schools.edu',
                    'phone' => '01000000002',
                ],
            ];
        }

        foreach ($payload['schools'] as $school) {
            foreach ($school['accounts'] as $account) {
                $role = match ($account['role'] ?? '') {
                    'manager' => 'مدير مدرسة',
                    'specialist' => 'أخصائي',
                    default => $account['title'] ?? $account['role'],
                };

                $rows[] = [
                    'org' => $school['name'],
                    'role' => $role,
                    'email' => $account['email'],
                    'email_display' => $account['email'],
                    'phone' => $account['phone'] ?? '',
                ];
            }
        }

        if ($this->option('sync-db')) {
            $this->syncDatabase($rows, $password);
        }

        $html = view('pdf.armant-credentials', [
            'rows' => $rows,
            'password' => $password,
            'loginUrl' => 'https://leave.rtltec.com/admin',
        ])->render();

        $html = ArabicPdf::shapeHtml($html);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $out = $this->option('path')
            ?: base_path('بيانات-تسجيل-الدخول-أرمنت.pdf');

        File::put($out, $pdf->output());

        $txtPath = preg_replace('/\.pdf$/i', '.txt', $out) ?: ($out.'.txt');
        $txt = "إدارة أرمنت التعليمية — بيانات تسجيل الدخول\n";
        $txt .= "كلمة المرور الموحدة: {$password}\n";
        $txt .= "رابط الدخول: https://leave.rtltec.com/admin\n";
        $txt .= "يمكن الدخول بالبريد أو رقم التليفون\n";
        $txt .= str_repeat('-', 88)."\n";
        foreach ($rows as $row) {
            $txt .= "{$row['org']} | {$row['role']} | {$row['email']} | {$row['phone']} | {$password}\n";
        }
        File::put($txtPath, $txt);

        $this->info('PDF: '.$out);
        $this->info('TXT: '.$txtPath);
        $this->info('Accounts: '.count($rows));

        return self::SUCCESS;
    }

    /**
     * @param  list<array{email: string, phone: string}>  $rows
     */
    private function syncDatabase(array $rows, string $password): void
    {
        $updated = 0;

        foreach ($rows as $row) {
            $user = User::where('email', $row['email'])->first();
            if (! $user) {
                continue;
            }

            $user->forceFill([
                'password'             => $password,
                'phone'                => User::normalizePhone($row['phone'] ?: null),
                'must_change_password' => false,
            ])->save();
            $updated++;
        }

        // Any other users: same password, generate phone if missing
        $extra = 0;
        $seq = 9000;
        User::query()
            ->whereNotIn('email', collect($rows)->pluck('email'))
            ->each(function (User $user) use ($password, &$extra, &$seq): void {
                $phone = $user->phone ?: User::normalizePhone(sprintf('0109%07d', $seq++));
                $user->forceFill([
                    'password'             => $password,
                    'phone'                => $phone,
                    'must_change_password' => false,
                ])->save();
                $extra++;
            });

        $this->info("DB synced: {$updated} Armant accounts, {$extra} other users.");
    }
}
