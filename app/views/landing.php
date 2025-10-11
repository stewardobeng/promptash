<?php
$appName = isset($appSettings) && $appSettings !== null ? $appSettings->getAppName() : 'Promptash';
$appTagline = isset($appSettings) && $appSettings !== null ? $appSettings->getAppDescription() : 'Professional prompt management made simple.';
$tiers = isset($publicTiers) && is_array($publicTiers) ? array_filter($publicTiers, fn ($tier) => $tier['is_active']) : [];

$formatPrice = function ($amount) use ($appSettings) {
    if ($appSettings !== null && method_exists($appSettings, 'formatPrice')) {
        return $appSettings->formatPrice($amount);
    }

    return '$' . number_format($amount, 0);
};

$formatLimit = function ($limit, $label) {
    if ((int)$limit === 0) {
        return "Unlimited {$label}";
    }
    return number_format($limit) . " {$label}";
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($appName); ?> — Organize Every Prompt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #0066ab 0%, #004d85 40%, #2b3b9d 100%);
            --gradient-accent: linear-gradient(135deg, #ff8a5c 0%, #ff5f6d 100%);
            --color-primary: #005da8;
            --color-secondary: #0b2f4a;
            --color-muted: #6c7890;
            --color-bg: #f5f7fb;
            --shadow-xl: 0 30px 60px rgba(11, 47, 74, 0.20);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #182438;
            background: var(--color-bg);
            line-height: 1.5;
        }

        a {
            text-decoration: none;
        }

        .nav-bar {
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.85);
            border-bottom: 1px solid rgba(0, 102, 171, 0.08);
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 700;
            font-size: 1.1rem;
            color: #0b2540;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--gradient-primary);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.4rem;
            box-shadow: 0 12px 20px rgba(0, 102, 171, 0.25);
        }

        .nav-actions {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .nav-link {
            color: var(--color-muted);
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .nav-link:hover {
            color: var(--color-primary);
        }

        .btn-outline,
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            font-weight: 600;
            padding: 10px 22px;
            border: 1px solid transparent;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .btn-outline {
            border-color: rgba(0, 93, 168, 0.25);
            color: var(--color-primary);
            background: rgba(255, 255, 255, 0.6);
        }

        .btn-outline:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 18px rgba(0, 93, 168, 0.12);
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: #fff;
            box-shadow: 0 18px 30px rgba(0, 80, 140, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 22px 36px rgba(0, 80, 140, 0.35);
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 120px 24px 140px;
            background: radial-gradient(circle at 20% 20%, rgba(0, 102, 171, 0.28), transparent 60%),
                        radial-gradient(circle at 80% 10%, rgba(255, 138, 92, 0.25), transparent 55%),
                        #040c1a;
            color: #fff;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 48px;
            align-items: center;
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 800;
            margin: 0;
            line-height: 1.1;
        }

        .hero-description {
            margin: 20px 0 30px;
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.85);
        }

        .hero-points {
            display: grid;
            gap: 12px;
            margin-top: 30px;
        }

        .hero-point {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .hero-point i {
            color: #ffd166;
            font-size: 1rem;
        }

        .hero-visual {
            position: relative;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 28px;
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 40px 60px rgba(4, 12, 26, 0.35);
        }

        .hero-visual::after {
            content: "";
            position: absolute;
            inset: -60px;
            background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.15), transparent 60%);
            z-index: -1;
        }

        .hero-mockup {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(6px);
        }

        .mockup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .mockup-body {
            display: grid;
            gap: 14px;
        }

        .mockup-card {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .mockup-card strong {
            display: block;
            margin-bottom: 6px;
            color: #fff;
            font-weight: 600;
        }

        .mockup-card span {
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.9rem;
        }

        .section {
            max-width: 1100px;
            margin: 0 auto;
            padding: 110px 24px 0;
        }

        .section-heading {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-heading span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            background: rgba(0, 102, 171, 0.12);
            color: var(--color-primary);
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        .section-heading h2 {
            margin: 16px 0 12px;
            font-size: clamp(2rem, 4vw, 2.5rem);
            font-weight: 700;
            color: #0b2540;
        }

        .section-heading p {
            margin: 0 auto;
            max-width: 560px;
            color: var(--color-muted);
            font-size: 1.05rem;
        }

        .billing-toggle {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: rgba(0, 93, 168, 0.08);
            padding: 6px;
            margin: 0 auto 36px;
            position: relative;
        }

        .billing-option {
            border: none;
            background: transparent;
            color: var(--color-muted);
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 999px;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
            font-size: 0.95rem;
        }

        .billing-option.active {
            background: #fff;
            color: var(--color-primary);
            box-shadow: 0 10px 18px rgba(0, 93, 168, 0.18);
        }

        .billing-option:focus-visible {
            outline: 2px solid rgba(0, 93, 168, 0.4);
            outline-offset: 2px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 26px;
        }

        .feature-card {
            background: #fff;
            border-radius: 20px;
            padding: 26px;
            box-shadow: 0 22px 34px rgba(11, 47, 74, 0.08);
            border: 1px solid rgba(11, 47, 74, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 28px 40px rgba(11, 47, 74, 0.12);
        }

        .feature-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(0, 93, 168, 0.12);
            display: grid;
            place-items: center;
            color: var(--color-primary);
            font-size: 1.2rem;
            margin-bottom: 18px;
        }

        .feature-card h3 {
            margin: 0 0 12px;
            font-size: 1.25rem;
            color: #0b2540;
        }

        .feature-card p {
            margin: 0;
            color: var(--color-muted);
        }

        .pricing {
            padding-bottom: 110px;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 28px;
        }

        .pricing-card {
            background: #fff;
            border-radius: 26px;
            padding: 32px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(0, 93, 168, 0.08);
            display: flex;
            flex-direction: column;
            gap: 18px;
            position: relative;
        }

        .pricing-card.popular {
            border: 2px solid transparent;
            background:
                linear-gradient(#fff, #fff) padding-box,
                var(--gradient-primary) border-box;
        }

        .pricing-badge {
            position: absolute;
            top: 24px;
            right: 24px;
            background: var(--gradient-accent);
            color: #fff;
            font-size: 0.72rem;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .pricing-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0b2540;
            margin: 0;
        }

        .pricing-desc {
            color: var(--color-muted);
            margin: 0;
            min-height: 48px;
        }

        .pricing-amount {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--color-primary);
            margin: 0;
        }

        .pricing-amount span {
            font-size: 1rem;
            color: var(--color-muted);
            font-weight: 500;
        }

        .pricing-amount .price-caption {
            margin-left: 6px;
        }

        .pricing-note {
            color: var(--color-muted);
            font-size: 0.9rem;
            min-height: 18px;
        }

        .pricing-limits {
            display: grid;
            gap: 10px;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .pricing-limits li {
            display: flex;
            gap: 12px;
            align-items: center;
            color: #1b2f46;
        }

        .pricing-limits li i {
            color: #2eb67d;
        }

        .pricing-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .secondary-section {
            background: #fff;
            border-radius: 28px;
            padding: 50px;
            margin-top: 90px;
            box-shadow: 0 30px 50px rgba(11, 47, 74, 0.08);
            border: 1px solid rgba(0, 93, 168, 0.08);
        }

        .steps {
            display: grid;
            gap: 26px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .step {
            display: flex;
            gap: 18px;
            align-items: flex-start;
        }

        .step-number {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: rgba(0, 93, 168, 0.12);
            color: var(--color-primary);
            font-weight: 700;
            display: grid;
            place-items: center;
        }

        .step h4 {
            margin: 0 0 8px;
            font-size: 1.1rem;
            color: #0b2540;
        }

        .faq {
            display: grid;
            gap: 18px;
            margin-top: 36px;
        }

        .faq-item {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 18px;
            padding: 20px 22px;
            border: 1px solid rgba(11, 47, 74, 0.08);
        }

        .faq-item h5 {
            margin: 0 0 8px;
            font-size: 1rem;
            color: #0b2540;
        }

        .faq-item p {
            margin: 0;
            color: var(--color-muted);
        }

        .cta-footer {
            margin: 120px auto 80px;
            max-width: 900px;
            background: var(--gradient-primary);
            border-radius: 30px;
            padding: 60px;
            text-align: center;
            color: #fff;
            box-shadow: 0 40px 60px rgba(11, 47, 74, 0.25);
        }

        .cta-footer h2 {
            margin: 0 0 16px;
            font-size: clamp(2rem, 4vw, 2.6rem);
            font-weight: 700;
        }

        .cta-footer p {
            margin: 0 auto 30px;
            max-width: 520px;
            color: rgba(255, 255, 255, 0.85);
            font-size: 1.05rem;
        }

        .footer-actions {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .footer {
            background: #030917;
            color: rgba(255, 255, 255, 0.75);
            padding: 70px 24px 40px;
        }

        .footer-content {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            gap: 40px;
        }

        .footer-columns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 32px;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 14px;
            color: rgba(255, 255, 255, 0.85);
        }

        .footer-brand .brand-icon {
            background: var(--gradient-primary);
            box-shadow: none;
        }

        .footer-brand p {
            margin: 0;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
        }

        .footer-column h6 {
            margin: 0 0 14px;
            color: #fff;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.92rem;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: #fff;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.55);
        }

        .footer-bottom span {
            display: block;
        }

        .footer-bottom-links {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .footer-bottom-links a {
            color: rgba(255, 255, 255, 0.6);
            transition: color 0.2s ease;
        }

        .footer-bottom-links a:hover {
            color: #fff;
        }

        @media (max-width: 768px) {
            .hero {
                padding: 90px 20px 110px;
            }

            .nav-content {
                padding: 16px 18px;
            }

            .nav-actions {
                gap: 10px;
            }

            .btn-outline,
            .btn-primary {
                padding: 9px 18px;
            }

            .hero-visual {
                padding: 24px;
            }

            .cta-footer {
                padding: 46px 30px;
            }
        }
    </style>
</head>
<body>
    <header class="nav-bar">
        <div class="nav-content">
            <a href="index.php?page=landing" class="brand">
                <span class="brand-icon"><i class="fas fa-magic"></i></span>
                <span><?php echo htmlspecialchars($appName); ?></span>
            </a>
            <div class="nav-actions">
                <a class="nav-link" href="#pricing">Pricing</a>
                <a class="nav-link" href="#why">Why <?php echo htmlspecialchars($appName); ?></a>
                <a class="btn-outline" href="index.php?page=login">Sign In</a>
                <a class="btn-primary" href="index.php?page=checkout&plan=personal">Choose Plan</a>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <div>
                <h1 class="hero-title">
                    The command center for every prompt your team creates.
                </h1>
                <p class="hero-description">
                    <?php echo htmlspecialchars($appTagline); ?> Collect ideas, refine them with AI, track usage, and keep your entire prompt library aligned across projects.
                </p>
                <div class="hero-actions">
                    <a class="btn-primary" href="index.php?page=checkout&plan=personal&trial=1">
                        <i class="fas fa-rocket"></i> Start Personal Trial
                    </a>
                    <a class="btn-outline" href="index.php?page=login">
                        <i class="fas fa-user-check"></i> I already have an account
                    </a>
                </div>
                <div class="hero-points">
                    <div class="hero-point"><i class="fas fa-check-circle"></i> Beautiful workspace for prompts, notes, and assets</div>
                    <div class="hero-point"><i class="fas fa-check-circle"></i> Built-in AI generator with guided collaboration</div>
                    <div class="hero-point"><i class="fas fa-check-circle"></i> Membership-ready with analytics and notifications</div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-mockup">
                    <div class="mockup-header">
                        <span><i class="fas fa-layer-group"></i> Prompt collections</span>
                        <span><i class="fas fa-signal"></i> Usage insights</span>
                    </div>
                    <div class="mockup-body">
                        <div class="mockup-card">
                            <strong>Brainstorm Launch Campaign</strong>
                            <span>Create step-by-step prompt series for product messaging, social captions, and customer support macros.</span>
                        </div>
                        <div class="mockup-card">
                            <strong>AI Workflows in Motion</strong>
                            <span>Monitor prompt usage, automate reminders, and keep premium members in sync.</span>
                        </div>
                        <div class="mockup-card">
                            <strong>Promptash Premium Toolkit</strong>
                            <span>Unlimited prompt collections, AI generations, sharing controls, and advanced analytics.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="why">
        <div class="section-heading">
            <span><i class="fas fa-star"></i> Built for prompt-led teams</span>
            <h2>Everything you need to deliver consistent, on-brand prompts</h2>
            <p>Promptash wraps your entire prompt lifecycle into a single, secure workspace. Move faster without losing control.</p>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-folder-tree"></i></div>
                <h3>Organize with precision</h3>
                <p>Create categories, apply tags, and bookmark your most impactful prompts. Find what you need instantly with full-text search and smart filters.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-users-cog"></i></div>
                <h3>Membership ready</h3>
                <p>Premium-ready membership tiers with usage limits, payment tracking, and auto-upgrades for admins come standard out of the box.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-robot"></i></div>
                <h3>AI-assisted creation</h3>
                <p>Use built-in AI generation to inspire new ideas, refine drafts, and collaborate with teammates using real-time notifications.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Enterprise-grade security</h3>
                <p>Two-factor authentication, secure session handling, and granular audit logs keep every prompt protected end-to-end.</p>
            </div>
        </div>
    </section>

    <section class="section secondary-section">
        <div class="section-heading" style="margin-bottom: 32px;">
            <span><i class="fas fa-bolt"></i> Launch in minutes</span>
            <h2>From idea to organized prompt library in four simple steps</h2>
            <p>Promptash is designed to work on modern shared hosting or cloud servers—no complex setup required.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div>
                    <h4>Create your workspace</h4>
                    <p>Sign up, name your workspace, and invite teammates. The onboarding checklist guides you through essential configuration.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div>
                    <h4>Import or craft prompts</h4>
                    <p>Import existing prompt libraries, or build new ones with rich text, attachments, and AI assistance.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div>
                    <h4>Automate engagement</h4>
                    <p>Set usage limits, trigger smart notifications, and integrate payment flows for premium members.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div>
                    <h4>Scale confidently</h4>
                    <p>Track usage trends, monitor roles, and keep every prompt within reach—whether you manage 50 or 5,000.</p>
                </div>
            </div>
        </div>
        <div class="faq">
            <div class="faq-item">
                <h5><i class="fas fa-question-circle"></i> Is Promptash suitable for solo creators?</h5>
                <p>Absolutely. Start with the Personal Plan to organize personal prompt collections, then upgrade when you add collaborators.</p>
            </div>
            <div class="faq-item">
                <h5><i class="fas fa-lock"></i> How secure is my prompt data?</h5>
                <p>Promptash enforces strong password policies, optional two-factor authentication, CSRF protection, and granular permission checks.</p>
            </div>
            <div class="faq-item">
                <h5><i class="fas fa-rocket"></i> Can I monetize my prompts?</h5>
                <p>Yes. Built-in membership tiers, Paystack payments, and usage tracking make it simple to run premium prompt libraries.</p>
            </div>
        </div>
    </section>

    <section class="section pricing" id="pricing">
        <div class="section-heading">
            <span><i class="fas fa-crown"></i> Pricing</span>
            <h2>Plans that scale with your creativity</h2>
            <p>Choose a plan that fits. Start with Personal, upgrade when you’re ready. No setup fees, cancel anytime.</p>
        </div>
        <div class="billing-toggle" role="radiogroup" aria-label="Billing period">
            <button type="button" class="billing-option active" data-billing="monthly" role="radio" aria-checked="true">
                Monthly billing
            </button>
            <button type="button" class="billing-option" data-billing="annual" role="radio" aria-checked="false">
                Yearly billing
            </button>
        </div>
        <div class="pricing-grid">
            <?php if (!empty($tiers)): ?>
                <?php foreach ($tiers as $tier): ?>
                    <?php
                        $isPopular = strtolower($tier['name']) === 'premium';
                        $monthly = $tier['price_monthly'];
                        $annual = $tier['price_annual'];
                        $features = is_array($tier['features']) ? $tier['features'] : [];

                        $monthlyAmount = (float)$monthly;
                        $annualAmount = (float)$annual;
                        $hasMonthly = $monthlyAmount > 0.0;
                        $hasAnnual = $annualAmount > 0.0;

                        $monthlyLabel = $hasMonthly ? $formatPrice($monthlyAmount) : 'Free';
                        $monthlyCaption = $hasMonthly ? '/month' : ($hasAnnual ? 'Included in annual plan' : '');

                        if ($hasAnnual) {
                            $annualLabel = $annualAmount === 0.0 ? 'Free' : $formatPrice($annualAmount);
                            $annualCaption = $annualAmount === 0.0 ? '' : '/year';
                        } else {
                            $annualLabel = $hasMonthly ? $formatPrice($monthlyAmount) : 'Free';
                            $annualCaption = $hasMonthly ? '/month' : '';
                        }

                        $defaultBilling = $hasMonthly ? 'monthly' : 'annual';

                        $monthlyNote = $hasMonthly ? 'Pay as you go monthly.' : ($hasAnnual ? 'Included in annual membership.' : '');
                        $annualNote = '';

                        if ($hasMonthly && $hasAnnual) {
                            $monthlyTotal = $monthlyAmount * 12;
                            $savings = max(0, $monthlyTotal - $annualAmount);
                            if ($savings > 0) {
                                $annualNote = 'Save ' . $formatPrice($savings) . ' each year.';
                            } else {
                                $annualNote = 'Billed yearly.';
                            }
                        } elseif ($hasAnnual && !$hasMonthly) {
                            $annualNote = 'Billed yearly.';
                        }

                        $initialAmount = $defaultBilling === 'monthly' ? $monthlyLabel : $annualLabel;
                        $initialCaption = $defaultBilling === 'monthly' ? $monthlyCaption : $annualCaption;
                        $initialNote = $defaultBilling === 'monthly' ? $monthlyNote : $annualNote;
                    ?>
                    <div class="pricing-card <?php echo $isPopular ? 'popular' : ''; ?>">
                        <?php if ($isPopular): ?>
                            <div class="pricing-badge"><i class="fas fa-fire"></i> Most Popular</div>
                        <?php endif; ?>
                        <h3 class="pricing-title"><?php echo htmlspecialchars($tier['display_name']); ?></h3>
                        <p class="pricing-desc"><?php echo htmlspecialchars($tier['description'] ?: 'Everything you need to stay organized.'); ?></p>
                        <p
                            class="pricing-amount"
                            data-default-mode="<?php echo $defaultBilling; ?>"
                            data-monthly-amount="<?php echo htmlspecialchars($monthlyLabel, ENT_QUOTES, 'UTF-8'); ?>"
                            data-annual-amount="<?php echo htmlspecialchars($annualLabel, ENT_QUOTES, 'UTF-8'); ?>"
                            data-monthly-caption="<?php echo htmlspecialchars($monthlyCaption, ENT_QUOTES, 'UTF-8'); ?>"
                            data-annual-caption="<?php echo htmlspecialchars($annualCaption, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <span class="price-number"><?php echo htmlspecialchars($initialAmount); ?></span>
                            <span class="price-caption" <?php echo empty($initialCaption) ? 'style="display:none;"' : ''; ?>>
                                <?php echo htmlspecialchars($initialCaption); ?>
                            </span>
                        </p>
                        <p
                            class="pricing-note"
                            data-monthly-note="<?php echo htmlspecialchars($monthlyNote, ENT_QUOTES, 'UTF-8'); ?>"
                            data-annual-note="<?php echo htmlspecialchars($annualNote, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo empty($initialNote) ? 'style="display:none;"' : ''; ?>
                        >
                            <?php echo htmlspecialchars($initialNote); ?>
                        </p>
                        <ul class="pricing-limits">
                            <li><i class="fas fa-check-circle"></i> <?php echo $formatLimit($tier['max_prompts_per_month'], 'prompts / month'); ?></li>
                            <li><i class="fas fa-check-circle"></i> <?php echo $formatLimit($tier['max_ai_generations_per_month'], 'AI generations'); ?></li>
                            <li><i class="fas fa-check-circle"></i> <?php echo $formatLimit($tier['max_categories'], 'categories'); ?></li>
                            <?php if (!empty($features)): ?>
                                <?php foreach ($features as $feature): ?>
                                    <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <div class="pricing-actions">
                            <?php if ($isPopular): ?>
                                <a class="btn-primary" href="index.php?page=checkout&plan=premium">
                                    Upgrade to Premium
                                </a>
                            <?php else: ?>
                                <a class="btn-primary" href="index.php?page=checkout&plan=personal&trial=1">
                                    Start Personal Trial
                                </a>
                                <a class="btn-outline" href="index.php?page=checkout&plan=personal">
                                    Choose Personal Plan
                                </a>
                            <?php endif; ?>
                            <a class="btn-outline" href="index.php?page=login">Sign in to manage membership</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="pricing-card">
                    <h3 class="pricing-title">Flexible plans</h3>
                    <p class="pricing-desc">Promptash supports personal and premium tiers out of the box. Ask your admin for current pricing.</p>
                    <p
                        class="pricing-amount"
                        data-default-mode="monthly"
                        data-monthly-amount="Custom"
                        data-annual-amount="Custom"
                        data-monthly-caption=""
                        data-annual-caption=""
                    >
                        <span class="price-number">Custom</span>
                        <span class="price-caption" style="display:none;"></span>
                    </p>
                    <p
                        class="pricing-note"
                        data-monthly-note="Tailored pricing available."
                        data-annual-note="Tailored pricing available."
                    >
                        Tailored pricing available.
                    </p>
                    <ul class="pricing-limits">
                        <li><i class="fas fa-check-circle"></i> Unlimited collections</li>
                        <li><i class="fas fa-check-circle"></i> Collaborative workspace</li>
                        <li><i class="fas fa-check-circle"></i> Detailed analytics & notifications</li>
                    </ul>
                    <div class="pricing-actions">
                        <a class="btn-primary" href="index.php?page=checkout&plan=personal&trial=1">Create your account</a>
                        <a class="btn-outline" href="index.php?page=login">Sign in</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="cta-footer">
        <h2>Ready to give your prompts a real home?</h2>
        <p>Join creators, agencies, and AI teams using <?php echo htmlspecialchars($appName); ?> to deliver consistent, high-impact content in less time.</p>
        <div class="footer-actions">
            <a class="btn-primary" href="index.php?page=checkout&plan=personal&trial=1"><i class="fas fa-rocket"></i> Start Personal Trial</a>
            <a class="btn-outline" href="index.php?page=checkout&plan=premium"><i class="fas fa-credit-card"></i> Go to Checkout</a>
            <a class="btn-outline" href="index.php?page=login"><i class="fas fa-sign-in-alt"></i> Sign in</a>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-columns">
                <div class="footer-brand">
                    <div class="brand">
                        <span class="brand-icon"><i class="fas fa-magic"></i></span>
                        <span><?php echo htmlspecialchars($appName); ?></span>
                    </div>
                    <p><?php echo htmlspecialchars($appTagline); ?> Purpose-built to organize, collaborate, and scale every prompt you publish.</p>
                </div>

                <div class="footer-column">
                    <h6>Product</h6>
                    <ul class="footer-links">
                        <li><a href="#why">Features</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="index.php?page=login">Customer dashboard</a></li>
                        <li><a href="index.php?page=checkout&plan=personal">Create workspace</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h6>Resources</h6>
                    <ul class="footer-links">
                        <li><a href="README.md" target="_blank">Documentation</a></li>
                        <li><a href="INSTALLATION.md" target="_blank">Deployment guide</a></li>
                        <li><a href="SHARED_HOSTING_COMPLETE_GUIDE.md" target="_blank">Shared hosting setup</a></li>
                        <li><a href="DEPLOYMENT_README.md" target="_blank">Release notes</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h6>Support</h6>
                    <ul class="footer-links">
                        <li><a href="index.php?page=login">Contact support</a></li>
                    <li><a href="index.php?page=checkout&plan=personal">Onboarding</a></li>
                        <li><a href="index.php?page=login">Report an issue</a></li>
                        <li><a href="#pricing">Request a demo</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <span>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($appName); ?>. All rights reserved.</span>
                <div class="footer-bottom-links">
                    <a href="index.php?page=login">Terms</a>
                    <a href="index.php?page=login">Privacy</a>
                    <a href="index.php?page=login">Security</a>
                </div>
            </div>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const billingButtons = document.querySelectorAll('.billing-option');
            let currentMode = 'monthly';

            const updateCards = (mode) => {
                const cards = document.querySelectorAll('.pricing-card');
                cards.forEach((card) => {
                    const amountEl = card.querySelector('.pricing-amount');
                    if (!amountEl) {
                        return;
                    }

                    const defaultMode = amountEl.dataset.defaultMode || 'monthly';
                    const getValue = (suffix) => (amountEl.dataset[suffix] || '').trim();
                    const hasMonthly = getValue('monthlyAmount').length > 0;
                    const hasAnnual = getValue('annualAmount').length > 0;

                    const resolveMode = (preferred) => {
                        if (preferred === 'annual' && hasAnnual) return 'annual';
                        if (preferred === 'monthly' && hasMonthly) return 'monthly';
                        if (defaultMode === 'annual' && hasAnnual) return 'annual';
                        if (defaultMode === 'monthly' && hasMonthly) return 'monthly';
                        return hasAnnual ? 'annual' : 'monthly';
                    };

                    const targetMode = resolveMode(mode);
                    const amount = getValue(`${targetMode}Amount`);
                    const caption = getValue(`${targetMode}Caption`);

                    const numberEl = amountEl.querySelector('.price-number');
                    const captionEl = amountEl.querySelector('.price-caption');
                    if (numberEl) {
                        numberEl.textContent = amount || '';
                    }
                    if (captionEl) {
                        if (caption) {
                            captionEl.textContent = caption;
                            captionEl.style.display = 'inline';
                        } else {
                            captionEl.textContent = '';
                            captionEl.style.display = 'none';
                        }
                    }

                    const noteEl = card.querySelector('.pricing-note');
                    if (noteEl) {
                        const note = (noteEl.dataset[`${targetMode}Note`] || '').trim();
                        if (note) {
                            noteEl.textContent = note;
                            noteEl.style.display = 'block';
                        } else {
                            noteEl.textContent = '';
                            noteEl.style.display = 'none';
                        }
                    }
                });
            };

            billingButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (button.classList.contains('active')) {
                        return;
                    }
                    billingButtons.forEach((btn) => {
                        btn.classList.toggle('active', btn === button);
                        btn.setAttribute('aria-checked', btn === button ? 'true' : 'false');
                    });
                    currentMode = button.dataset.billing === 'annual' ? 'annual' : 'monthly';
                    updateCards(currentMode);
                });
            });

            updateCards(currentMode);
        });
    </script>
</body>
</html>
