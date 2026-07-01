<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$dompdf = new Dompdf($options);

// Render HTML content
$html = '<h1>Customer Belum Bayar</h1>';
$html .= '<table>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Invoice</th>
                <th>Kode Booking</th>
                <th>Customer</th>
                <th>Tagihan</th>
                <th>Bayar</th>
                <th>Sisa</th>
                <th>Loc Dev</th>
                <th>Umur (Hari)</th>
            </tr>';

while ($row = $result_pph23->fetch_assoc()) {
    $customer_name = 'Tidak Ditemukan';
    $sql_customer = "SELECT customer FROM customer WHERE id = " . intval($row['cust_id']);
    $result_customer = $conn->query($sql_customer);
    if ($result_customer && $result_customer->num_rows > 0) {
        $customer_name = $result_customer->fetch_assoc()['customer'];
    }

    $html .= "<tr>
                <td>" . htmlspecialchars($row['id']) . "</td>
                <td>" . htmlspecialchars($row['tanggal']) . "</td>
                <td>" . htmlspecialchars($row['inv']) . "</td>
                <td>" . htmlspecialchars($row['kodebooking']) . "</td>
                <td>" . htmlspecialchars($customer_name) . "</td>
                <td>" . number_format($row['tagihan'], 2) . "</td>
                <td>" . number_format($row['bayar'], 2) . "</td>
                <td>" . number_format($row['sisa'], 2) . "</td>
                <td>" . htmlspecialchars($row['location'] . " - " . $row['devisi']) . "</td>
                <td>" . intval($row['umur']) . " Hari</td>
              </tr>";
}

$html .= '</table>';

$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream("customer_belum_bayar.pdf", array("Attachment" => 0));
?>
