<?php
require_once __DIR__ . '/../config/App.php';
?>
  </main><!-- /#main -->
</div><!-- /#app -->

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<!-- Core JS -->
<script src="<?= App::url('/assets/js/app.js') ?>"></script>

<?php if (isset($extraJs)): ?>
  <script src="<?= htmlspecialchars($extraJs, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>

</body>
</html>
