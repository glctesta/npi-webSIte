<?php

require_once __DIR__ . '/fpdf.php';

function utf8_to_latin1($text) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
}

function generate_proforma_pdf($data): string {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);
    
    // Colors
    $primaryColor = [79, 70, 229]; // #4f46e5
    $darkGray = [51, 51, 51];
    $lightGray = [240, 240, 240];
    
    // 1. Header (logo skipped - FPDF has limited PNG support)
    // To add logo: convert to JPEG first or use a compatible PNG
    
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(0, 10, utf8_to_latin1('PROFORMA INVOICE'), 0, 1, 'R');
    
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
    $pdf->Cell(0, 8, utf8_to_latin1('No: ' . $data['proforma_number']), 0, 1, 'R');
    $pdf->Cell(0, 8, utf8_to_latin1('Date: ' . $data['date']), 0, 1, 'R');
    
    $pdf->Ln(15);
    
    // 2. Company Info Section (Two columns)
    $y = $pdf->GetY();
    
    // Left: GTMC Info
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(95, 8, utf8_to_latin1('FROM:'), 0, 1, 'L');
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(95, 6, utf8_to_latin1('GTMC CONSULTING SRL'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 5, utf8_to_latin1('CUI: 30767243'), 0, 1, 'L');
    $pdf->Cell(95, 5, utf8_to_latin1('Nr. Reg. Com.: J02/771/2015'), 0, 1, 'L');
    $pdf->Cell(95, 5, utf8_to_latin1('Bdul. G-Ral Vasile Milea 3, Et. 3, Ap. 8A'), 0, 1, 'L');
    $pdf->Cell(95, 5, utf8_to_latin1('Arad, Cod 310131, Romania'), 0, 1, 'L');
    
    $leftY = $pdf->GetY();
    
    // Right: Customer Info
    $pdf->SetY($y);
    $pdf->SetX(105);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(95, 8, utf8_to_latin1('TO:'), 0, 1, 'L');
    
    $pdf->SetX(105);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(95, 6, utf8_to_latin1($data['contact_name']), 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    if (!empty($data['company_name'])) {
        $pdf->SetX(105);
        $pdf->Cell(95, 5, utf8_to_latin1($data['company_name']), 0, 1, 'L');
    }
    if (!empty($data['vat_id'])) {
        $pdf->SetX(105);
        $pdf->Cell(95, 5, utf8_to_latin1('VAT ID: ' . $data['vat_id']), 0, 1, 'L');
    }
    if (!empty($data['billing_address'])) {
        $pdf->SetX(105);
        $pdf->MultiCell(95, 5, utf8_to_latin1($data['billing_address']), 0, 'L');
    }
    $pdf->SetX(105);
    $pdf->Cell(95, 5, utf8_to_latin1($data['contact_email']), 0, 1, 'L');
    
    $rightY = $pdf->GetY();
    $pdf->SetY(max($leftY, $rightY) + 15);
    
    // 4. Line items table
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(120, 10, utf8_to_latin1(' Description'), 1, 0, 'L', true);
    $pdf->Cell(30, 10, utf8_to_latin1(' Period'), 1, 0, 'C', true);
    $pdf->Cell(40, 10, utf8_to_latin1(' Amount'), 1, 1, 'R', true);
    
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
    $pdf->SetFont('Arial', '', 11);
    
    $desc = "GTMC-NPI - {$data['plan_label']} License";
    $period = "1 Year";
    $amountStr = number_format($data['amount'], 2) . ' ' . $data['currency'];
    
    $pdf->Cell(120, 10, utf8_to_latin1(' ' . $desc), 1, 0, 'L');
    $pdf->Cell(30, 10, utf8_to_latin1($period), 1, 0, 'C');
    $pdf->Cell(40, 10, utf8_to_latin1($amountStr . ' '), 1, 1, 'R');
    
    // 5. Total row
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(150, 10, utf8_to_latin1('TOTAL'), 1, 0, 'R');
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(40, 10, utf8_to_latin1($amountStr . ' '), 1, 1, 'R');
    
    $pdf->Ln(15);
    
    // 6. Bank transfer box
    $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->Cell(0, 8, utf8_to_latin1(' PAYMENT DETAILS'), 'LTR', 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 7, utf8_to_latin1(' Beneficiary:'), 'L', 0, 'L', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, utf8_to_latin1('GTMC CONSULTING SRL'), 'R', 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 7, utf8_to_latin1(' IBAN:'), 'L', 0, 'L', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, utf8_to_latin1('RO88INGB0000999903390932'), 'R', 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 7, utf8_to_latin1(' Bank:'), 'L', 0, 'L', true);
    $pdf->Cell(0, 7, utf8_to_latin1('ING Bank, Timisoara'), 'R', 1, 'L', true);
    
    $pdf->Cell(40, 7, utf8_to_latin1(' Reference:'), 'LB', 0, 'L', true);
    $pdf->Cell(0, 7, utf8_to_latin1($data['proforma_number']), 'RB', 1, 'L', true);
    
    $pdf->Ln(20);
    
    // 7. Footer note
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 10, utf8_to_latin1('This is a proforma invoice and does not represent a fiscal document.'), 0, 1, 'C');
    
    return $pdf->Output('S');
}
