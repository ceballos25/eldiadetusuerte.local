<?php
declare(strict_types=1);

namespace App\Application\Marketing;

/**
 * Meta Pixel anti-duplication: only PageView (once per visit) and Purchase (once per sale).
 */
final class MetaPixelGuard
{
    private const SESSION_PAGEVIEW_PREFIX = 'meta_pageview_sent_';
    private const PURCHASE_PREFIX = 'meta_purchase_sent_';

    public function pageViewStorageKey(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');

        return md5($path) . '-' . date('Ymd');
    }

    public function shouldSendPageView(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return empty($_SESSION[self::SESSION_PAGEVIEW_PREFIX . $this->pageViewStorageKey()]);
    }

    public function markPageViewSent(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_PAGEVIEW_PREFIX . $this->pageViewStorageKey()] = true;
        }
    }

    public function shouldSendPurchase(string $saleCode): bool
    {
        if ($saleCode === '') {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = self::PURCHASE_PREFIX . $saleCode;
            if (!empty($_SESSION[$key])) {
                return false;
            }
        }

        return !$this->checkDbPurchaseSent($saleCode);
    }

    public function markPurchaseSent(string $saleCode): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && $saleCode !== '') {
            $_SESSION[self::PURCHASE_PREFIX . $saleCode] = true;
        }
    }

    private function checkDbPurchaseSent(string $saleCode): bool
    {
        if ($saleCode === '' || !class_exists('Db')) {
            return false;
        }
        try {
            $row = Db::fetchOne(
                'SELECT id_audit FROM audit_logs WHERE action_audit = :a AND new_data LIKE :code LIMIT 1',
                [':a' => 'meta.purchase.sent', ':code' => '%"' . $saleCode . '"%']
            );

            return $row !== null && $row !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
