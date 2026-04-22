<?php
/**
 * Kimlik Doğrulama ve Yetkilendirme
 */

if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

function auth_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Oturum süresi kontrolü
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function auth_login(string $username, string $password): array {
    $st = db()->prepare('SELECT * FROM ' . tbl('users') . ' WHERE (username = ? OR email = ?) LIMIT 1');
    $st->execute([$username, $username]);
    $u = $st->fetch();

    if (!$u || !password_verify($password, $u['password_hash'])) {
        return ['ok' => false, 'msg' => 'Kullanıcı adı veya şifre hatalı.'];
    }
    if (!$u['aktif']) {
        return ['ok' => false, 'msg' => 'Hesabınız aktif değil. Yöneticinize başvurun.'];
    }

    // Rehash gerekiyorsa güncelle
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('UPDATE ' . tbl('users') . ' SET password_hash = ? WHERE id = ?')
            ->execute([$newHash, $u['id']]);
    }

    // Session regenerate
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int)$u['id'],
        'username' => $u['username'],
        'ad_soyad' => $u['ad_soyad'],
        'email'    => $u['email'],
        'rol'      => $u['rol'],
    ];

    db()->prepare('UPDATE ' . tbl('users') . ' SET son_giris = NOW(), son_giris_ip = ? WHERE id = ?')
        ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $u['id']]);

    log_yaz('giris', 'auth', (int)$u['id'], 'Başarılı giriş');
    return ['ok' => true];
}

function auth_logout(): void {
    if (!empty($_SESSION['user'])) {
        log_yaz('cikis', 'auth', (int)$_SESSION['user']['id'], 'Çıkış yapıldı');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user']['id']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool {
    return (current_user()['rol'] ?? '') === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('index.php?r=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Bu işlem için yetkiniz yok.');
    }
}
