<?php
declare(strict_types=1);

$user = current_user();
?>
<?php if ($user): ?>
        </main>
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></span>
        </footer>
    </div>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
