<?php
declare(strict_types=1);

/**
 * Chip de número reutilizable (recibos, correo, success PSE, admin con app.css).
 * Incluye estilos inline para clientes de correo y páginas sin app.css (accesorios).
 *
 * @param string $number
 * @param string $variant     ''|'selected'|'libre'|'vendido'|'reservado'|'recibo'|'premium'|'lg'
 * @param string $extraClasses
 * @param int    $idTicket
 */
function cr_numero_chip(
    string $number,
    string $variant = 'selected',
    string $extraClasses = '',
    int $idTicket = 0
): string {
    $classes = ['cr-numero-chip'];

    if ($variant === 'lg') {
        $classes[] = 'cr-numero-chip--lg';
    } elseif ($variant !== '' && $variant !== 'selected') {
        $classes[] = 'cr-numero-chip--' . preg_replace('/[^a-z]/', '', $variant);
    }

    if ($extraClasses !== '') {
        foreach (preg_split('/\s+/', trim($extraClasses)) as $c) {
            if ($c !== '') {
                $classes[] = $c;
            }
        }
    }

    if ($idTicket > 0) {
        $classes[] = 'numero-ticket';
    }

    $classAttr = htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8');
    $styleAttr = htmlspecialchars(cr_numero_chip_inline_style($variant), ENT_QUOTES, 'UTF-8');
    $dataAttr = $idTicket > 0 ? ' data-ticket-id="' . (int)$idTicket . '"' : '';

    return '<span class="' . $classAttr . '" style="' . $styleAttr . '"' . $dataAttr . '>'
        . htmlspecialchars($number, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

/**
 * Estilos inline (email-safe: inline-block, colores hex, sin flex).
 */
function cr_numero_chip_inline_style(string $variant = 'selected'): string
{
    $isLarge = ($variant === 'lg');
    $colorKey = $isLarge ? 'selected' : ($variant === '' ? 'selected' : preg_replace('/[^a-z]/', '', $variant));
    if ($colorKey === '') {
        $colorKey = 'selected';
    }

    $palettes = [
        'selected' => [
            'background-color' => '#ffffff',
            'color' => '#000000',
            'border' => '2px solid #0D0D0D',
        ],
        'libre' => [
            'background-color' => '#ffffff',
            'color' => '#0D0D0D',
            'border' => '2px solid #d0d0d0',
        ],
        'vendido' => [
            'background-color' => '#eeeeee',
            'color' => '#888888',
            'border' => '2px solid #cccccc',
        ],
        'reservado' => [
            'background-color' => '#ffffff',
            'color' => '#15803d',
            'border' => '2px dashed #16a34a',
        ],
        'premium' => [
            'background-color' => '#16a34a',
            'color' => '#ffffff',
            'border' => '2px solid #15803d',
        ],
        'recibo' => [
            'background-color' => '#ffffff',
            'color' => '#000000',
            'border' => '2px solid #0D0D0D',
        ],
    ];

    $colors = $palettes[$colorKey] ?? $palettes['selected'];

    $styles = array_merge([
        'display' => 'inline-block',
        'text-align' => 'center',
        'vertical-align' => 'middle',
        'font-family' => 'Montserrat, Arial, Helvetica, sans-serif',
        'font-weight' => '700',
        'font-size' => $isLarge ? '17px' : '16px',
        'line-height' => '1.2',
        'letter-spacing' => '0.02em',
        'border-radius' => '10px',
        'padding' => $isLarge ? '0 12px' : '7px 10px',
        'min-width' => $isLarge ? '54px' : '46px',
        'min-height' => $isLarge ? '48px' : 'auto',
        'margin' => '0 6px 6px 0',
        'box-sizing' => 'border-box',
        'text-decoration' => 'none',
        'white-space' => 'nowrap',
    ], $colors);

    $parts = [];
    foreach ($styles as $property => $value) {
        $parts[] = $property . ':' . $value;
    }

    return implode(';', $parts);
}
