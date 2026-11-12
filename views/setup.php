<?php $title = 'First admin setup'; require __DIR__ . '/_auth_head.php'; ?>
<main class="auth-shell">
  <section class="auth-card auth-card-wide">
    <a class="auth-brand" href="/"><img src="/assets/nexum-chatbot-favicon.png" alt="" width="52" height="52"><span>NEXUM <b>CHATBOT</b></span></a>
    <p class="eyebrow">One-time setup</p>
    <h1>Create the first administrator.</h1>
    <p class="intro">This page disables itself as soon as the first active admin exists.</p>
    <form method="post" action="/setup">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label>Setup token<input name="setup_token" type="password" autocomplete="off" required></label>
      <label>Full name<input name="name" autocomplete="name" minlength="2" maxlength="100" required></label>
      <label>Admin email<input name="email" type="email" autocomplete="username" required></label>
      <label>Admin password<input name="password" type="password" autocomplete="new-password" minlength="12" required><small>At least 12 characters with uppercase, lowercase, a number, and a symbol.</small></label>
      <button type="submit">Create administrator</button>
    </form>
  </section>
</main>
</body></html>
