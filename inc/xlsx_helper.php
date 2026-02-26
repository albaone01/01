<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function xlsx_col_name(int $index): string {
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function xlsx_escape(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function xlsx_build_content(array $headers, array $rows, string $sheetName = 'Sheet1'): array {
    $sheetRows = [];
    $r = 1;
    $rowXml = '<row r="' . $r . '">';
    foreach ($headers as $c => $h) {
        $cell = xlsx_col_name($c + 1) . $r;
        $rowXml .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . xlsx_escape((string)$h) . '</t></is></c>';
    }
    $rowXml .= '</row>';
    $sheetRows[] = $rowXml;
    $r++;

    foreach ($rows as $line) {
        $rowXml = '<row r="' . $r . '">';
        foreach ($line as $c => $v) {
            $cell = xlsx_col_name($c + 1) . $r;
            if (is_numeric($v)) {
                $rowXml .= '<c r="' . $cell . '"><v>' . (0 + $v) . '</v></c>';
            } else {
                $rowXml .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . xlsx_escape((string)$v) . '</t></is></c>';
            }
        }
        $rowXml .= '</row>';
        $sheetRows[] = $rowXml;
        $r++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>';

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . xlsx_escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];
    return $files;
}

function xlsx_output(array $headers, array $rows, string $filename, string $sheetName = 'Sheet1'): void {
    if (!class_exists('ZipArchive')) {
        throw new Exception('Ekstensi ZipArchive tidak tersedia');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($tmp === false) throw new Exception('Gagal membuat file sementara');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Gagal membuat XLSX');
    }
    foreach (xlsx_build_content($headers, $rows, $sheetName) as $path => $xml) {
        $zip->addFromString($path, $xml);
    }
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
}
