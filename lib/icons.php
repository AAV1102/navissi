<?php
/** Iconos SVG inline (stroke, estilo Feather/Lucide) - livianos, sin dependencias externas. */
function icon(string $nombre, string $claseExtra = ''): string {
    $clase = trim('icon ' . $claseExtra);
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'ticket' => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4Z"/><path d="M9 6v12" stroke-dasharray="2 2"/>',
        'inventory' => '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M9 16h1M14 16h1"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 3-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17.5" cy="8.5" r="2.5"/><path d="M15 14.2c2.6.3 4.5 2.3 4.5 5.3"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.6 12.4 20 3M16 7l3 3M13 10l2.5 2.5"/>',
        'eye' => '<path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c7 0 10.5 7 10.5 7a15.6 15.6 0 0 1-3.4 4.4M6.5 6.6C3.4 8.6 1.5 12 1.5 12S5 19 12 19a10.8 10.8 0 0 0 3.4-.6"/><path d="M9.5 9.7a3 3 0 0 0 4.1 4.2"/>',
        'dollar' => '<circle cx="12" cy="12" r="9.5"/><path d="M12 6.5v11M15.5 9.2c0-1.5-1.6-2.2-3.5-2.2s-3.5.9-3.5 2.4 1.6 1.9 3.5 2.1 3.5.7 3.5 2.2S13.9 16 12 16s-3.5-.7-3.5-2.2"/>',
        'chat' => '<path d="M21 11.5a8.4 8.4 0 0 1-8.8 8.4 8.9 8.9 0 0 1-3.6-.7L3 21l1.8-5.4A8.3 8.3 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5Z"/>',
        'send' => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'sun' => '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2 12h2.5M19.5 12H22M4.2 19.8 6 18M18 6l1.8-1.8"/>',
        'moon' => '<path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11Z"/>',
        'accessibility' => '<circle cx="12" cy="4.5" r="1.8"/><path d="M4 8.5h16M12 8.5v5M8.5 21l2-7.5M15.5 21l-2-7.5M8 12l-3 2M16 12l3 2"/>',
        'contrast' => '<circle cx="12" cy="12" r="9.5"/><path d="M12 2.5a9.5 9.5 0 0 1 0 19Z"/>',
        'type' => '<path d="M5 6h14M12 6v14M9 20h6"/>',
        'sort' => '<path d="m7 15 5 5 5-5M7 9l5-5 5 5"/>',
        'briefcase' => '<rect x="2.5" y="7" width="19" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M2.5 13h19"/>',
        'book' => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H5.5A1.5 1.5 0 0 1 4 18.5Z"/><path d="M8 3v16"/>',
        'cloud' => '<path d="M7 18a4.5 4.5 0 0 1-.5-9 5.5 5.5 0 0 1 10.8-1.5A4 4 0 0 1 17 18Z"/>',
        'robot' => '<rect x="4" y="8" width="16" height="11" rx="2.5"/><circle cx="9" cy="13.5" r="1.3" fill="currentColor" stroke="none"/><circle cx="15" cy="13.5" r="1.3" fill="currentColor" stroke="none"/><path d="M12 8V4M9 4h6"/><path d="M2 12v3M22 12v3"/>',
        'shield' => '<path d="M12 3 4.5 6v6c0 4.8 3.2 7.9 7.5 9 4.3-1.1 7.5-4.2 7.5-9V6Z"/>',
        'zap' => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>',
        'store' => '<path d="M3 9.5 4.5 4h15L21 9.5"/><path d="M3 9.5a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0"/><path d="M5 9.5V20h14V9.5"/><path d="M9.5 20v-6h5v6"/>',
        'wifi' => '<path d="M2 8.5a16 16 0 0 1 20 0"/><path d="M5.5 12.2a11 11 0 0 1 13 0"/><path d="M9 16a5.5 5.5 0 0 1 6 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/>',
        'file' => '<path d="M13 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M13 3v5h5"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'megaphone' => '<path d="M3 10v4a1 1 0 0 0 1 1h2l4 4V5L6 9H4a1 1 0 0 0-1 1Z"/><path d="M14 8a4 4 0 0 1 0 8M17.5 5.5a8.5 8.5 0 0 1 0 13"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M2 14h4M10 8h4M18 12h4"/>',
        'log' => '<path d="M9 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/><path d="M9 15 21 3M21 3h-6M21 3v6"/>',
        'graduation' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow-right' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        'external' => '<path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>',
    ];
    $inner = $paths[$nombre] ?? $paths['file'];
    return "<svg class=\"{$clase}\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\">{$inner}</svg>";
}
