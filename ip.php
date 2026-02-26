<?php
$ip = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputIP = $_POST['ip_address'];

    // Validasi format IPv4
    if (filter_var($inputIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ip = $inputIP;
        $message = "IP yang kamu masukkan valid: $ip";
    } else {
        $message = "IP yang kamu masukkan tidak valid!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cek IP IPv4</title>
</head>
<body>
    <h2>Masukkan IP IPv4</h2>
    <form method="post" action="">
        <input type="text" name="ip_address" placeholder="contoh: 192.168.1.10" required>
        <input type="submit" value="Cek IP">
    </form>

    <?php
    if (!empty($message)) {
        echo "<p>$message</p>";
    }
    ?>
</body>
</html>
