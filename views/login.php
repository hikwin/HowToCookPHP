<?php declare(strict_types=1);
// views/login.php
ob_start();
$error   = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = attempt_login($email, $password);
    if ($user) {
        $return = $_GET['return'] ?? base_url();
        // Validate return URL (must be relative path, not external)
        if (!str_starts_with($return, '/') || str_contains($return, '//')) {
            $return = base_url();
        }
        header('Location: ' . $return);
        exit;
    }
    $error = '邮箱或密码错误，请重试。';
}
?>
<div class="container" style="max-width:440px;margin-top:4rem;">
    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div style="font-size:3rem;">🍳</div>
                <h2 class="fw-bold mt-2">欢迎回来</h2>
                <p class="text-muted">登录你的账号</p>
            </div>
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">邮箱</label>
                    <input type="email" name="email" class="form-control form-control-lg"
                           value="<?= e($email) ?>" required maxlength="254"
                           placeholder="your@email.com" autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">密码</label>
                    <input type="password" name="password" class="form-control form-control-lg"
                           required maxlength="256" placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">登录</button>
            </form>
            <div class="text-center mt-4 text-muted small">
                还没有账号？<a href="<?= e(base_url('register')) ?>">立即注册</a>
            </div>
        </div>
    </div>
</div>
<?php
$content    = ob_get_clean();
$page_title = '登录';
$no_sidebar = true;
require __DIR__ . '/layout.php';
