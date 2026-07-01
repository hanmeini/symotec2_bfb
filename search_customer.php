<?php



  
    



require_once 'config1.php';



if (isset($_GET['kode'])) {
    $kode = $_GET['kode'];
    $stmt = $conn->prepare("SELECT kode, nama, alamat, npwp, cabang FROM cust WHERE kode LIKE ? OR nama LIKE ? LIMIT 5");
    $searchTerm = "%$kode%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $custList = [];
    while ($row = $result->fetch_assoc()) {
        $custList[] = [
            'kode' => $row['kode'],
            'nama' => $row['nama'],
            'alamat' => $row['alamat'],
            'npwp' => $row['npwp'],
            'cabang' => $row['cabang']
        ];
    }

    echo json_encode($custList);
    $stmt->close();
}

$conn->close();
?>
