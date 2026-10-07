<?php

use Plugin\HeaderFooterBuilder\Support\Runtime;

?>
<!doctype html>
<html lang="<?= phpb_e(phpb_current_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= phpb_e($page->getName()); ?></title>
    <?= Runtime::canvasAssets(); ?>
</head>
<body data-hfb-kind="<?= phpb_e($page->get('hfb_kind')); ?>">
    <?= $body; ?>
</body>
</html>
