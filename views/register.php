<?php declare(strict_types=1);
// views/register.php
ob_start();
$errors = [];
$email  = '';
$name   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $name     = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $errors[] = '两次输入的密码不一致';
    } else {
        $result = attempt_register($email, $name, $password);
        if ($result['ok']) {
            flash('success', '注册成功，欢迎！');
            header('Location: ' . base_url());
            exit;
        }
        $errors[] = $result['error'];
    }
}
?>
<div class="container" style="max-width:480px;margin-top:4rem;">
    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div style="font-size:3rem;">🧑‍🍳</div>
                <h2 class="fw-bold mt-2">创建账号</h2>
                <p class="text-muted">加入 HowToCook 社区</p>
            </div>
            <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= e($e) ?></div>
            <?php endforeach; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">邮箱</label>
                    <input type="email" name="email" class="form-control form-control-lg"
                           value="<?= e($email) ?>" required maxlength="254"
                           placeholder="your@email.com">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">昵称</label>
                    <input type="text" name="display_name" class="form-control form-control-lg"
                           value="<?= e($name) ?>" required minlength="2" maxlength="30"
                           placeholder="你的昵称">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">密码</label>
                    <input type="password" name="password" class="form-control form-control-lg"
                           required minlength="8" maxlength="256" placeholder="至少 8 位">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">确认密码</label>
                    <input type="password" name="confirm" class="form-control form-control-lg"
                           required minlength="8" maxlength="256" placeholder="再输一次">
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">注册</button>
            </form>
            <div class="text-center mt-4 text-muted small">
                已有账号？<a href="<?= e(base_url('login')) ?>">立即登录</a>
            </div>
        </div>
    </div>
</div>
<?php
$content    = ob_get_clean();
$page_title = '注册';
$no_sidebar = true;
require __DIR__ . '/layout.php';
