<?php
/**
 * Translio Admin Settings
 *
 * Settings page for plugin configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Settings {

    /**
     * Render Settings page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $languages = Translio::get_available_languages();
        $default_language = translio()->get_setting('default_language');
        $secondary_languages = translio()->get_secondary_languages(); // Array of language codes

        // License info
        $license = translio_license();
        $license_info = $license->get_license_info();
        $has_license = !empty($license_info['license_key']);
        $is_valid = $license_info['valid'];

        // For BYOAI mode, check local API key
        $local_api_key = Translio_Admin::decrypt_api_key();
        $has_local_api_key = !empty($local_api_key);

        ?>
        <div class="wrap translio-settings">
            <h1>
                <img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo">
                <?php esc_html_e('Settings', 'translio'); ?>
                <span class="translio-version">v<?php echo esc_html(TRANSLIO_VERSION); ?></span>
            </h1>

            <!-- License Card -->
            <div class="translio-card translio-license-card">
                <div class="translio-license-header">
                    <h2><?php esc_html_e('License', 'translio'); ?></h2>
                    <?php if ($is_valid) : ?>
                        <span class="translio-plan-badge translio-plan-<?php echo esc_attr($license_info['plan']); ?>">
                            <?php echo esc_html($license_info['plan_title']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div id="translio-license-content">
                    <?php if ($has_license && $is_valid) : ?>
                        <!-- Active License Display -->
                        <div class="translio-license-active">
                            <div class="translio-license-status">
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php esc_html_e('License Active', 'translio'); ?>
                            </div>

                            <table class="translio-license-info-table">
                                <tr>
                                    <th><?php esc_html_e('License Key', 'translio'); ?></th>
                                    <td>
                                        <code><?php echo esc_html(substr($license_info['license_key'], 0, 8) . '...' . substr($license_info['license_key'], -4)); ?></code>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Email', 'translio'); ?></th>
                                    <td>
                                        <?php echo esc_html($license_info['email']); ?>
                                        <?php if ($license_info['email_verified']) : ?>
                                            <span class="dashicons dashicons-yes" style="color: #00a32a;" title="<?php esc_attr_e('Verified', 'translio'); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Domain', 'translio'); ?></th>
                                    <td><code><?php echo esc_html($license_info['domain']); ?></code></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Plan', 'translio'); ?></th>
                                    <td><?php echo esc_html($license_info['plan_title']); ?></td>
                                </tr>
                                <?php if ($license_info['is_byoai']) : ?>
                                <tr>
                                    <th><?php esc_html_e('Mode', 'translio'); ?></th>
                                    <td>
                                        <strong><?php esc_html_e('BYOAI', 'translio'); ?></strong>
                                        <span class="description"><?php esc_html_e('(Use your own API key)', 'translio'); ?></span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($license_info['balance'] > 0) : ?>
                                <tr>
                                    <th><?php esc_html_e('Credits', 'translio'); ?></th>
                                    <td>
                                        <strong><?php echo esc_html($license_info['formatted_balance']); ?></strong>
                                        <?php esc_html_e('characters', 'translio'); ?>
                                        <button type="button" class="button-link translio-refresh-credits" title="<?php esc_attr_e('Refresh', 'translio'); ?>">
                                            <span class="dashicons dashicons-update"></span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <?php if (!empty($license_info['credit_packages'])) : ?>
                            <!-- Credit Packages Breakdown -->
                            <div class="translio-credit-packages">
                                <h4><?php esc_html_e('Credit Packages', 'translio'); ?></h4>
                                <table class="translio-packages-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Package', 'translio'); ?></th>
                                            <th><?php esc_html_e('Balance', 'translio'); ?></th>
                                            <th><?php esc_html_e('Expires', 'translio'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($license_info['credit_packages'] as $pkg) :
                                            $pkg = (array) $pkg;
                                            $expires_date = !empty($pkg['expires_at']) ? date_i18n(get_option('date_format'), strtotime($pkg['expires_at'])) : '—';
                                            $days_left = isset($pkg['days_left']) ? (int) $pkg['days_left'] : 0;
                                            $is_expiring_soon = $days_left > 0 && $days_left <= 7;
                                        ?>
                                        <tr class="<?php echo $is_expiring_soon ? 'expiring-soon' : ''; ?>">
                                            <td><?php echo esc_html($pkg['name']); ?></td>
                                            <td><strong><?php echo esc_html($pkg['formatted']); ?></strong></td>
                                            <td>
                                                <?php echo esc_html($expires_date); ?>
                                                <?php if ($days_left > 0) : ?>
                                                    <span class="days-left <?php echo $is_expiring_soon ? 'warning' : ''; ?>">
                                                        (<?php printf(esc_html__('%d days', 'translio'), $days_left); ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <?php if (!$license_info['email_verified']) : ?>
                            <!-- Email Verification Banner -->
                            <div class="translio-verification-banner">
                                <div class="translio-verification-icon">
                                    <span class="dashicons dashicons-email-alt"></span>
                                </div>
                                <div class="translio-verification-content">
                                    <strong><?php esc_html_e('Verify your email to unlock 15,000 bonus credits!', 'translio'); ?></strong>
                                    <p>
                                        <?php
                                        printf(
                                            esc_html__('We sent a verification email to %s. Click the link in the email to verify.', 'translio'),
                                            '<strong>' . esc_html($license_info['email']) . '</strong>'
                                        );
                                        ?>
                                    </p>
                                </div>
                                <div class="translio-verification-actions">
                                    <button type="button" class="button" id="translio-resend-verification">
                                        <?php esc_html_e('Resend Email', 'translio'); ?>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="translio-license-actions">
                                <button type="button" class="button" id="translio-deactivate-license">
                                    <?php esc_html_e('Deactivate License', 'translio'); ?>
                                </button>
                                <a href="https://translio.to/account" target="_blank" class="button">
                                    <?php esc_html_e('Manage Account', 'translio'); ?>
                                </a>
                                <button type="button" class="button translio-refresh-credits" title="<?php esc_attr_e('Refresh license data', 'translio'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                    <?php esc_html_e('Refresh', 'translio'); ?>
                                </button>
                            </div>
                        </div>
                    <?php else : ?>
                        <!-- Welcome Screen / Registration -->
                        <div class="translio-welcome-screen" id="translio-welcome-screen">
                            <div class="translio-welcome-header">
                                <span class="translio-welcome-icon">🎉</span>
                                <h3><?php esc_html_e('Welcome to Translio!', 'translio'); ?></h3>
                            </div>

                            <p class="translio-welcome-text">
                                <?php esc_html_e('Get your free license key to start translating.', 'translio'); ?><br>
                                <strong><?php esc_html_e('Includes 20,000 characters free.', 'translio'); ?></strong>
                            </p>

                            <div class="translio-register-form">
                                <div class="translio-register-input-wrap">
                                    <input type="email" id="translio-register-email" class="regular-text"
                                           placeholder="<?php esc_attr_e('your@email.com', 'translio'); ?>"
                                           autocomplete="email">
                                    <button type="button" class="button button-primary button-hero" id="translio-get-free-license">
                                        <?php esc_html_e('Get Free License Key', 'translio'); ?>
                                    </button>
                                </div>
                                <p class="translio-register-privacy">
                                    <span class="dashicons dashicons-lock"></span>
                                    <?php esc_html_e('We respect your privacy. No spam, ever.', 'translio'); ?>
                                </p>
                            </div>

                            <div class="translio-divider">
                                <span><?php esc_html_e('or', 'translio'); ?></span>
                            </div>

                            <div class="translio-existing-license">
                                <a href="#" id="translio-show-license-form" class="translio-toggle-link">
                                    <?php esc_html_e('Already have a license key? Enter it here', 'translio'); ?>
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </a>
                            </div>
                        </div>

                        <!-- Hidden License Activation Form -->
                        <div class="translio-license-form" id="translio-license-form" style="display: none;">
                            <p class="description">
                                <?php esc_html_e('Enter your license key to activate Translio.', 'translio'); ?>
                            </p>

                            <div class="translio-license-input-wrap">
                                <input type="text" id="translio-license-key" class="regular-text"
                                       placeholder="trl_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                       autocomplete="off">
                                <button type="button" class="button button-primary" id="translio-activate-license">
                                    <?php esc_html_e('Activate', 'translio'); ?>
                                </button>
                            </div>

                            <p class="description">
                                <?php esc_html_e('Domain:', 'translio'); ?>
                                <code><?php echo esc_html($license_info['domain']); ?></code>
                            </p>

                            <p>
                                <a href="#" id="translio-show-register-form" class="translio-toggle-link">
                                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    <?php esc_html_e('Back to free registration', 'translio'); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="translio-license-message" class="translio-message" style="display: none;"></div>
            </div>

            <?php
            // Pro BYOAI Subscription Section - show if user has active BYOAI subscription
            if ($has_license && $is_valid && $license_info['is_byoai']) :
                $expires_date = $license_info['subscription_expires'] ? date_i18n(get_option('date_format'), strtotime($license_info['subscription_expires'])) : '';
                $days_remaining = $license_info['days_remaining'];
                $is_monthly = $license_info['plan'] === 'pro-byoai-monthly';
            ?>
            <div class="translio-card translio-subscription-card">
                <div class="translio-subscription-header">
                    <div class="translio-subscription-badge">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e('Pro BYOAI Active', 'translio'); ?>
                    </div>
                    <div class="translio-subscription-type">
                        <?php echo $is_monthly ? esc_html__('Monthly Subscription', 'translio') : esc_html__('Yearly Subscription', 'translio'); ?>
                    </div>
                </div>

                <div class="translio-subscription-details">
                    <div class="translio-subscription-expiry">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <div>
                            <strong><?php esc_html_e('Subscription Expires', 'translio'); ?></strong>
                            <span class="translio-expiry-date"><?php echo esc_html($expires_date); ?></span>
                            <span class="translio-days-remaining <?php echo $days_remaining < 7 ? 'expiring-soon' : ''; ?>">
                                (<?php printf(esc_html(_n('%d day remaining', '%d days remaining', $days_remaining, 'translio')), $days_remaining); ?>)
                            </span>
                        </div>
                    </div>

                    <?php if ($days_remaining < 7) : ?>
                    <div class="translio-renewal-notice">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Your subscription is expiring soon. Renew to continue using unlimited translations.', 'translio'); ?>
                        <a href="https://api.translio.to/checkout/?add-to-cart=<?php echo $is_monthly ? '75' : '76'; ?>&translio_license=<?php echo esc_attr($license_info['license_key']); ?>"
                           class="button button-primary" target="_blank">
                            <?php esc_html_e('Renew Now', 'translio'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="translio-subscription-features">
                    <h4><?php esc_html_e('Your Pro Features', 'translio'); ?></h4>
                    <ul>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Unlimited translations', 'translio'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Use your own Anthropic API key', 'translio'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Your API key = your rates', 'translio'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Priority support', 'translio'); ?></li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Buy Credits Section - always show for upsell (even for BYOAI users)
            if ($has_license && $is_valid) :
                $packages_data = $license->get_packages();
                $packages = is_wp_error($packages_data) ? array() : ($packages_data['packages'] ?? array());
            ?>
            <div class="translio-card translio-buy-credits-card">
                <h2><?php esc_html_e('Buy More Credits', 'translio'); ?></h2>
                <p class="description" style="margin-bottom: 20px;">
                    <?php esc_html_e('Choose a credit package to translate more content. Credits never expire as long as you have an active license.', 'translio'); ?>
                </p>

                <?php if (!empty($packages)) : ?>
                <div class="translio-packages-grid">
                    <?php foreach ($packages as $package) : ?>
                    <div class="translio-package<?php echo $package['popular'] ? ' translio-package-popular' : ''; ?>">
                        <?php if ($package['popular']) : ?>
                        <div class="translio-package-badge"><?php esc_html_e('Popular', 'translio'); ?></div>
                        <?php endif; ?>
                        <div class="translio-package-credits">
                            <?php
                            // Format credits as 100K, 500K, 1M, etc.
                            $credits = $package['credits'];
                            if ($credits >= 1000000) {
                                echo esc_html(($credits / 1000000) . 'M');
                            } else {
                                echo esc_html(($credits / 1000) . 'K');
                            }
                            ?>
                        </div>
                        <div class="translio-package-name"><?php echo esc_html($package['name']); ?></div>
                        <div class="translio-package-price">
                            $<?php echo esc_html(number_format($package['price'], 2)); ?>
                        </div>
                        <div class="translio-package-validity">
                            <?php printf(esc_html__('Valid for %d days', 'translio'), $package['expires_days']); ?>
                        </div>
                        <button type="button" class="button button-primary translio-buy-package"
                                data-product-id="<?php echo esc_attr($package['product_id']); ?>"
                                data-checkout-url="<?php echo esc_url($package['checkout_url']); ?>">
                            <?php esc_html_e('Buy Now', 'translio'); ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <p class="description">
                    <?php esc_html_e('Unable to load packages. Please try again later.', 'translio'); ?>
                </p>
                <?php endif; ?>

                <!-- Pro BYOAI Option -->
                <div class="translio-pro-byoai-section">
                    <div class="translio-divider">
                        <span><?php esc_html_e('or', 'translio'); ?></span>
                    </div>
                    <div class="translio-pro-byoai-card">
                        <div class="translio-pro-byoai-content">
                            <?php if ($license_info['is_byoai']) : ?>
                                <div class="translio-pro-byoai-badge"><?php esc_html_e('ACTIVE', 'translio'); ?></div>
                                <h3><?php esc_html_e('Unlimited Translations', 'translio'); ?></h3>
                                <p><?php esc_html_e('You have Pro BYOAI subscription. Renew before it expires to continue.', 'translio'); ?></p>
                                <?php if (!empty($license_info['subscription_expires'])) : ?>
                                <div class="translio-pro-byoai-expiry">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php printf(esc_html__('Expires: %s', 'translio'), date_i18n(get_option('date_format'), strtotime($license_info['subscription_expires']))); ?>
                                    <?php if ($license_info['days_remaining'] > 0) : ?>
                                        <span class="days-left">(<?php printf(esc_html(_n('%d day left', '%d days left', $license_info['days_remaining'], 'translio')), $license_info['days_remaining']); ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            <?php else : ?>
                                <div class="translio-pro-byoai-badge">PRO</div>
                                <h3><?php esc_html_e('Unlimited Translations', 'translio'); ?></h3>
                                <p><?php esc_html_e('Use your own Anthropic API key for unlimited translations. Perfect for high-volume sites.', 'translio'); ?></p>
                                <div class="translio-pro-byoai-price">
                                    <span class="price">$12</span>
                                    <span class="period">/<?php esc_html_e('month', 'translio'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="translio-pro-byoai-actions">
                            <?php if ($license_info['is_byoai']) : ?>
                                <button type="button" class="button button-primary translio-buy-package"
                                        data-product-id="75"
                                        data-checkout-url="https://api.translio.to/checkout/?add-to-cart=75">
                                    <?php esc_html_e('Renew Monthly', 'translio'); ?>
                                </button>
                                <button type="button" class="button translio-buy-package"
                                        data-product-id="76"
                                        data-checkout-url="https://api.translio.to/checkout/?add-to-cart=76">
                                    <?php esc_html_e('Renew Yearly (Save 31%)', 'translio'); ?>
                                </button>
                            <?php else : ?>
                                <button type="button" class="button button-primary translio-buy-package"
                                        data-product-id="75"
                                        data-checkout-url="https://api.translio.to/checkout/?add-to-cart=75">
                                    <?php esc_html_e('Subscribe Monthly', 'translio'); ?>
                                </button>
                                <button type="button" class="button translio-buy-package"
                                        data-product-id="76"
                                        data-checkout-url="https://api.translio.to/checkout/?add-to-cart=76">
                                    <?php esc_html_e('Yearly ($99/year - Save 31%)', 'translio'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('translio_settings'); ?>

                <div class="translio-card">
                    <h2><?php esc_html_e('Language Configuration', 'translio'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="translio_default_language"><?php esc_html_e('Default Language', 'translio'); ?></label>
                            </th>
                            <td>
                                <select name="translio_default_language" id="translio_default_language">
                                    <?php foreach ($languages as $code => $data) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($default_language, $code); ?>>
                                            <?php echo esc_html($data['name'] . ' (' . $data['native'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('The main language of your website content.', 'translio'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Translation Languages', 'translio'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('Select up to 4 translation languages', 'translio'); ?></legend>

                                    <?php $selected_count = count($secondary_languages); ?>

                                    <div class="translio-language-checkboxes" id="translio-language-checkboxes">
                                        <?php foreach ($languages as $code => $data) :
                                            if ($code === $default_language) continue; // Skip default
                                            $is_checked = in_array($code, $secondary_languages);
                                            $is_disabled = !$is_checked && $selected_count >= 4;
                                        ?>
                                        <label class="translio-language-checkbox <?php echo $is_disabled ? 'disabled' : ''; ?>">
                                            <input type="checkbox"
                                                   name="translio_secondary_languages[]"
                                                   value="<?php echo esc_attr($code); ?>"
                                                   <?php checked($is_checked); ?>
                                                   <?php disabled($is_disabled && !$is_checked); ?>
                                                   class="translio-lang-checkbox"
                                                   data-lang="<?php echo esc_attr($code); ?>">
                                            <span class="translio-lang-name"><?php echo esc_html($data['name']); ?></span>
                                            <span class="translio-lang-native">(<?php echo esc_html($data['native']); ?>)</span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <p class="description" style="margin-top: 10px;">
                                        <?php esc_html_e('Select up to 4 languages to translate your content into.', 'translio'); ?>
                                        <br>
                                        <strong><span id="translio-selected-count"><?php echo $selected_count; ?></span>/4</strong>
                                        <?php esc_html_e('languages selected', 'translio'); ?>
                                    </p>

                                    <div id="translio-url-prefixes" style="margin-top: 10px;">
                                        <?php if (!empty($secondary_languages)) : ?>
                                            <strong><?php esc_html_e('URL prefixes:', 'translio'); ?></strong>
                                            <?php foreach ($secondary_languages as $lang_code): ?>
                                            <code style="margin-left: 5px;">/<?php echo esc_html($lang_code); ?>/</code>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <style>
                    .translio-language-checkboxes {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                        gap: 8px;
                        max-height: 300px;
                        overflow-y: auto;
                        padding: 10px;
                        border: 1px solid #c3c4c7;
                        border-radius: 4px;
                        background: #f9f9f9;
                    }
                    .translio-language-checkbox {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        padding: 8px 12px;
                        background: #fff;
                        border: 1px solid #dcdcde;
                        border-radius: 4px;
                        cursor: pointer;
                        transition: all 0.2s ease;
                    }
                    .translio-language-checkbox:hover {
                        border-color: #2271b1;
                        background: #f0f6fc;
                    }
                    .translio-language-checkbox.disabled {
                        opacity: 0.5;
                        cursor: not-allowed;
                        background: #f0f0f1;
                    }
                    .translio-language-checkbox.selected {
                        border-color: #2271b1;
                        background: #f0f6fc;
                    }
                    .translio-language-checkbox input[type="checkbox"] {
                        margin: 0;
                    }
                    .translio-lang-name {
                        font-weight: 500;
                    }
                    .translio-lang-native {
                        color: #646970;
                        font-size: 12px;
                    }
                    </style>

                    <script>
                    jQuery(function($) {
                        var maxLanguages = 4;
                        var $checkboxes = $('.translio-lang-checkbox');
                        var $counter = $('#translio-selected-count');
                        var $prefixes = $('#translio-url-prefixes');
                        var $default = $('#translio_default_language');

                        function updateState() {
                            var checked = $checkboxes.filter(':checked').length;
                            $counter.text(checked);

                            // Update disabled state
                            $checkboxes.each(function() {
                                var $cb = $(this);
                                var $label = $cb.closest('.translio-language-checkbox');

                                // Update selected class
                                if ($cb.is(':checked')) {
                                    $label.addClass('selected');
                                } else {
                                    $label.removeClass('selected');
                                }

                                // Disable unchecked if at limit
                                if (!$cb.is(':checked') && checked >= maxLanguages) {
                                    $cb.prop('disabled', true);
                                    $label.addClass('disabled');
                                } else {
                                    $cb.prop('disabled', false);
                                    $label.removeClass('disabled');
                                }
                            });

                            // Update URL prefixes display
                            var prefixes = [];
                            $checkboxes.filter(':checked').each(function() {
                                prefixes.push('<code style="margin-left: 5px;">/' + $(this).data('lang') + '/</code>');
                            });
                            if (prefixes.length > 0) {
                                $prefixes.html('<strong><?php echo esc_js(__('URL prefixes:', 'translio')); ?></strong> ' + prefixes.join(''));
                            } else {
                                $prefixes.html('');
                            }
                        }

                        function hideDefaultLanguage() {
                            var defaultVal = $default.val();
                            $checkboxes.each(function() {
                                var $cb = $(this);
                                var $label = $cb.closest('.translio-language-checkbox');
                                if ($cb.data('lang') === defaultVal) {
                                    $cb.prop('checked', false);
                                    $label.hide();
                                } else {
                                    $label.show();
                                }
                            });
                            updateState();
                        }

                        $checkboxes.on('change', updateState);
                        $default.on('change', hideDefaultLanguage);

                        hideDefaultLanguage();
                    });
                    </script>
                </div>

                <!-- BYOAI API Key Section (only shown for Pro BYOAI users) -->
                <?php if ($license_info['is_byoai']) : ?>
                <div class="translio-card">
                    <h2><?php esc_html_e('Anthropic API Key (BYOAI)', 'translio'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('As a Pro BYOAI subscriber, you use your own Anthropic API key for translations.', 'translio'); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="translio_api_key"><?php esc_html_e('API Key', 'translio'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="translio_api_key" id="translio_api_key"
                                       value=""
                                       class="regular-text" autocomplete="off"
                                       placeholder="<?php echo $has_local_api_key ? esc_attr__('Key saved. Enter new key to replace.', 'translio') : 'sk-ant-...'; ?>">
                                <?php if ($has_local_api_key) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a; margin-left: 5px; line-height: 30px;"></span>
                                <span class="translio-api-status translio-api-connected">
                                    <?php esc_html_e('API key configured', 'translio'); ?>
                                </span>
                                <?php endif; ?>
                                <p class="description">
                                    <?php esc_html_e('Your Anthropic API key for translations.', 'translio'); ?>
                                    <a href="https://console.anthropic.com/settings/keys" target="_blank"><?php esc_html_e('Get API key', 'translio'); ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <?php
                // Language Switcher Settings
                $switcher_settings = get_option('translio_switcher_settings', array());
                $menu_locations = get_registered_nav_menus();
                ?>
                <div class="translio-card">
                    <h2><?php esc_html_e('Language Switcher', 'translio'); ?></h2>
                    <p class="description" style="margin-bottom: 15px;">
                        <?php esc_html_e('Configure how the language switcher appears on your website.', 'translio'); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Add to Navigation Menu', 'translio'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="translio_switcher_settings[enable_menu]" value="1"
                                        <?php checked(!empty($switcher_settings['enable_menu'])); ?>>
                                    <?php esc_html_e('Enable menu integration', 'translio'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Automatically add language switcher to your navigation menu.', 'translio'); ?></p>
                            </td>
                        </tr>
                        <tr class="translio-switcher-menu-options" <?php echo empty($switcher_settings['enable_menu']) ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="translio_menu_location"><?php esc_html_e('Menu Location', 'translio'); ?></label>
                            </th>
                            <td>
                                <select name="translio_switcher_settings[menu_location]" id="translio_menu_location">
                                    <?php foreach ($menu_locations as $location => $description) : ?>
                                        <option value="<?php echo esc_attr($location); ?>"
                                            <?php selected(isset($switcher_settings['menu_location']) ? $switcher_settings['menu_location'] : 'primary', $location); ?>>
                                            <?php echo esc_html($description); ?> (<?php echo esc_html($location); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($menu_locations)) : ?>
                                        <option value=""><?php esc_html_e('No menu locations registered', 'translio'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="translio-switcher-menu-options" <?php echo empty($switcher_settings['enable_menu']) ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="translio_menu_style"><?php esc_html_e('Menu Style', 'translio'); ?></label>
                            </th>
                            <td>
                                <select name="translio_switcher_settings[menu_style]" id="translio_menu_style">
                                    <option value="dropdown" <?php selected(isset($switcher_settings['menu_style']) ? $switcher_settings['menu_style'] : 'dropdown', 'dropdown'); ?>>
                                        <?php esc_html_e('Dropdown (single item with submenu)', 'translio'); ?>
                                    </option>
                                    <option value="inline" <?php selected(isset($switcher_settings['menu_style']) ? $switcher_settings['menu_style'] : '', 'inline'); ?>>
                                        <?php esc_html_e('Inline (separate menu items)', 'translio'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Display Options', 'translio'); ?></th>
                            <td>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="translio_switcher_settings[show_flags]" value="1"
                                        <?php checked(!isset($switcher_settings['show_flags']) || $switcher_settings['show_flags']); ?>>
                                    <?php esc_html_e('Show country flags', 'translio'); ?>
                                </label>
                                <label style="display: block;">
                                    <input type="checkbox" name="translio_switcher_settings[show_names]" value="1"
                                        <?php checked(!isset($switcher_settings['show_names']) || $switcher_settings['show_names']); ?>>
                                    <?php esc_html_e('Show language names', 'translio'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Shortcode', 'translio'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #f0f0f0; display: inline-block;">[translio_switcher]</code>
                                <p class="description" style="margin-top: 8px;">
                                    <?php esc_html_e('Use this shortcode to place the language switcher anywhere on your site.', 'translio'); ?>
                                    <br><strong><?php esc_html_e('Parameters:', 'translio'); ?></strong>
                                    <br><code>style="dropdown|inline|flags"</code> - <?php esc_html_e('Display style', 'translio'); ?>
                                    <br><code>show_flags="yes|no"</code> - <?php esc_html_e('Show flags', 'translio'); ?>
                                    <br><code>show_names="yes|no"</code> - <?php esc_html_e('Show language names', 'translio'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Widget', 'translio'); ?></th>
                            <td>
                                <p class="description">
                                    <?php
                                    printf(
                                        esc_html__('Add the "Translio Language Switcher" widget from %sAppearance → Widgets%s.', 'translio'),
                                        '<a href="' . esc_url(admin_url('widgets.php')) . '">',
                                        '</a>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <script>
                jQuery(function($) {
                    $('input[name="translio_switcher_settings[enable_menu]"]').on('change', function() {
                        $('.translio-switcher-menu-options').toggle(this.checked);
                    });
                });
                </script>

                <?php submit_button(__('Save Settings', 'translio')); ?>
            </form>
        </div>

        <!-- License AJAX Scripts -->
        <script>
        jQuery(function($) {
            var $licenseKey = $('#translio-license-key');
            var $activateBtn = $('#translio-activate-license');
            var $deactivateBtn = $('#translio-deactivate-license');
            var $refreshBtn = $('.translio-refresh-credits');
            var $message = $('#translio-license-message');

            // Welcome/Register form elements
            var $welcomeScreen = $('#translio-welcome-screen');
            var $licenseForm = $('#translio-license-form');
            var $registerEmail = $('#translio-register-email');
            var $getFreeBtn = $('#translio-get-free-license');

            function showMessage(text, type) {
                $message
                    .removeClass('translio-message-success translio-message-error')
                    .addClass('translio-message-' + type)
                    .html(text)
                    .show();
            }

            // Toggle between welcome screen and license form
            $('#translio-show-license-form').on('click', function(e) {
                e.preventDefault();
                $welcomeScreen.slideUp(200, function() {
                    $licenseForm.slideDown(200);
                    $licenseKey.focus();
                });
            });

            $('#translio-show-register-form').on('click', function(e) {
                e.preventDefault();
                $licenseForm.slideUp(200, function() {
                    $welcomeScreen.slideDown(200);
                    $registerEmail.focus();
                });
            });

            // Get Free License (Registration)
            $getFreeBtn.on('click', function() {
                var email = $registerEmail.val().trim();

                if (!email) {
                    showMessage('<?php echo esc_js(__('Please enter your email address', 'translio')); ?>', 'error');
                    $registerEmail.focus();
                    return;
                }

                // Basic email validation
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showMessage('<?php echo esc_js(__('Please enter a valid email address', 'translio')); ?>', 'error');
                    $registerEmail.focus();
                    return;
                }

                $getFreeBtn.prop('disabled', true).text('<?php echo esc_js(__('Creating license...', 'translio')); ?>');
                $message.hide();

                $.post(ajaxurl, {
                    action: 'translio_register_free',
                    nonce: '<?php echo wp_create_nonce('translio_license_nonce'); ?>',
                    email: email
                }, function(response) {
                    if (response.success) {
                        showMessage(
                            '<strong><?php echo esc_js(__('Success!', 'translio')); ?></strong> ' +
                            response.data.message +
                            '<br><small><?php echo esc_js(__('Activating your license...', 'translio')); ?></small>',
                            'success'
                        );
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage(response.data.message, 'error');
                        $getFreeBtn.prop('disabled', false).text('<?php echo esc_js(__('Get Free License Key', 'translio')); ?>');
                    }
                }).fail(function() {
                    showMessage('<?php echo esc_js(__('Connection error. Please try again.', 'translio')); ?>', 'error');
                    $getFreeBtn.prop('disabled', false).text('<?php echo esc_js(__('Get Free License Key', 'translio')); ?>');
                });
            });

            // Enter key in email input
            $registerEmail.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $getFreeBtn.click();
                }
            });

            // Activate License
            $activateBtn.on('click', function() {
                var key = $licenseKey.val().trim();
                if (!key) {
                    showMessage('<?php echo esc_js(__('Please enter a license key', 'translio')); ?>', 'error');
                    return;
                }

                $activateBtn.prop('disabled', true).text('<?php echo esc_js(__('Activating...', 'translio')); ?>');

                $.post(ajaxurl, {
                    action: 'translio_activate_license',
                    nonce: '<?php echo wp_create_nonce('translio_license_nonce'); ?>',
                    license_key: key
                }, function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showMessage(response.data.message, 'error');
                        $activateBtn.prop('disabled', false).text('<?php echo esc_js(__('Activate', 'translio')); ?>');
                    }
                }).fail(function() {
                    showMessage('<?php echo esc_js(__('Connection error. Please try again.', 'translio')); ?>', 'error');
                    $activateBtn.prop('disabled', false).text('<?php echo esc_js(__('Activate', 'translio')); ?>');
                });
            });

            // Deactivate License
            $deactivateBtn.on('click', function() {
                if (!confirm('<?php echo esc_js(__('Are you sure you want to deactivate this license?', 'translio')); ?>')) {
                    return;
                }

                $deactivateBtn.prop('disabled', true).text('<?php echo esc_js(__('Deactivating...', 'translio')); ?>');

                $.post(ajaxurl, {
                    action: 'translio_deactivate_license',
                    nonce: '<?php echo wp_create_nonce('translio_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showMessage(response.data.message, 'error');
                        $deactivateBtn.prop('disabled', false).text('<?php echo esc_js(__('Deactivate License', 'translio')); ?>');
                    }
                }).fail(function() {
                    showMessage('<?php echo esc_js(__('Connection error. Please try again.', 'translio')); ?>', 'error');
                    $deactivateBtn.prop('disabled', false).text('<?php echo esc_js(__('Deactivate License', 'translio')); ?>');
                });
            });

            // Refresh Credits
            $refreshBtn.on('click', function() {
                var $btn = $(this);
                $btn.find('.dashicons').addClass('spin');

                $.post(ajaxurl, {
                    action: 'translio_refresh_license',
                    nonce: '<?php echo wp_create_nonce('translio_license_nonce'); ?>'
                }, function(response) {
                    $btn.find('.dashicons').removeClass('spin');
                    if (response.success) {
                        location.reload();
                    }
                }).fail(function() {
                    $btn.find('.dashicons').removeClass('spin');
                });
            });

            // Resend Verification Email
            $(document).on('click', '#translio-resend-verification', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'translio')); ?>');

                $.post(ajaxurl, {
                    action: 'translio_resend_verification',
                    nonce: '<?php echo wp_create_nonce('translio_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        $btn.text('<?php echo esc_js(__('Email Sent!', 'translio')); ?>');
                        setTimeout(function() {
                            $btn.prop('disabled', false).text(originalText);
                        }, 5000);
                    } else {
                        showMessage(response.data.message, 'error');
                        $btn.prop('disabled', false).text(originalText);
                    }
                }).fail(function() {
                    showMessage('<?php echo esc_js(__('Connection error. Please try again.', 'translio')); ?>', 'error');
                    $btn.prop('disabled', false).text(originalText);
                });
            });

            // Enter key in license input
            $licenseKey.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $activateBtn.click();
                }
            });

            // Buy package buttons - redirect to checkout with license key (event delegation)
            $(document).on('click', '.translio-buy-package', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var checkoutUrl = $btn.data('checkout-url');
                var licenseKey = '<?php echo esc_js($license_info['license_key'] ?? ''); ?>';

                // Append license key to checkout URL
                if (licenseKey) {
                    checkoutUrl += (checkoutUrl.indexOf('?') > -1 ? '&' : '?') + 'translio_license=' + encodeURIComponent(licenseKey);
                }

                // Open checkout in new tab
                window.open(checkoutUrl, '_blank');
            });
        });
        </script>

        <style>
        /* Credit Packages Styles */
        .translio-credit-packages {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #dcdcde;
        }
        .translio-credit-packages h4 {
            margin: 0 0 10px;
            font-size: 13px;
            color: #50575e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .translio-packages-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .translio-packages-table th {
            text-align: left;
            padding: 8px 10px;
            background: #f6f7f7;
            border-bottom: 1px solid #dcdcde;
            font-weight: 600;
            color: #50575e;
        }
        .translio-packages-table td {
            padding: 10px;
            border-bottom: 1px solid #f0f0f1;
        }
        .translio-packages-table tr:last-child td {
            border-bottom: none;
        }
        .translio-packages-table tr.expiring-soon td {
            background: #fcf9e8;
        }
        .translio-packages-table .days-left {
            color: #50575e;
            font-size: 12px;
        }
        .translio-packages-table .days-left.warning {
            color: #b32d2e;
            font-weight: 600;
        }

        /* Welcome Screen Styles */
        .translio-welcome-screen {
            text-align: center;
            padding: 20px 0;
        }
        .translio-welcome-header {
            margin-bottom: 15px;
        }
        .translio-welcome-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        .translio-welcome-header h3 {
            margin: 0;
            font-size: 24px;
            color: #1d2327;
        }
        .translio-welcome-text {
            font-size: 15px;
            color: #50575e;
            margin-bottom: 25px;
        }
        .translio-register-form {
            max-width: 500px;
            margin: 0 auto;
        }
        .translio-register-input-wrap {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .translio-register-input-wrap input {
            flex: 1;
            min-width: 250px;
            max-width: 300px;
            padding: 8px 12px;
            font-size: 15px;
        }
        .translio-register-input-wrap .button-hero {
            padding: 8px 24px !important;
            font-size: 14px !important;
            height: auto !important;
            line-height: 1.5 !important;
        }
        .translio-register-privacy {
            font-size: 12px;
            color: #888;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .translio-register-privacy .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        .translio-divider {
            position: relative;
            margin: 25px 0;
            text-align: center;
        }
        .translio-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            border-top: 1px solid #dcdcde;
        }
        .translio-divider span {
            position: relative;
            background: #fff;
            padding: 0 15px;
            color: #888;
            font-size: 13px;
            text-transform: uppercase;
        }
        .translio-existing-license {
            margin-top: 10px;
        }
        .translio-toggle-link {
            color: #2271b1;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .translio-toggle-link:hover {
            color: #135e96;
        }
        .translio-toggle-link .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .translio-license-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
        }
        .translio-license-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .translio-license-header h2 {
            margin: 0;
        }
        .translio-plan-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .translio-plan-free {
            background: #e5e7eb;
            color: #374151;
        }
        .translio-plan-pro-byoai-monthly,
        .translio-plan-pro-byoai-yearly {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .translio-license-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 500;
            color: #00a32a;
            margin-bottom: 15px;
        }
        .translio-license-info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .translio-license-info-table th {
            text-align: left;
            padding: 8px 0;
            width: 120px;
            font-weight: 500;
            color: #666;
        }
        .translio-license-info-table td {
            padding: 8px 0;
        }

        /* Email Verification Banner */
        .translio-verification-banner {
            display: flex;
            align-items: center;
            gap: 15px;
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            border: 1px solid #ffca28;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .translio-verification-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: #ff9800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .translio-verification-icon .dashicons {
            color: #fff;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .translio-verification-content {
            flex: 1;
        }
        .translio-verification-content strong {
            display: block;
            color: #e65100;
            margin-bottom: 4px;
        }
        .translio-verification-content p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        .translio-verification-actions {
            flex-shrink: 0;
        }

        .translio-license-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .translio-license-input-wrap {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        .translio-license-input-wrap input {
            flex: 1;
            max-width: 400px;
        }
        .translio-message {
            padding: 12px 15px;
            border-radius: 4px;
            margin-top: 15px;
        }
        .translio-message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .translio-message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .translio-refresh-credits {
            color: #666;
            text-decoration: none;
        }
        .translio-refresh-credits:hover {
            color: #0073aa;
        }
        .translio-refresh-credits .dashicons.spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        /* Buy Credits Packages */
        .translio-buy-credits-card {
            background: linear-gradient(135deg, #f0f7ff 0%, #fff 100%);
        }
        .translio-packages-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 15px;
        }
        @media (max-width: 960px) {
            .translio-packages-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 600px) {
            .translio-packages-grid {
                grid-template-columns: 1fr;
            }
        }
        .translio-package {
            border-radius: 12px;
            padding: 25px 20px;
            text-align: center;
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(30, 58, 95, 0.2);
        }
        .translio-package:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(30, 58, 95, 0.3);
        }
        .translio-package-popular {
            background: linear-gradient(135deg, #2271b1 0%, #1a5a8c 100%);
            box-shadow: 0 4px 20px rgba(34, 113, 177, 0.3);
        }
        .translio-package-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255,255,255,0.95);
            color: #2271b1;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .translio-package-credits {
            font-size: 42px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
            margin-bottom: 5px;
        }
        .translio-package-name {
            font-size: 14px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 15px;
        }
        .translio-package-price {
            font-size: 28px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 5px;
        }
        .translio-package-validity {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            margin-bottom: 20px;
        }
        .translio-package .button {
            width: 100%;
            padding: 10px 20px !important;
            height: auto !important;
            background: rgba(255,255,255,0.95) !important;
            color: #1e3a5f !important;
            border: none !important;
            font-weight: 600 !important;
        }
        .translio-package .button:hover {
            background: #fff !important;
            color: #1e3a5f !important;
        }
        .translio-package-popular .button {
            color: #2271b1 !important;
        }

        /* Pro BYOAI Section */
        .translio-pro-byoai-section {
            margin-top: 30px;
        }
        .translio-pro-byoai-section .translio-divider {
            margin: 20px 0;
        }
        .translio-pro-byoai-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 25px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        .translio-pro-byoai-content {
            flex: 1;
            min-width: 250px;
            color: #fff;
        }
        .translio-pro-byoai-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .translio-pro-byoai-content h3 {
            margin: 0 0 8px 0;
            font-size: 22px;
            color: #fff;
        }
        .translio-pro-byoai-content p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .translio-pro-byoai-price {
            margin-top: 15px;
        }
        .translio-pro-byoai-price .price {
            font-size: 36px;
            font-weight: 700;
        }
        .translio-pro-byoai-price .period {
            font-size: 16px;
            opacity: 0.8;
        }
        .translio-pro-byoai-expiry {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-size: 14px;
            opacity: 0.9;
        }
        .translio-pro-byoai-expiry .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .translio-pro-byoai-expiry .days-left {
            opacity: 0.8;
        }
        .translio-pro-byoai-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .translio-pro-byoai-actions .button {
            min-width: 220px;
            text-align: center;
            padding: 10px 20px !important;
            height: auto !important;
        }
        .translio-pro-byoai-actions .button-primary {
            background: #fff !important;
            color: #764ba2 !important;
            border-color: #fff !important;
        }
        .translio-pro-byoai-actions .button-primary:hover {
            background: #f0f0f0 !important;
        }
        .translio-pro-byoai-actions .button:not(.button-primary) {
            background: transparent !important;
            color: #fff !important;
            border-color: rgba(255,255,255,0.5) !important;
        }
        .translio-pro-byoai-actions .button:not(.button-primary):hover {
            border-color: #fff !important;
            background: rgba(255,255,255,0.1) !important;
        }

        /* Pro BYOAI Subscription Card */
        .translio-settings .translio-subscription-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #fff !important;
            border: none !important;
        }
        .translio-subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .translio-subscription-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
        }
        .translio-subscription-badge .dashicons {
            color: #ffd700;
        }
        .translio-subscription-type {
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .translio-subscription-details {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .translio-subscription-expiry {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .translio-subscription-expiry .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            margin-top: 2px;
        }
        .translio-subscription-expiry strong {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
            margin-bottom: 4px;
        }
        .translio-expiry-date {
            font-size: 18px;
            font-weight: 600;
        }
        .translio-days-remaining {
            display: block;
            font-size: 14px;
            opacity: 0.9;
            margin-top: 4px;
        }
        .translio-days-remaining.expiring-soon {
            color: #ffcc00;
            font-weight: 600;
        }
        .translio-renewal-notice {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,204,0,0.2);
            border: 1px solid rgba(255,204,0,0.5);
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .translio-renewal-notice .dashicons {
            color: #ffcc00;
        }
        .translio-renewal-notice .button {
            margin-left: auto;
            background: #fff !important;
            color: #764ba2 !important;
            border: none !important;
        }
        .translio-subscription-features {
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 20px;
        }
        .translio-subscription-features h4 {
            margin: 0 0 15px 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }
        .translio-subscription-features ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        @media (max-width: 600px) {
            .translio-subscription-features ul {
                grid-template-columns: 1fr;
            }
        }
        .translio-subscription-features li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .translio-subscription-features .dashicons-yes {
            color: #90EE90;
        }
        </style>
        <?php
    }
}
