<?php
// admin/export_functions.php
// This file contains export functions for PDF and Excel

// Include Composer autoloader (installs dompdf/dompdf and its dependencies)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/psr_simple_cache_compat.php';

spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $baseDir = __DIR__ . '/../includes/PhpSpreadsheet/src/PhpSpreadsheet/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

/**
 * Export timelogs to PDF
 */
function exportTimelogsPDF($timelogs, $date_from, $date_to, $total_hours, $total_days, $total_interns, $avg_hours) {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="timelogs_' . date('Y-m-d') . '.pdf"');
    
    // Create PDF content
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Time Logs Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #D32F2F; text-align: center; border-bottom: 2px solid #D32F2F; padding-bottom: 10px; }
            .header-info { margin-bottom: 20px; }
            .header-info table { width: 100%; }
            .header-info td { padding: 5px; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th { background: #D32F2F; color: white; padding: 8px 10px; text-align: left; }
            td { padding: 6px 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f9f9f9; }
            .summary { margin-top: 20px; background: #f5f5f5; padding: 15px; border-radius: 5px; }
            .summary table { width: auto; margin: 0 auto; }
            .summary td { padding: 5px 15px; border: none; }
            .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #999; border-top: 1px solid #ddd; padding-top: 15px; }
            .status-active { color: #16a34a; font-weight: bold; }
            .status-completed { color: #3b82f6; font-weight: bold; }
            .status-missed { color: #dc2626; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>' . t('app_name') . ' - ' . t('time_logs_report') . '</h1>
        
        <div class="header-info">
            <table>
                <tr>
                    <td><strong>' . t('date_range') . ':</strong> ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)) . '</td>
                    <td><strong>' . t('generated_on') . ':</strong> ' . date('M d, Y H:i') . '</td>
                </tr>
                <tr>
                    <td><strong>' . t('total_records') . ':</strong> ' . count($timelogs) . '</td>
                    <td><strong>' . t('total_hours') . ':</strong> ' . number_format($total_hours, 2) . '</td>
                </tr>
            </table>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>' . t('intern') . '</th>
                    <th>' . t('date') . '</th>
                    <th>' . t('clock_in') . '</th>
                    <th>' . t('clock_out') . '</th>
                    <th>' . t('break_start') . '</th>
                    <th>' . t('break_end') . '</th>
                    <th>' . t('break_duration') . '</th>
                    <th>' . t('total_hours') . '</th>
                    <th>' . t('status') . '</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($timelogs as $log) {
        $status_class = 'status-' . $log['status'];
        $html .= '<tr>
            <td>' . htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) . '</td>
            <td>' . date('M d, Y', strtotime($log['date'])) . '</td>
            <td>' . ($log['clock_in'] ? formatTime($log['clock_in']) : '-') . '</td>
            <td>' . ($log['clock_out'] ? formatTime($log['clock_out']) : '-') . '</td>
            <td>' . ($log['break_start'] ? formatTime($log['break_start']) : '-') . '</td>
            <td>' . ($log['break_end'] ? formatTime($log['break_end']) : '-') . '</td>
            <td>' . ($log['total_break_minutes'] ?? 0) . ' min</td>
            <td><strong>' . number_format($log['total_hours'], 2) . '</strong></td>
            <td class="' . $status_class . '">' . ucfirst($log['status']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="summary">
            <table>
                <tr>
                    <td><strong>' . t('summary') . ':</strong></td>
                    <td></td>
                </tr>
                <tr>
                    <td>' . t('total_records') . ':</td>
                    <td>' . count($timelogs) . '</td>
                </tr>
                <tr>
                    <td>' . t('total_hours') . ':</td>
                    <td>' . number_format($total_hours, 2) . ' hrs</td>
                </tr>
                <tr>
                    <td>' . t('total_days') . ':</td>
                    <td>' . $total_days . '</td>
                </tr>
                <tr>
                    <td>' . t('interns_logged') . ':</td>
                    <td>' . $total_interns . '</td>
                </tr>
                <tr>
                    <td>' . t('avg_hours_per_day') . ':</td>
                    <td>' . number_format($avg_hours, 2) . ' hrs</td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <p>' . t('generated_by') . ' ' . t('app_name') . ' | ' . date('Y') . ' &copy; ' . t('all_rights_reserved') . '</p>
        </div>
    </body>
    </html>';
    
    // Convert HTML to PDF using dompdf
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('timelogs_' . date('Y-m-d') . '.pdf', array('Attachment' => true));
}

/**
 * Export timelogs to real Excel .xls file
 */
function exportTimelogsExcel($timelogs, $date_from, $date_to, $total_hours, $total_days, $total_interns, $avg_hours) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        t('intern'),
        t('date'),
        t('clock_in'),
        t('clock_out'),
        t('break_start'),
        t('break_end'),
        t('break_duration'),
        t('total_hours'),
        t('status')
    ];
    $sheet->fromArray($headers, null, 'A1');

    $row = 2;
    foreach ($timelogs as $log) {
        $sheet->fromArray([
            $log['first_name'] . ' ' . $log['last_name'],
            date('Y-m-d', strtotime($log['date'])),
            $log['clock_in'] ? date('H:i', strtotime($log['clock_in'])) : '-',
            $log['clock_out'] ? date('H:i', strtotime($log['clock_out'])) : '-',
            $log['break_start'] ? date('H:i', strtotime($log['break_start'])) : '-',
            $log['break_end'] ? date('H:i', strtotime($log['break_end'])) : '-',
            ($log['total_break_minutes'] ?? 0) . ' min',
            number_format($log['total_hours'], 2),
            ucfirst($log['status'])
        ], null, 'A' . $row);
        $row++;
    }

    $summaryStart = $row + 2;
    $sheet->setCellValue('A' . $summaryStart, t('summary'));
    $sheet->setCellValue('A' . ($summaryStart + 1), t('total_records'));
    $sheet->setCellValue('B' . ($summaryStart + 1), count($timelogs));
    $sheet->setCellValue('A' . ($summaryStart + 2), t('total_hours'));
    $sheet->setCellValue('B' . ($summaryStart + 2), number_format($total_hours, 2) . ' hrs');
    $sheet->setCellValue('A' . ($summaryStart + 3), t('total_days'));
    $sheet->setCellValue('B' . ($summaryStart + 3), $total_days);
    $sheet->setCellValue('A' . ($summaryStart + 4), t('interns_logged'));
    $sheet->setCellValue('B' . ($summaryStart + 4), $total_interns);
    $sheet->setCellValue('A' . ($summaryStart + 5), t('avg_hours_per_day'));
    $sheet->setCellValue('B' . ($summaryStart + 5), number_format($avg_hours, 2) . ' hrs');
    $sheet->setCellValue('A' . ($summaryStart + 6), t('date_range'));
    $sheet->setCellValue('B' . ($summaryStart + 6), date('Y-m-d', strtotime($date_from)) . ' - ' . date('Y-m-d', strtotime($date_to)));
    $sheet->setCellValue('A' . ($summaryStart + 7), t('generated_on'));
    $sheet->setCellValue('B' . ($summaryStart + 7), date('Y-m-d H:i:s'));

    foreach (range('A', 'I') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="timelogs_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    $writer = new Xls($spreadsheet);
    $writer->save('php://output');
}

/**
 * Export timelogs to CSV
 */
function exportTimelogsCsv($timelogs, $date_from, $date_to, $total_hours, $total_days, $total_interns, $avg_hours) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="timelogs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        t('intern'),
        t('date'),
        t('clock_in'),
        t('clock_out'),
        t('break_start'),
        t('break_end'),
        t('break_duration'),
        t('total_hours'),
        t('status')
    ], ';', '"', '\\');

    foreach ($timelogs as $log) {
        fputcsv($output, [
            $log['first_name'] . ' ' . $log['last_name'],
            date('Y-m-d', strtotime($log['date'])),
            $log['clock_in'] ? date('H:i', strtotime($log['clock_in'])) : '-',
            $log['clock_out'] ? date('H:i', strtotime($log['clock_out'])) : '-',
            $log['break_start'] ? date('H:i', strtotime($log['break_start'])) : '-',
            $log['break_end'] ? date('H:i', strtotime($log['break_end'])) : '-',
            ($log['total_break_minutes'] ?? 0) . ' min',
            number_format($log['total_hours'], 2),
            ucfirst($log['status'])
        ], ';', '"', '\\');
    }

    fputcsv($output, [], ';', '"', '\\');
    fputcsv($output, [t('summary')], ';', '"', '\\');
    fputcsv($output, [t('total_records'), count($timelogs)], ';', '"', '\\');
    fputcsv($output, [t('total_hours'), number_format($total_hours, 2) . ' hrs'], ';', '"', '\\');
    fputcsv($output, [t('total_days'), $total_days], ';', '"', '\\');
    fputcsv($output, [t('interns_logged'), $total_interns], ';', '"', '\\');
    fputcsv($output, [t('avg_hours_per_day'), number_format($avg_hours, 2) . ' hrs'], ';', '"', '\\');
    fputcsv($output, [t('date_range'), date('Y-m-d', strtotime($date_from)) . ' - ' . date('Y-m-d', strtotime($date_to))], ';', '"', '\\');
    fputcsv($output, [t('generated_on'), date('Y-m-d H:i:s')], ';', '"', '\\');

    fclose($output);
}

/**
 * Export report to PDF
 */
function exportReportPDF($report_data, $start_date, $end_date, $total_hours, $avg_hours) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.pdf"');
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Internship Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
            h1 { color: #D32F2F; text-align: center; border-bottom: 2px solid #D32F2F; padding-bottom: 10px; }
            .header-info { margin-bottom: 20px; }
            .header-info table { width: 100%; }
            .header-info td { padding: 5px; }
            table { width: 100%; border-collapse: collapse; font-size: 10px; }
            th { background: #D32F2F; color: white; padding: 6px 8px; text-align: left; }
            td { padding: 5px 8px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f9f9f9; }
            .summary { margin-top: 20px; background: #f5f5f5; padding: 15px; border-radius: 5px; }
            .summary table { width: auto; margin: 0 auto; }
            .summary td { padding: 5px 15px; border: none; }
            .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #999; border-top: 1px solid #ddd; padding-top: 15px; }
        </style>
    </head>
    <body>
        <h1>' . t('app_name') . ' - ' . t('internship_report') . '</h1>
        
        <div class="header-info">
            <table>
                <tr>
                    <td><strong>' . t('date_range') . ':</strong> ' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)) . '</td>
                    <td><strong>' . t('generated_on') . ':</strong> ' . date('M d, Y H:i') . '</td>
                </tr>
            </table>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>' . t('intern') . '</th>
                    <th>' . t('email') . '</th>
                    <th>' . t('school') . '</th>
                    <th>' . t('field_of_study') . '</th>
                    <th>' . t('supervisor') . '</th>
                    <th>' . t('start_date') . '</th>
                    <th>' . t('end_date') . '</th>
                    <th>' . t('total_hours') . '</th>
                    <th>' . t('days_worked') . '</th>
                    <th>' . t('avg_hours_per_day') . '</th>
                    <th>' . t('completed') . '</th>
                    <th>' . t('missed') . '</th>
                    <th>' . t('active') . '</th>
                </tr>
            </thead>
            <tbody>';
    
    $counter = 1;
    foreach ($report_data as $data) {
        $html .= '<tr>
            <td>' . $counter . '</td>
            <td><strong>' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . '</strong></td>
            <td>' . htmlspecialchars($data['email'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($data['school'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($data['field_of_study'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars(($data['supervisor_first_name'] ?? '') . ' ' . ($data['supervisor_last_name'] ?? '')) . '</td>
            <td>' . (isset($data['intern_start_date']) ? date('M d, Y', strtotime($data['intern_start_date'])) : 'N/A') . '</td>
            <td>' . (isset($data['intern_end_date']) ? date('M d, Y', strtotime($data['intern_end_date'])) : 'N/A') . '</td>
            <td><strong>' . number_format($data['total_hours'], 2) . '</strong></td>
            <td>' . ($data['days_worked'] ?? 0) . '</td>
            <td>' . number_format($data['avg_hours'] ?? 0, 1) . '</td>
            <td>' . ($data['completed_days'] ?? 0) . '</td>
            <td>' . ($data['missed_days'] ?? 0) . '</td>
            <td>' . ($data['active_days'] ?? 0) . '</td>
        </tr>';
        $counter++;
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="summary">
            <table>
                <tr><td colspan="2"><strong>' . t('summary') . ':</strong></td></tr>
                <tr><td>' . t('total_interns') . ':</td><td>' . count($report_data) . '</td></tr>
                <tr><td>' . t('total_hours') . ':</td><td>' . number_format($total_hours, 2) . ' hrs</td></tr>
                <tr><td>' . t('avg_hours_per_intern') . ':</td><td>' . number_format($avg_hours, 1) . ' hrs</td></tr>
                <tr><td>' . t('date_range') . ':</td><td>' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)) . '</td></tr>
            </table>
        </div>
        
        <div class="footer">
            <p>' . t('generated_by') . ' ' . t('app_name') . ' | ' . date('Y') . ' &copy; ' . t('all_rights_reserved') . '</p>
        </div>
    </body>
    </html>';
    
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('report_' . date('Y-m-d') . '.pdf', array('Attachment' => true));
}

/**
 * Export report to CSV
 */
function exportReportCsv($report_data, $start_date, $end_date, $total_hours, $avg_hours) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'ID',
        t('intern'),
        t('email'),
        t('school'),
        t('field_of_study'),
        t('supervisor'),
        t('start_date'),
        t('end_date'),
        t('total_hours'),
        t('days_worked'),
        t('avg_hours_per_day'),
        t('completed'),
        t('missed'),
        t('active')
    ], ';', '"', '\\');

    $counter = 1;
    foreach ($report_data as $data) {
        fputcsv($output, [
            $counter,
            $data['first_name'] . ' ' . $data['last_name'],
            $data['email'] ?? 'N/A',
            $data['school'] ?? 'N/A',
            $data['field_of_study'] ?? 'N/A',
            ($data['supervisor_first_name'] ?? '') . ' ' . ($data['supervisor_last_name'] ?? ''),
            isset($data['intern_start_date']) ? date('Y-m-d', strtotime($data['intern_start_date'])) : 'N/A',
            isset($data['intern_end_date']) ? date('Y-m-d', strtotime($data['intern_end_date'])) : 'N/A',
            number_format($data['total_hours'], 2),
            $data['days_worked'] ?? 0,
            number_format($data['avg_hours'] ?? 0, 1),
            $data['completed_days'] ?? 0,
            $data['missed_days'] ?? 0,
            $data['active_days'] ?? 0
        ], ';', '"', '\\');
        $counter++;
    }

    fputcsv($output, [], ';', '"', '\\');
    fputcsv($output, [t('summary')], ';', '"', '\\');
    fputcsv($output, [t('total_interns'), count($report_data)], ';', '"', '\\');
    fputcsv($output, [t('total_hours'), number_format($total_hours, 2) . ' hrs'], ';', '"', '\\');
    fputcsv($output, [t('avg_hours_per_intern'), number_format($avg_hours, 1) . ' hrs'], ';', '"', '\\');
    fputcsv($output, [t('date_range'), date('Y-m-d', strtotime($start_date)) . ' - ' . date('Y-m-d', strtotime($end_date))], ';', '"', '\\');
    fputcsv($output, [t('generated_on'), date('Y-m-d H:i:s')], ';', '"', '\\');

    fclose($output);
}

/**
 * Export report to real Excel .xls file
 */
function exportReportExcel($report_data, $start_date, $end_date, $total_hours, $avg_hours) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        '#',
        t('intern'),
        t('email'),
        t('school'),
        t('field_of_study'),
        t('supervisor'),
        t('start_date'),
        t('end_date'),
        t('total_hours'),
        t('days_worked'),
        t('avg_hours_per_day'),
        t('completed'),
        t('missed'),
        t('active')
    ];
    $sheet->fromArray($headers, null, 'A1');

    $row = 2;
    $counter = 1;
    foreach ($report_data as $data) {
        $sheet->fromArray([
            $counter,
            $data['first_name'] . ' ' . $data['last_name'],
            $data['email'] ?? 'N/A',
            $data['school'] ?? 'N/A',
            $data['field_of_study'] ?? 'N/A',
            ($data['supervisor_first_name'] ?? '') . ' ' . ($data['supervisor_last_name'] ?? ''),
            isset($data['intern_start_date']) ? date('Y-m-d', strtotime($data['intern_start_date'])) : 'N/A',
            isset($data['intern_end_date']) ? date('Y-m-d', strtotime($data['intern_end_date'])) : 'N/A',
            number_format($data['total_hours'], 2),
            $data['days_worked'] ?? 0,
            number_format($data['avg_hours'] ?? 0, 1),
            $data['completed_days'] ?? 0,
            $data['missed_days'] ?? 0,
            $data['active_days'] ?? 0
        ], null, 'A' . $row);
        $counter++;
        $row++;
    }

    $summaryStart = $row + 2;
    $sheet->setCellValue('A' . $summaryStart, t('summary'));
    $sheet->setCellValue('A' . ($summaryStart + 1), t('total_interns'));
    $sheet->setCellValue('B' . ($summaryStart + 1), count($report_data));
    $sheet->setCellValue('A' . ($summaryStart + 2), t('total_hours'));
    $sheet->setCellValue('B' . ($summaryStart + 2), number_format($total_hours, 2) . ' hrs');
    $sheet->setCellValue('A' . ($summaryStart + 3), t('avg_hours_per_intern'));
    $sheet->setCellValue('B' . ($summaryStart + 3), number_format($avg_hours, 1) . ' hrs');
    $sheet->setCellValue('A' . ($summaryStart + 4), t('date_range'));
    $sheet->setCellValue('B' . ($summaryStart + 4), date('Y-m-d', strtotime($start_date)) . ' - ' . date('Y-m-d', strtotime($end_date)));
    $sheet->setCellValue('A' . ($summaryStart + 5), t('generated_on'));
    $sheet->setCellValue('B' . ($summaryStart + 5), date('Y-m-d H:i:s'));

    foreach (range('A', 'N') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    $writer = new Xls($spreadsheet);
    $writer->save('php://output');
}
?>