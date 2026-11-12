<?php

declare(strict_types=1);

namespace Nexum;

final class SiteRenderer
{
    public function __construct(private readonly string $root, private readonly Store $store) {}

    public function publicPage(string $page): string
    {
        $file = $this->root . '/dist/' . $page . '.html';
        if (!is_file($file)) throw new HttpException(404, 'Page not found.');
        $html = $this->rewriteAssetsAndLinks((string) file_get_contents($file));
        if ($page === 'contact') $html = str_replace('data-contact-form', 'data-contact-form-php', $html);

        $plans = $this->store->plans(true);
        $defaultPrices = ['Starter'=>'PKR 150,000','Growth'=>'PKR 300,000','Custom'=>'PKR 600,000+'];
        $defaultDescriptions = [
            'Starter'=>'For a focused chatbot with a clear purpose and compact knowledge set.',
            'Growth'=>'For businesses combining support, qualification, or multiple customer paths.',
            'Custom'=>'For advanced journeys, larger knowledge sets, or deeper business integrations.',
        ];
        foreach ($plans as $plan) {
            $name = (string) ($plan['name'] ?? '');
            $price = 'PKR ' . number_format((int) ($plan['starting_price_pkr'] ?? 0)) . ($plan['price_suffix'] ?? '');
            if (isset($defaultPrices[$name])) $html = str_replace($defaultPrices[$name], $price, $html);
            if ($page === 'pricing' && isset($defaultDescriptions[$name])) {
                $html = str_replace($defaultDescriptions[$name], htmlspecialchars((string) $plan['description'], ENT_QUOTES, 'UTF-8'), $html);
            }
        }

        $content = $this->store->content();
        if ($page === 'index') {
            $html = str_replace('Every conversation.<br><em>Connected.</em>', $this->heroTitle((string) ($content['heroTitle'] ?? 'Every conversation. Connected.')), $html);
            $defaultLead = 'An AI chatbot built around your business—ready to answer, guide, qualify, and support your customers around the clock.';
            $html = str_replace($defaultLead, htmlspecialchars((string) ($content['heroLead'] ?? $defaultLead), ENT_QUOTES, 'UTF-8'), $html);
        }
        $html = str_replace('Find the right chatbot package.', htmlspecialchars((string) ($content['ctaTitle'] ?? 'Find the right chatbot package.'), ENT_QUOTES, 'UTF-8'), $html);
        $html = str_replace('info@nexumglobal.solutions', htmlspecialchars((string) ($content['contactEmail'] ?? 'info@nexumglobal.solutions'), ENT_QUOTES, 'UTF-8'), $html);

        $config = htmlspecialchars(base64_encode((string) json_encode($plans, JSON_UNESCAPED_UNICODE)), ENT_QUOTES, 'UTF-8');
        return str_replace('</body>', '<div hidden data-plan-config="' . $config . '"></div><script src="/assets/php-site.js"></script></body>', $html);
    }

    public function adminPage(array $user): string
    {
        $html = $this->rewriteAssetsAndLinks((string) file_get_contents($this->root . '/dist/admin.html'));
        $html = str_replace('<script src="/assets/admin.js"></script>', '<script src="/assets/admin-api.js"></script>', $html);
        $html = str_replace('</head>', '<link rel="stylesheet" href="/assets/admin-live.css"></head>', $html);
        $html = str_replace('<head>', '<head><meta name="csrf-token" content="' . htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') . '">', $html);
        $html = str_replace('<body>', '<body data-admin-role="' . htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') . '" data-admin-name="' . htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') . '">', $html);
        return str_replace('Frontend admin prototype', 'MongoDB connected', str_replace('Changes are saved only in this browser. Connect Supabase before using real customer data.', 'Authenticated production dashboard. Changes are stored in MongoDB Atlas.', $html));
    }

    private function rewriteAssetsAndLinks(string $html): string
    {
        $replacements = [
            'href="styles.css"'=>'href="/assets/styles.css"','href="admin.css"'=>'href="/assets/admin.css"',
            'src="script.js"'=>'src="/assets/script.js"','src="admin.js"'=>'src="/assets/admin.js"',
            'href="nexum-chatbot-favicon.png"'=>'href="/assets/nexum-chatbot-favicon.png"','src="nexum-chatbot-favicon.png"'=>'src="/assets/nexum-chatbot-favicon.png"',
            'href="index.html"'=>'href="/"','href="features.html"'=>'href="/features"','href="solutions.html"'=>'href="/solutions"','href="pricing.html#'=>'href="/pricing#','href="pricing.html"'=>'href="/pricing"',
            'href="contact.html?'=>'href="/contact?','href="contact.html"'=>'href="/contact"',
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    private function heroTitle(string $title): string
    {
        $parts = preg_split('/\s+/', trim($title));
        if (count($parts) < 2) return htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $last = array_pop($parts);
        return htmlspecialchars(implode(' ', $parts), ENT_QUOTES, 'UTF-8') . '<br><em>' . htmlspecialchars($last, ENT_QUOTES, 'UTF-8') . '</em>';
    }
}
