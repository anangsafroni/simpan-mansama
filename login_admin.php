<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Mengecek password, mendukung teks biasa maupun hash
        if ($password === $row['password'] || password_verify($password, $row['password'])) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $row['username'];
            header("Location: admin.php");
            exit;
        } else {
            $error = "Password yang Anda masukkan salah!";
        }
    } else {
        $error = "Username admin tidak ditemukan!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - E-Dispensasi</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-card h2 { margin-top: 0; text-align: center; color: #0f172a; font-size: 20px; margin-bottom: 5px; }
        .login-card p { text-align: center; color: #64748b; font-size: 13px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn-login { width: 100%; background: #2563eb; color: #fff; padding: 10px; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; margin-top: 10px; }
        .btn-login:hover { background: #1d4ed8; }
        .alert { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: center; font-weight: 500; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Login Admin</h2>
    <p>MAN 1 Kota Madiun</p>
    
    <?php if (!empty($error)): ?>
        <div class="alert"><?= $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required autofocus placeholder="Masukkan username">
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required placeholder="Masukkan password">
        </div>

        <button type="submit" class="btn-login">Masuk</button>
    </form>
</div>

</body>
</html>