<?php
/**
 * Translio Admin Contact Form 7
 *
 * Contact Form 7 forms list and translation editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_CF7 {

    /**
     * Render CF7 forms list
     */
    public function render_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!class_exists('WPCF7')) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Contact Form 7', 'translio'); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Contact Form 7 is not installed or activated.', 'translio'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Contact Form 7', 'translio'); ?></h1>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('Please configure your translation language in', 'translio'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=translio-settings')); ?>">
                            <?php esc_html_e('Settings', 'translio'); ?>
                        </a>.
                    </p>
                </div>
            </div>
            <?php
            return;
        }

        // Get all CF7 forms
        $args = array(
            'post_type' => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $query = new WP_Query($args);

        ?>
        <div class="wrap">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Contact Form 7', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio-cf7'); ?>

            <div class="translio-translate-header">
                <div class="translio-lang-indicator">
                    <span class="translio-lang-badge">
                        <?php echo esc_html(strtoupper($default_language)); ?>
                    </span>
                    →
                    <span class="translio-lang-badge translio-lang-secondary">
                        <?php echo esc_html(strtoupper($secondary_language)); ?>
                    </span>
                </div>
            </div>

            <p class="description">
                <?php esc_html_e('Translate Contact Form 7 forms including form fields, email templates, and messages.', 'translio'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="width: 40%;"><?php esc_html_e('Form Title', 'translio'); ?></th>
                        <th><?php esc_html_e('Shortcode', 'translio'); ?></th>
                        <th><?php esc_html_e('Translation Status', 'translio'); ?></th>
                        <th><?php esc_html_e('Actions', 'translio'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()) : ?>
                        <?php while ($query->have_posts()) : $query->the_post();
                            $form_id = get_the_ID();

                            // Count translatable fields
                            $total_fields = 0;
                            $translated_fields = 0;

                            // Title
                            $total_fields++;
                            $title_trans = Translio_DB::get_translation($form_id, 'cf7_form', 'title', $secondary_language);
                            if (!empty($title_trans->translated_content)) {
                                $translated_fields++;
                            }

                            // Form content
                            $cf7_form = null;
                            if (class_exists('WPCF7_ContactForm')) {
                                $cf7_form = WPCF7_ContactForm::get_instance($form_id);
                            }

                            if ($cf7_form) {
                                $form_content = $cf7_form->prop('form');
                                if (!empty($form_content)) {
                                    $total_fields++;
                                    $form_trans = Translio_DB::get_translation($form_id, 'cf7_form', 'form', $secondary_language);
                                    if (!empty($form_trans->translated_content)) {
                                        $translated_fields++;
                                    }
                                }

                                // Mail fields
                                $mail = $cf7_form->prop('mail');
                                if (!empty($mail['subject'])) {
                                    $total_fields++;
                                    $subject_trans = Translio_DB::get_translation($form_id, 'cf7_mail', 'subject', $secondary_language);
                                    if (!empty($subject_trans->translated_content)) {
                                        $translated_fields++;
                                    }
                                }
                                if (!empty($mail['body'])) {
                                    $total_fields++;
                                    $body_trans = Translio_DB::get_translation($form_id, 'cf7_mail', 'body', $secondary_language);
                                    if (!empty($body_trans->translated_content)) {
                                        $translated_fields++;
                                    }
                                }

                                // Messages
                                $messages = $cf7_form->prop('messages');
                                if (!empty($messages)) {
                                    foreach ($messages as $key => $message) {
                                        if (!empty($message)) {
                                            $total_fields++;
                                            $msg_trans = Translio_DB::get_translation($form_id, 'cf7_message', $key, $secondary_language);
                                            if (!empty($msg_trans->translated_content)) {
                                                $translated_fields++;
                                            }
                                        }
                                    }
                                }
                            }

                            $is_translated = $translated_fields === $total_fields;
                            $status_class = $is_translated ? 'translio-status-ok' : ($translated_fields > 0 ? 'translio-status-partial' : 'translio-status-missing');
                            $status_text = sprintf(__('%d/%d translated', 'translio'), $translated_fields, $total_fields);
                        ?>
                        <tr>
                            <td><strong><?php the_title(); ?></strong></td>
                            <td>
                                <code>[contact-form-7 id="<?php echo esc_attr($form_id); ?>"]</code>
                            </td>
                            <td>
                                <span class="translio-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-translate-cf7&form_id=' . $form_id)); ?>"
                                   class="button button-small">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4">
                                <p style="padding: 20px; text-align: center;">
                                    <?php esc_html_e('No Contact Form 7 forms found.', 'translio'); ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpcf7-new')); ?>">
                                        <?php esc_html_e('Create a form', 'translio'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        wp_reset_postdata();
    }

    /**
     * Render Translate CF7 form page
     */
    public function render_translate_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;

        if (!$form_id) {
            wp_die(__('Invalid form ID', 'translio'));
        }

        $form = get_post($form_id);
        if (!$form || $form->post_type !== 'wpcf7_contact_form') {
            wp_die(__('Form not found', 'translio'));
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();
        $languages = Translio::get_available_languages();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translate Contact Form', 'translio'); ?></h1>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('Please configure your translation language in', 'translio'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=translio-settings')); ?>">
                            <?php esc_html_e('Settings', 'translio'); ?>
                        </a>.
                    </p>
                </div>
            </div>
            <?php
            return;
        }

        // Get CF7 form data
        $cf7_form = null;
        $form_content = '';
        $mail = array();
        $messages = array();

        if (class_exists('WPCF7_ContactForm')) {
            $cf7_form = WPCF7_ContactForm::get_instance($form_id);
            if ($cf7_form) {
                $form_content = $cf7_form->prop('form');
                $mail = $cf7_form->prop('mail');
                $messages = $cf7_form->prop('messages');
            }
        }

        // Fallback to meta fields
        if (empty($form_content)) {
            $form_content = get_post_meta($form_id, '_form', true);
        }
        if (empty($mail) || !is_array($mail)) {
            $mail = get_post_meta($form_id, '_mail', true);
            if (!is_array($mail)) $mail = array();
        }
        if (empty($messages) || !is_array($messages)) {
            $messages = get_post_meta($form_id, '_messages', true);
            if (!is_array($messages)) $messages = array();
        }

        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();

        ?>
        <div class="wrap translio-translate">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-cf7')); ?>" class="translio-back">
                    ← <?php esc_html_e('Back to CF7 Forms', 'translio'); ?>
                </a>
                <?php esc_html_e('Translate Form:', 'translio'); ?> <?php echo esc_html($form->post_title); ?>
            </h1>

            <?php Translio_Admin::render_language_selector('translio-translate-cf7'); ?>

            <div class="translio-translate-header">
                <div class="translio-lang-indicator">
                    <span class="translio-lang-badge">
                        <?php echo esc_html(strtoupper($default_language)); ?>
                    </span>
                    →
                    <span class="translio-lang-badge translio-lang-secondary">
                        <?php echo esc_html(strtoupper($secondary_language)); ?>
                    </span>
                </div>

                <?php if ($can_translate) : ?>
                <button type="button" class="button button-primary" id="translio-translate-cf7-all"
                        data-form-id="<?php echo esc_attr($form_id); ?>">
                    <?php esc_html_e('Auto-translate all fields', 'translio'); ?>
                </button>
                <?php endif; ?>
            </div>

            <div class="translio-editor" data-form-id="<?php echo esc_attr($form_id); ?>">

                <!-- Form Title -->
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Form Title', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($form->post_title); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-cf7-field"
                                        data-field="title"
                                        data-original="<?php echo esc_attr($form->post_title); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <?php
                                $title_trans = Translio_DB::get_translation($form_id, 'cf7_form', 'title', $secondary_language);
                                ?>
                                <input type="text"
                                       class="translio-input translio-cf7-input"
                                       data-field="title"
                                       data-object-type="cf7_form"
                                       value="<?php echo esc_attr($title_trans ? $title_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Content -->
                <?php if (!empty($form_content)) : ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Form Template', 'translio'); ?></h3>
                        <small class="translio-text-muted">
                            <?php esc_html_e('Translate labels and placeholders. Keep CF7 shortcodes intact.', 'translio'); ?>
                        </small>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <pre style="background: #f5f5f5; padding: 10px; overflow: auto;"><?php echo esc_html($form_content); ?></pre>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-cf7-field"
                                        data-field="form"
                                        data-original="<?php echo esc_attr($form_content); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <?php
                                $form_trans = Translio_DB::get_translation($form_id, 'cf7_form', 'form', $secondary_language);
                                ?>
                                <textarea class="translio-textarea translio-cf7-input"
                                          data-field="form"
                                          data-object-type="cf7_form"
                                          rows="10"><?php echo esc_textarea($form_trans ? $form_trans->translated_content : ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mail Subject -->
                <?php if (!empty($mail['subject'])) : ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Email Subject', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($mail['subject']); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-cf7-field"
                                        data-field="mail_subject"
                                        data-original="<?php echo esc_attr($mail['subject']); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <?php
                                $subject_trans = Translio_DB::get_translation($form_id, 'cf7_mail', 'subject', $secondary_language);
                                ?>
                                <input type="text"
                                       class="translio-input translio-cf7-input"
                                       data-field="mail_subject"
                                       data-object-type="cf7_mail"
                                       value="<?php echo esc_attr($subject_trans ? $subject_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mail Body -->
                <?php if (!empty($mail['body'])) : ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Email Body', 'translio'); ?></h3>
                        <small class="translio-text-muted">
                            <?php esc_html_e('Keep placeholders like [your-name] intact.', 'translio'); ?>
                        </small>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <pre style="background: #f5f5f5; padding: 10px; overflow: auto;"><?php echo esc_html($mail['body']); ?></pre>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-cf7-field"
                                        data-field="mail_body"
                                        data-original="<?php echo esc_attr($mail['body']); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <?php
                                $body_trans = Translio_DB::get_translation($form_id, 'cf7_mail', 'body', $secondary_language);
                                ?>
                                <textarea class="translio-textarea translio-cf7-input"
                                          data-field="mail_body"
                                          data-object-type="cf7_mail"
                                          rows="8"><?php echo esc_textarea($body_trans ? $body_trans->translated_content : ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Messages -->
                <?php if (!empty($messages)) : ?>
                <h2 style="margin-top: 30px;"><?php esc_html_e('Form Messages', 'translio'); ?></h2>

                <?php
                $message_labels = array(
                    'mail_sent_ok' => __('Message sent successfully', 'translio'),
                    'mail_sent_ng' => __('Message failed to send', 'translio'),
                    'validation_error' => __('Validation errors', 'translio'),
                    'spam' => __('Spam detected', 'translio'),
                    'accept_terms' => __('Accept terms', 'translio'),
                    'invalid_required' => __('Required field', 'translio'),
                    'invalid_email' => __('Invalid email', 'translio'),
                    'invalid_url' => __('Invalid URL', 'translio'),
                    'invalid_tel' => __('Invalid phone', 'translio'),
                    'invalid_number' => __('Invalid number', 'translio'),
                    'invalid_date' => __('Invalid date', 'translio'),
                );

                foreach ($messages as $key => $message) :
                    if (empty($message)) continue;

                    $label = isset($message_labels[$key]) ? $message_labels[$key] : ucwords(str_replace('_', ' ', $key));
                ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php echo esc_html($label); ?></h3>
                        <small class="translio-text-muted"><?php echo esc_html($key); ?></small>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($message); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-cf7-field"
                                        data-field="message_<?php echo esc_attr($key); ?>"
                                        data-original="<?php echo esc_attr($message); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <?php
                                $msg_trans = Translio_DB::get_translation($form_id, 'cf7_message', $key, $secondary_language);
                                ?>
                                <input type="text"
                                       class="translio-input translio-cf7-input"
                                       data-field="message_<?php echo esc_attr($key); ?>"
                                       data-object-type="cf7_message"
                                       value="<?php echo esc_attr($msg_trans ? $msg_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }
}
