<?php
declare(strict_types=1);

/**
 * Pixel de Meta en accesorios — solo init (sin eventos; Purchase va por CAPI en servidor principal).
 */
function paymentMetaPixelHead(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    $pixelId = defined('META_PIXEL_ID') ? trim((string)META_PIXEL_ID) : '';
    if ($pixelId === '') {
        return;
    }
    ?>
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', <?= json_encode($pixelId, JSON_UNESCAPED_SLASHES) ?>);
    </script>
    <?php
}
