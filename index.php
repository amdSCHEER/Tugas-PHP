<?php
session_start();

// data diri
const NAMA = 'Eksel';
const NIM  = '255314055';
const FOTO = 'foto_profil.jpeg';
const ADMIN_PASS = '255314055';

// konfigurasi database
const DB_HOST = 'localhost';
const DB_NAME = 'todolist_db';
const DB_USER = 'root';
const DB_PASS = '';

// koneksi database, kalau belum ada dibuat otomatis
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS todos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        teks VARCHAR(255) NOT NULL,
        selesai TINYINT(1) NOT NULL DEFAULT 0,
        dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');

    // bikin user admin kalau belum ada (password di-hash)
    $cek = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $cek->execute(['admin']);
    if ((int) $cek->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)')
            ->execute(['admin', password_hash(ADMIN_PASS, PASSWORD_DEFAULT)]);
    }
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . htmlspecialchars($e->getMessage()));
}

// biar teks aman ditampilkan di html
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// token biar form tidak bisa dikirim dari situs lain
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfValid(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

// proses form (login, tambah, selesai, hapus, logout)
$error = '';
$aksi  = $_POST['aksi'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($aksi === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php');
            exit;
        }
        $error = 'Username atau password salah. Coba lagi.';
    } elseif (isset($_SESSION['user_id']) && csrfValid()) {
        $uid = $_SESSION['user_id'];

        if ($aksi === 'logout') {
            session_destroy();
            header('Location: index.php');
            exit;
        }

        if ($aksi === 'tambah') {
            $teks = trim($_POST['teks'] ?? '');
            if ($teks !== '') {
                $pdo->prepare('INSERT INTO todos (user_id, teks) VALUES (?, ?)')
                    ->execute([$uid, mb_substr($teks, 0, 255)]);
            }
        } elseif ($aksi === 'selesai') {
            // ditekan lagi = balik ke belum selesai
            $pdo->prepare('UPDATE todos SET selesai = 1 - selesai WHERE id = ? AND user_id = ?')
                ->execute([(int) ($_POST['id'] ?? 0), $uid]);
        } elseif ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM todos WHERE id = ? AND user_id = ?')
                ->execute([(int) ($_POST['id'] ?? 0), $uid]);
        }
        // redirect biar tidak kirim ulang kalau di-refresh
        header('Location: index.php');
        exit;
    }
}

$login = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $login ? 'To Do List' : 'Login' ?> - <?= e(NAMA) ?></title>
    <link rel="stylesheet" href="<?= e(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')) ?>/style.css">
</head>
<body>

<!-- header: nama, nim, foto -->
<header class="header">
    <div class="header-isi">
        <img class="foto" src="<?= e(FOTO) ?>" alt="Foto <?= e(NAMA) ?>"
             onerror="this.style.visibility='hidden'">
        <div class="identitas">
            <h1><?= e(NAMA) ?></h1>
            <p>NIM <?= e(NIM) ?></p>
        </div>
    </div>
</header>

<main class="konten">
<?php if (!$login): ?>

    <!-- halaman login -->
    <section class="kartu kartu-login">
        <h2>Login</h2>
        <p class="sub">Masuk untuk membuka to do list Anda.</p>

        <?php if ($error): ?>
            <div class="pesan-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="aksi" value="login">

            <label for="username">User Name</label>
            <input type="text" id="username" name="username" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="tombol tombol-utama">Submit</button>
        </form>
    </section>

<?php else:
    // ambil to do milik user, yang sudah selesai ditaruh paling bawah
    $stmt = $pdo->prepare('SELECT * FROM todos WHERE user_id = ? ORDER BY selesai ASC, id ASC');
    $stmt->execute([$_SESSION['user_id']]);
    $todos = $stmt->fetchAll();
    $token = csrfToken();
?>

    <!-- halaman to do list -->
    <section class="kartu kartu-todo">
        <div class="bar-atas">
            <div>
                <h2>To Do List</h2>
                <p class="sub">Halo, <?= e($_SESSION['username']) ?>.</p>
            </div>
            <form method="post">
                <input type="hidden" name="aksi" value="logout">
                <input type="hidden" name="csrf" value="<?= $token ?>">
                <button type="submit" class="tombol tombol-garis">Keluar</button>
            </form>
        </div>

        <form method="post" class="form-tambah">
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="text" name="teks" maxlength="255" placeholder="Tulis kegiatan baru" required autofocus>
            <button type="submit" class="tombol tombol-utama">Tambah</button>
        </form>

        <?php if (!$todos): ?>
            <p class="kosong">Belum ada kegiatan. Tulis satu di atas, lalu tekan Tambah.</p>
        <?php else: ?>
            <ul class="daftar">
                <?php foreach ($todos as $t): ?>
                    <li class="item<?= $t['selesai'] ? ' selesai' : '' ?>">
                        <span class="teks"><?= e($t['teks']) ?></span>
                        <div class="aksi">
                            <form method="post">
                                <input type="hidden" name="aksi" value="selesai">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= $token ?>">
                                <button type="submit" class="tombol tombol-kecil tombol-selesai">
                                    <?= $t['selesai'] ? 'Batal' : 'Selesai' ?>
                                </button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= $token ?>">
                                <button type="submit" class="tombol tombol-kecil tombol-hapus">Hapus</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

<?php endif; ?>
</main>

</body>
</html>