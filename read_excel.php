<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$files = [
    'c:\\Users\\X1 CARBON\\Downloads\\symotec2_bfb\\temp\\daftar-barang.xlsx',
    'c:\\Users\\X1 CARBON\\Downloads\\symotec2_bfb\\temp\\daftar-pemasok.xlsx'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    echo "--- $file ---\n";
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rowIterator = $sheet->getRowIterator(1, 2);
    foreach ($rowIterator as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $rowData = [];
        foreach ($cellIterator as $cell) {
            $val = $cell->getValue();
            if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $val = $val->getPlainText();
            }
            $rowData[] = $val;
        }
        print_r($rowData);
    }
}
?>
