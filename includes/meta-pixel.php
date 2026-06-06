<?php
declare(strict_types=1);

use App\Application\Marketing\MetaConversionsApi;
use App\Application\Marketing\MetaPixelGuard;

function edts_meta_pixel_head(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    if (!MetaConversionsApi::isPixelConfigured()) {
        return;
    }

    $guard = new MetaPixelGuard();
    $metaPixelId = MetaConversionsApi::pixelId();
    $metaPageViewEventId = '';
    $sendPageView = $guard->shouldSendPageView();
    $sendCapiPageView = $sendPageView && MetaConversionsApi::isCapiConfigured();
    $pageViewRef = (string)(session_id() ?: 'guest') . '-' . $guard->pageViewStorageKey();

    if ($sendCapiPageView) {
        $metaPageViewEventId = MetaConversionsApi::eventId('PageView', $pageViewRef);
        if (MetaConversionsApi::sendPageView($metaPageViewEventId)) {
            $guard->markPageViewSent();
        }
    } elseif ($sendPageView) {
        $metaPageViewEventId = MetaConversionsApi::eventId('PageView', $pageViewRef);
        $guard->markPageViewSent();
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
    fbq('init', <?= json_encode($metaPixelId, JSON_UNESCAPED_SLASHES) ?>);
    <?php if ($sendPageView && $metaPageViewEventId !== ''): ?>
    fbq('track', 'PageView', {}, {eventID: <?= json_encode($metaPageViewEventId, JSON_UNESCAPED_SLASHES) ?>});
    <?php endif; ?>
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?= htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8') ?>&ev=PageView&noscript=1"
    /></noscript>
    <script>
    window.META_EVENTS_CONFIG = {
        enabled: true,
        capiEnabled: <?= MetaConversionsApi::isCapiConfigured() ? 'true' : 'false' ?>,
        pixelId: <?= json_encode($metaPixelId, JSON_UNESCAPED_SLASHES) ?>,
        ajaxUrl: <?= json_encode(BASE_URL . '/front/ajax/meta.ajax.php', JSON_UNESCAPED_SLASHES) ?>,
        standardEvents: <?= json_encode(MetaConversionsApi::BROWSER_TRACK_EVENTS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    </script>
    <?php
}
