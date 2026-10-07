<?php

use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Runtime;

use function App\Shared\Helpers\get_option;
use function Codefy\Framework\Helpers\config;

$company = $block->setting('company', true) ?: (string) get_option('sitename', config()->string('app.name', 'Website'));
$year = date('Y');
$start = $block->setting('start-year', true);
if (preg_match('/^\d{4}$/', $start) && (int) $start < (int) $year) {
    $year = $start . '–' . $year;
}
$companyHtml = Html::escape($company);
if ($block->setting('link', true)) {
    $companyHtml = '<a href="' . Html::escape(Html::url($block->setting('link', true))) . '">' . $companyHtml . '</a>';
}
echo Runtime::assets();
?>
<div class="hfb-copyright"><?= strtr(Html::escape($block->setting('text', true)), ['{year}' => Html::escape($year), '{company}' => $companyHtml]); ?></div>
