<?php
// includes/sections.php
// Rendering helpers for sections (keeps index.php tidy)

require_once __DIR__ . '/format.php';

function render_description(string $section) {
    $text = description_for($section);
    if ($text) {
        echo "<p class=\"section-desc\">".htmlspecialchars($text)."</p>";
    }
}

function render_collapsible_toggle(string $id, int $count) {
    $label = $count > 0 ? "Show details ({$count})" : "Show details";
    echo "<button class=\"toggle-btn\" data-target=\"{$id}\">{$label}</button>";
}

function render_table_wrapper_start(string $id, bool $collapsed = true) {
    $cls = $collapsed ? 'collapsed' : 'expanded';
    echo "<div id=\"{$id}\" class=\"table-wrapper {$cls}\">";
}

function render_table_wrapper_end() {
    echo "</div>";
}
