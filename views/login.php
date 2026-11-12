<?php $title = 'Admin login'; require __DIR__ . '/_auth_head.php'; ?>
<main class="auth-shell">
  <section class="auth-card">
    <a class="auth-brand" href="/"><img src="/assets/nexum-chatbot-favicon.png" alt="" width="52" height="52"><span>NEXUM <b>CHATBOT</b></span></a>
    <p class="eyebrow">Secure control room</p>
    <h1>Welcome back.</h1>
    <p class="intro">Sign in to manage enquiries, pricing, website content, and team access.</p>
    <?php if (!empty($created)): ?><p class="notice success">Admin account created. You can sign in now.</p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="/admin/login">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label>Email address<input name="email" type="email" autocomplete="username" required autofocus></label>
      <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
      <button type="submit">Sign in</button>
    </form>
    <a class="back" href="/">← Back to website</a>
  </section>
</main>
</body></html>
