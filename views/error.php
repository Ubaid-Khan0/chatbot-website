<?php $title = 'Error ' . $status; require __DIR__ . '/_auth_head.php'; ?>
<main class="auth-shell">
  <section class="auth-card error-card">
    <p class="eyebrow">Error <?= (int) $status ?></p>
    <h1>Something went off course.</h1>
    <p class="intro"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <a class="button-link" href="/">Return home</a>
  </section>
</main>
</body></html>
