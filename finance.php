<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/Transaction.php';

// --- Inisialisasi state sesi ---
if (!isset($_SESSION['balance'])) {
    $_SESSION['balance'] = 0.0;
}
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Validasi token CSRF ---
    $tokenValid = isset($_POST['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

    if (!$tokenValid) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $type      = $_POST['type'] ?? '';
        $amountRaw = trim((string)($_POST['amount'] ?? ''));

        // --- Validasi jumlah: angka desimal positif ---
        if ($amountRaw === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw) || (float)$amountRaw <= 0) {
            $errors[] = 'Jumlah transaksi harus berupa angka desimal positif (contoh: 50000 atau 50000.50).';
        }

        // --- Validasi jenis transaksi ---
        if (!in_array($type, ['deposit', 'withdraw'], true)) {
            $errors[] = 'Jenis transaksi tidak valid.';
        }

        if (empty($errors)) {
            $amount = (float)$amountRaw;
            $nextId = count($_SESSION['history']) + 1;

            $transaction = new Transaction($nextId, $type, $amount);

            try {
                $newBalance = $transaction->process((float)$_SESSION['balance']);
                $_SESSION['balance']  = $newBalance;
                $_SESSION['history'][] = $transaction->toArray();
                $success = 'Transaksi berhasil diproses.';
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Regenerasi token setelah setiap submit (mencegah replay)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

$csrfToken = $_SESSION['csrf_token'];
$balance   = (float)$_SESSION['balance'];
$history   = $_SESSION['history'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sistem Manajemen Keuangan Sederhana</title>
<style>
  body {
    font-family: Arial, Helvetica, sans-serif;
    max-width: 560px;
    margin: 40px auto;
    padding: 0 16px;
    color: #222;
    background: #fafafa;
  }
  h1 { font-size: 1.4rem; margin-bottom: 4px; }
  .balance {
    font-size: 1.3rem;
    font-weight: bold;
    margin: 16px 0 24px;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
  }
  form {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 24px;
  }
  label { display: block; margin-top: 10px; font-weight: 600; }
  input, select {
    padding: 8px;
    width: 100%;
    box-sizing: border-box;
    margin-top: 4px;
    border: 1px solid #ccc;
    border-radius: 4px;
  }
  button {
    margin-top: 14px;
    padding: 8px 18px;
    cursor: pointer;
    background: #1a73e8;
    color: #fff;
    border: none;
    border-radius: 4px;
  }
  button:hover { background: #1558b0; }
  .error {
    color: #b00020;
    background: #fde8e8;
    border: 1px solid #f5b5b5;
    padding: 8px 10px;
    border-radius: 4px;
    margin: 6px 0;
  }
  .success {
    color: #087f23;
    background: #e6f4ea;
    border: 1px solid #b7dfc2;
    padding: 8px 10px;
    border-radius: 4px;
    margin: 6px 0;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    background: #fff;
  }
  th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
  th { background: #f2f2f2; }
</style>
</head>
<body>

<h1>Sistem Manajemen Keuangan Sederhana</h1>

<div class="balance">
    Saldo saat ini: Rp <?= htmlspecialchars(number_format($balance, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<?php if ($success !== null): ?>
    <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <label for="type">Jenis Transaksi</label>
    <select name="type" id="type" required>
        <option value="deposit">Deposit</option>
        <option value="withdraw">Penarikan</option>
    </select>

    <label for="amount">Jumlah</label>
    <input type="text" name="amount" id="amount" placeholder="contoh: 100000.00" required>

    <button type="submit">Proses Transaksi</button>
</form>

<h2>Riwayat Transaksi</h2>

<?php if (empty($history)): ?>
    <p>Belum ada transaksi.</p>
<?php else: ?>
<table>
    <tr>
        <th>ID</th>
        <th>Jenis</th>
        <th>Jumlah</th>
    </tr>
    <?php foreach (array_reverse($history) as $trx): ?>
    <tr>
        <td><?= htmlspecialchars((string)$trx['id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($trx['type'] === 'deposit' ? 'Deposit' : 'Penarikan', ENT_QUOTES, 'UTF-8') ?></td>
        <td>Rp <?= htmlspecialchars(number_format((float)$trx['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

</body>
</html>
