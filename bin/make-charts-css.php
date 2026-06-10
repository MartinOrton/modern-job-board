<?php

$layout = <<<'CSS'
/* Modern Job Board admin performance charts */

.mjb-admin-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.mjb-chart-panel {
    background: var(--mjb-bg-white);
    border: 1px solid var(--mjb-border);
    border-radius: 1rem;
    padding: 1.5rem;
}

.mjb-chart-panel h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1rem;
    font-family: var(--mjb-font-sans);
}

.mjb-chart-row {
    display: grid;
    grid-template-columns: minmax(120px, 1.2fr) 2fr 48px;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.75rem;
}

.mjb-chart-label {
    font-size: 0.875rem;
    color: var(--mjb-text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mjb-chart-track {
    background: var(--mjb-bg-neutral);
    border-radius: 999px;
    height: 10px;
    overflow: hidden;
}

.mjb-chart-bar {
    height: 100%;
    border-radius: 999px;
    min-width: 2px;
}

.mjb-chart-bar-views {
    background: var(--mjb-primary);
}

.mjb-chart-bar-apps {
    background: var(--mjb-accent);
}

.mjb-chart-value {
    font-size: 0.875rem;
    font-weight: 600;
    text-align: right;
}

CSS;

$widths = "\n/* Bar width utilities (0-100%) */\n";

for ($i = 0; $i <= 100; $i++) {
    $widths .= '.mjb-chart-w-' . $i . ' { width: ' . $i . "%; }\n";
}

$output = $layout . $widths;
$path = dirname(__DIR__) . '/assets/css/mjb-charts.css';
file_put_contents($path, $output);

echo 'Wrote ' . strlen($output) . ' bytes to ' . $path . PHP_EOL;