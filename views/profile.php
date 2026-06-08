<?php declare(strict_types=1);
// views/profile.php
require_login();
$user = current_user();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'name') {
        $name = trim($_POST['display_name'] ?? '');
        $err  = update_display_name((int)$user['id'], $name);
        if ($err) {
            $errors[] = $err;
        } else {
            flash('success', '昵称已更新');
            header('Location: ' . base_url('profile'));
            exit;
        }
    } elseif ($action === 'email') {
        $new_email = trim($_POST['new_email'] ?? '');
        $cur_pass  = $_POST['current_password'] ?? '';

        if (!password_verify($cur_pass, $user['password_hash'])) {
            $errors[] = '当前密码不正确，无法修改邮箱';
        } else {
            $err = update_email((int)$user['id'], $new_email);
            if ($err) {
                $errors[] = $err;
            } else {
                flash('success', '邮箱已更新');
                header('Location: ' . base_url('profile'));
                exit;
            }
        }
    } elseif ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cfm = $_POST['confirm_password'] ?? '';

        if (!password_verify($old, $user['password_hash'])) {
            $errors[] = '当前密码不正确';
        } elseif (strlen($new) < 8) {
            $errors[] = '新密码至少 8 位';
        } elseif (strlen($new) > 256) {
            $errors[] = '新密码过长';
        } elseif ($new !== $cfm) {
            $errors[] = '两次密码不一致';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            // Only update password_hash — is_admin is NOT touched
            db_exec("UPDATE users SET password_hash=? WHERE id=?", [$hash, $user['id']]);
            flash('success', '密码已更新，请重新登录');
            logout();
            header('Location: ' . base_url('login'));
            exit;
        }
    }
}

// Reload user after possible update
$user = current_user();

ob_start();
?>
<div class="container" style="max-width:560px; margin-top:2rem;">
    <h2 class="fw-bold mb-4">⚙️ 个人设置</h2>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= e($e) ?></div>
    <?php endforeach; ?>

    <!-- Profile info: nickname -->
    <div class="card mb-4 shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">基本信息</h5>
            <div class="mb-3">
                <label class="form-label text-muted small">当前邮箱</label>
                <div class="fw-semibold"><?= e($user['email']) ?></div>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="name">
                <div class="mb-3">
                    <label class="form-label">昵称</label>
                    <input type="text" name="display_name" class="form-control"
                           value="<?= e($user['display_name']) ?>" required minlength="2" maxlength="30">
                </div>
                <button type="submit" class="btn btn-primary">保存昵称</button>
            </form>
        </div>
    </div>

    <!-- Change email -->
    <div class="card mb-4 shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">修改邮箱</h5>
            <p class="text-muted small">修改邮箱不会影响您的权限（管理员仍为管理员）。需要验证当前密码。</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="email">
                <div class="mb-3">
                    <label class="form-label">新邮箱</label>
                    <input type="email" name="new_email" class="form-control"
                           required maxlength="254" placeholder="new@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">当前密码（验证身份）</label>
                    <input type="password" name="current_password" class="form-control"
                           required maxlength="256" placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-outline-primary">修改邮箱</button>
            </form>
        </div>
    </div>

    <!-- Change password -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">修改密码</h5>
            <p class="text-muted small">修改密码不会影响您的权限（管理员仍为管理员）。修改后需重新登录。</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div class="mb-3">
                    <label class="form-label">当前密码</label>
                    <input type="password" name="old_password" class="form-control" required maxlength="256">
                </div>
                <div class="mb-3">
                    <label class="form-label">新密码（至少 8 位）</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8" maxlength="256">
                </div>
                <div class="mb-3">
                    <label class="form-label">确认新密码</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8" maxlength="256">
                </div>
                <button type="submit" class="btn btn-warning">修改密码</button>
            </form>
        </div>
    </div>
</div>
<?php
$content    = ob_get_clean();
$page_title = '个人设置';
$no_sidebar = true;
require __DIR__ . '/layout.php';
