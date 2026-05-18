<?php
/* ============================================================
   GradeMS — Shared Footer Partial
   File: shared/footer.php
   Usage: include at the bottom of every authenticated page
   ============================================================ */
?>
  </main><!-- /#main -->
</div><!-- /#app -->

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<!-- Core JS -->
<script src="/assets/js/app.js"></script>

<?php if (isset($extraJs)): ?>
  <script src="<?= htmlspecialchars($extraJs, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>

</body>
</html>
