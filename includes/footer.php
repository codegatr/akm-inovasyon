<?php if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); } ?>
  </div>
  <footer class="footbar">
    <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
    <span class="muted">Sistem v<?= e(CURRENT_VERSION) ?></span>
  </footer>
</main>

<script src="assets/js/app.js?v=<?= e(CURRENT_VERSION) ?>"></script>
</body>
</html>
