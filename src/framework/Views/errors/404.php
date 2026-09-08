<?php

/**
 * Included by Kernel::errorBody(), which puts these in scope.
 *
 * @var int $status
 * @var string $title
 * @var string $description
 */

?>
<h1 style="margin: 20px 0; text-align: center;"><?= $title !== '' ? htmlspecialchars("{$status} {$title}") : '404 Page not found' ?></h1>
<?php if ($description !== ''): ?>
    <p style="margin: 0 0 20px; text-align: center;"><?= htmlspecialchars($description) ?></p>
<?php endif; ?>
