<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPsgcBarangays extends Command
{
    protected $signature = 'psgc:extract {file} {--province-code=18045} {--extra-code=*}';
    protected $description = 'Extract province (+ optional independent cities) barangays into database/seeders/data/barangays.csv';

    public function handle()
    {
        $spreadsheet = IOFactory::load($this->argument('file'));
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $header = array_map('trim', $rows[0]);

        $codeCol = array_search('10-digit PSGC', $header) !== false
            ? array_search('10-digit PSGC', $header)
            : 0;
        $nameCol = array_search('Name', $header) ?: 1;
        $levelCol = array_search('Geographic Level', $header) ?: 3;

        $provinceCode = $this->option('province-code');
        $extraCodes = $this->option('extra-code');

        $currentCity = null;
        $out = [['city_name', 'barangay_name']];

        foreach (array_slice($rows, 1) as $row) {
            $code = (string) $row[$codeCol];

            $matchesProvince = str_starts_with($code, $provinceCode);
            $matchesExtra = false;
            foreach ($extraCodes as $ec) {
                if ($ec && str_starts_with($code, $ec)) {
                    $matchesExtra = true;
                    break;
                }
            }

            if (!$matchesProvince && !$matchesExtra) continue;

            $level = trim($row[$levelCol]);
            $name = trim(preg_replace('/^(City of|Municipality of)\s+/i', '', (string) $row[$nameCol]));

            if (in_array($level, ['City', 'Mun'])) {
                $currentCity = str_ireplace(' City', '', $name);
            } elseif ($level === 'Bgy' && $currentCity) {
                $out[] = [$currentCity, $name];
            }
        }

        $path = database_path('seeders/data/barangays.csv');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $fp = fopen($path, 'w');
        foreach ($out as $line) fputcsv($fp, $line);
        fclose($fp);

        $this->info('Wrote ' . (count($out) - 1) . ' barangay rows to ' . $path);
    }
}