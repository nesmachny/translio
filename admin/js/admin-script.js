/**
 * Translio Admin JavaScript
 * Modular architecture with page-specific handlers
 */

(function($) {
    'use strict';

    // ========================================
    // CORE MODULE
    // ========================================
    var Translio = {
        saveTimer: null,
        savingFields: {},
        $progressBar: null,
        currentPage: null,
        currentLanguage: null,

        init: function() {
            this.detectPage();
            this.detectLanguage();
            this.createProgressBar();
            this.bindCoreEvents();
            this.initAutosave();
            this.initPage();
        },

        detectPage: function() {
            var urlParams = new URLSearchParams(window.location.search);
            this.currentPage = urlParams.get('page') || '';
        },

        detectLanguage: function() {
            var urlParams = new URLSearchParams(window.location.search);
            this.currentLanguage = urlParams.get('translio_lang') || '';
        },

        /**
         * Get common AJAX data with language_code included
         */
        getAjaxData: function(additionalData) {
            var data = {
                nonce: translioAdmin.nonce
            };

            // Add language_code if we have one
            if (this.currentLanguage) {
                data.language_code = this.currentLanguage;
            }

            // Merge additional data
            if (additionalData) {
                for (var key in additionalData) {
                    if (additionalData.hasOwnProperty(key)) {
                        data[key] = additionalData[key];
                    }
                }
            }

            return data;
        },

        initPage: function() {
            // Initialize page-specific handlers
            switch (this.currentPage) {
                case 'translio':
                    TranslioDashboard.init();
                    break;
                case 'translio-content':
                    TranslioContent.init();
                    break;
                case 'translio-translate':
                    TranslioTranslate.init();
                    break;
                case 'translio-strings':
                    TranslioStrings.init();
                    break;
                case 'translio-taxonomies':
                    TranslioTaxonomies.init();
                    break;
                case 'translio-translate-term':
                    TranslioTranslateTerm.init();
                    break;
                case 'translio-media':
                    TranslioMedia.init();
                    break;
                case 'translio-translate-media':
                    TranslioTranslateMedia.init();
                    break;
                case 'translio-options':
                    TranslioOptions.init();
                    break;
                case 'translio-wc-attributes':
                    TranslioWC.init();
                    break;
                case 'translio-elementor':
                    TranslioElementor.init();
                    break;
                case 'translio-translate-elementor':
                    TranslioTranslateElementor.init();
                    break;
                case 'translio-cf7':
                    TranslioCF7.init();
                    break;
                case 'translio-translate-cf7':
                    TranslioTranslateCF7.init();
                    break;
                case 'translio-divi':
                    TranslioDivi.init();
                    break;
                case 'translio-translate-divi':
                    TranslioTranslateDivi.init();
                    break;
                case 'translio-avada':
                    TranslioAvada.init();
                    break;
                case 'translio-translate-avada':
                    TranslioTranslateAvada.init();
                    break;
                case 'translio-settings':
                    TranslioSettings.init();
                    break;
            }
        },

        // Progress bar methods
        createProgressBar: function() {
            if ($('#translio-global-progress').length) {
                this.$progressBar = $('#translio-global-progress');
                return;
            }

            var progressHtml =
                '<div id="translio-global-progress" class="translio-global-progress" style="display: none;">' +
                    '<div class="translio-progress-container">' +
                        '<div class="translio-progress-bar-wrapper">' +
                            '<div class="translio-progress-bar-fill"></div>' +
                        '</div>' +
                        '<div class="translio-progress-footer">' +
                            '<span class="translio-progress-message"></span>' +
                            '<button type="button" class="button translio-stop-btn" id="translio-stop-translation">Stop Translation</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            var $wrap = $('.wrap');
            if ($wrap.length) {
                var $h1 = $wrap.find('h1').first();
                if ($h1.length) {
                    $h1.after(progressHtml);
                } else {
                    $wrap.prepend(progressHtml);
                }
            } else {
                $('body').append(progressHtml);
            }
            this.$progressBar = $('#translio-global-progress');
        },

        showProgress: function(message, current, total) {
            if (!this.$progressBar) this.createProgressBar();

            message = message || translioAdmin.strings.translating || 'Translating...';
            current = current || 0;
            total = total || 0;

            var percent = total > 0 ? Math.round((current / total) * 100) : 0;
            var text = message;
            if (total > 0) {
                text += ' ' + current + ' / ' + total;
            }

            this.$progressBar.find('.translio-progress-bar-wrapper').addClass('animated');
            this.$progressBar.find('.translio-progress-bar-fill').addClass('animated').css('width', percent + '%');
            this.$progressBar.find('.translio-progress-message').text(text);
            this.$progressBar.show();
        },

        updateProgress: function(current, total, message) {
            if (!this.$progressBar) return;

            var percent = total > 0 ? Math.round((current / total) * 100) : 0;
            var text = message || translioAdmin.strings.translating || 'Translating...';
            if (total > 0) {
                text += ' ' + current + ' / ' + total;
            }

            this.$progressBar.find('.translio-progress-bar-fill').css('width', percent + '%');
            this.$progressBar.find('.translio-progress-message').text(text);
        },

        hideProgress: function() {
            if (this.$progressBar) {
                this.$progressBar.hide();
                this.$progressBar.find('.translio-progress-bar-wrapper').removeClass('animated');
                this.$progressBar.find('.translio-progress-bar-fill').removeClass('animated').css('width', '0%');
                this.$progressBar.find('.translio-stop-btn').prop('disabled', false).text('Stop Translation');
            }
            this.stopRequested = false;
            this.currentXhr = null;
        },

        // Stop translation flag
        stopRequested: false,
        currentXhr: null,

        // Confirm modal
        $confirmModal: null,
        confirmResolve: null,

        createConfirmModal: function() {
            if ($('#translio-confirm-modal').length) {
                this.$confirmModal = $('#translio-confirm-modal');
                return;
            }

            var modalHtml =
                '<div id="translio-confirm-modal" class="translio-modal-overlay" style="display: none;">' +
                    '<div class="translio-modal">' +
                        '<div class="translio-modal-header">' +
                            '<span class="translio-modal-icon dashicons dashicons-translation"></span>' +
                            '<h2 class="translio-modal-title">Translio</h2>' +
                        '</div>' +
                        '<div class="translio-modal-body">' +
                            '<p class="translio-modal-message"></p>' +
                        '</div>' +
                        '<div class="translio-modal-footer">' +
                            '<button type="button" class="button translio-modal-cancel">' + (translioAdmin.strings.cancel || 'Cancel') + '</button>' +
                            '<button type="button" class="button button-primary translio-modal-confirm">' + (translioAdmin.strings.ok || 'OK') + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $('body').append(modalHtml);
            this.$confirmModal = $('#translio-confirm-modal');

            var self = this;
            this.$confirmModal.on('click', '.translio-modal-confirm', function() {
                self.hideConfirm(true);
            });
            this.$confirmModal.on('click', '.translio-modal-cancel, .translio-modal-overlay', function(e) {
                if (e.target === e.currentTarget || $(e.target).hasClass('translio-modal-cancel')) {
                    self.hideConfirm(false);
                }
            });
            $(document).on('keydown.translioConfirm', function(e) {
                if (e.key === 'Escape' && self.$confirmModal && self.$confirmModal.is(':visible')) {
                    self.hideConfirm(false);
                }
                if (e.key === 'Enter' && self.$confirmModal && self.$confirmModal.is(':visible')) {
                    self.hideConfirm(true);
                }
            });
        },

        confirm: function(message) {
            var self = this;
            if (!this.$confirmModal) this.createConfirmModal();

            return new Promise(function(resolve) {
                self.confirmResolve = resolve;
                self.$confirmModal.find('.translio-modal-message').text(message);
                self.$confirmModal.fadeIn(150);
                self.$confirmModal.find('.translio-modal-confirm').focus();
            });
        },

        hideConfirm: function(result) {
            if (this.$confirmModal) {
                this.$confirmModal.fadeOut(150);
            }
            if (this.confirmResolve) {
                this.confirmResolve(result);
                this.confirmResolve = null;
            }
        },

        // Alert modal (single OK button)
        alert: function(message, type) {
            if (!this.$confirmModal) this.createConfirmModal();

            var iconClass = 'dashicons-translation';
            if (type === 'error') iconClass = 'dashicons-warning';
            else if (type === 'success') iconClass = 'dashicons-yes-alt';
            else if (type === 'info') iconClass = 'dashicons-info';

            this.$confirmModal.find('.translio-modal-icon').attr('class', 'translio-modal-icon dashicons ' + iconClass);
            this.$confirmModal.find('.translio-modal-cancel').hide();
            this.$confirmModal.find('.translio-modal-message').text(message);
            this.$confirmModal.fadeIn(150);
            this.$confirmModal.find('.translio-modal-confirm').focus();

            var self = this;
            return new Promise(function(resolve) {
                self.confirmResolve = function() {
                    self.$confirmModal.find('.translio-modal-cancel').show();
                    self.$confirmModal.find('.translio-modal-icon').attr('class', 'translio-modal-icon dashicons dashicons-translation');
                    resolve();
                };
            });
        },

        requestStop: function() {
            this.stopRequested = true;
            if (this.currentXhr) {
                this.currentXhr.abort();
            }
            if (this.$progressBar) {
                this.$progressBar.find('.translio-progress-message').text('Stopping...');
                this.$progressBar.find('.translio-stop-btn').prop('disabled', true).text('Stopping...');
            }
        },

        isStopRequested: function() {
            return this.stopRequested;
        },

        resetStop: function() {
            this.stopRequested = false;
            this.currentXhr = null;
        },

        showLoader: function(message, subtext) {
            var total = 0;
            if (subtext) {
                var match = subtext.match(/(\d+)\s*(strings?|items?)?/i);
                if (match) {
                    total = parseInt(match[1], 10);
                }
            }
            this.showProgress(message, 0, total);
        },

        hideLoader: function() {
            this.hideProgress();
        },

        // Core event bindings
        bindCoreEvents: function() {
            var self = this;
            $(document).on('click', '#translio-translate-all', this.handleTranslateAll.bind(this));
            $(document).on('click', '#translio-translate-changes', this.handleTranslateChanges.bind(this));
            $(document).on('click', '.translio-translate-field', this.handleTranslateField.bind(this));
            $(document).on('click', '#translio-translate-page', this.handleTranslatePage.bind(this));
            $(document).on('click', '#translio-toggle-key', this.handleToggleKey.bind(this));

            // Stop translation button
            $(document).on('click', '#translio-stop-translation', function() {
                self.requestStop();
            });

            // Collapse toggle for source panels
            $(document).on('click', '.translio-collapse-toggle', function() {
                var $panels = $(this).closest('.translio-panels');
                $panels.toggleClass('source-collapsed');

                // Save preference to localStorage
                var isCollapsed = $panels.hasClass('source-collapsed');
                localStorage.setItem('translio_source_collapsed', isCollapsed ? '1' : '0');

                // Apply to all panels on the page
                $('.translio-panels').toggleClass('source-collapsed', isCollapsed);
            });

            // Restore collapse state from localStorage
            if (localStorage.getItem('translio_source_collapsed') === '1') {
                $('.translio-panels').addClass('source-collapsed');
            }
        },

        handleToggleKey: function(e) {
            e.preventDefault();
            var $input = $('#translio_api_key');
            var $btn = $(e.currentTarget);

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.text('Hide');
            } else {
                $input.attr('type', 'password');
                $btn.text('Show');
            }
        },

        // Autosave functionality
        initAutosave: function() {
            var self = this;

            $(document).on('input', '.translio-input, .translio-textarea', function() {
                var $input = $(this);
                var fieldName = $input.data('field');
                if (fieldName) {
                    self.scheduleAutosave(fieldName, $input.val());
                }
            });

            $(document).on('tinymce-editor-init', function(event, editor) {
                if (editor.id.indexOf('translio_') === 0) {
                    var fieldName = editor.id.replace('translio_', '');
                    editor.on('change keyup', function() {
                        self.scheduleAutosave(fieldName, editor.getContent());
                    });
                }
            });
        },

        scheduleAutosave: function(fieldName, content) {
            var self = this;

            if (this.savingFields[fieldName]) {
                clearTimeout(this.savingFields[fieldName]);
            }

            this.updateSaveStatus(fieldName, 'saving');

            this.savingFields[fieldName] = setTimeout(function() {
                self.saveTranslation(fieldName, content);
            }, 1000);
        },

        saveTranslation: function(fieldName, content) {
            var self = this;
            var $editor = $('.translio-editor');
            var postId = $editor.data('post-id');

            $.ajax({
                url: translioAdmin.ajaxUrl,
                type: 'POST',
                data: Translio.getAjaxData({
                    action: 'translio_save_translation',
                    post_id: postId,
                    field_name: fieldName,
                    translated_content: content
                }),
                success: function(response) {
                    self.updateSaveStatus(fieldName, response.success ? 'saved' : 'error');
                },
                error: function() {
                    self.updateSaveStatus(fieldName, 'error');
                }
            });
        },

        updateSaveStatus: function(fieldName, status) {
            var $row = $('[data-field="' + fieldName + '"]').closest('.translio-field-row');
            var $status = $row.find('.translio-save-status');

            $status.removeClass('saving saved error');

            switch (status) {
                case 'saving':
                    $status.addClass('saving').text(translioAdmin.strings.saving);
                    break;
                case 'saved':
                    $status.addClass('saved').text(translioAdmin.strings.saved);
                    setTimeout(function() {
                        $status.removeClass('saved').text('');
                    }, 2000);
                    break;
                case 'error':
                    $status.addClass('error').text(translioAdmin.strings.error);
                    break;
            }
        },

        // Translate field handler
        handleTranslateField: function(e) {
            e.preventDefault();
            var self = this;
            var $btn = $(e.currentTarget);
            var fieldName = $btn.data('field');
            var $editor = $('.translio-editor');
            var postId = $editor.data('post-id');

            $btn.addClass('loading').prop('disabled', true);
            self.showLoader('Translating ' + fieldName);

            $.ajax({
                url: translioAdmin.ajaxUrl,
                type: 'POST',
                data: Translio.getAjaxData({
                    action: 'translio_translate_single',
                    post_id: postId,
                    field_name: fieldName
                }),
                success: function(response) {
                    if (response.success && response.data.translations) {
                        self.applyTranslations(response.data.translations);
                    } else {
                        Translio.alert(response.data ? response.data.message : 'Translation failed', 'error');
                    }
                },
                error: function() {
                    Translio.alert('Translation request failed', 'error');
                },
                complete: function() {
                    $btn.removeClass('loading').prop('disabled', false);
                    self.hideLoader();
                }
            });
        },

        handleTranslatePage: function(e) {
            e.preventDefault();
            var self = this;
            var $btn = $(e.currentTarget);
            var postId = $btn.data('post-id');

            $btn.addClass('loading').prop('disabled', true).text(translioAdmin.strings.translating);
            self.showLoader('Translating all fields');

            $.ajax({
                url: translioAdmin.ajaxUrl,
                type: 'POST',
                data: Translio.getAjaxData({
                    action: 'translio_translate_single',
                    post_id: postId,
                    field_name: 'all'
                }),
                success: function(response) {
                    if (response.success && response.data.translations) {
                        self.applyTranslations(response.data.translations);
                        $('.translio-needs-update').removeClass('translio-needs-update');
                        $('.translio-update-badge').remove();
                    } else {
                        Translio.alert(response.data ? response.data.message : 'Translation failed', 'error');
                    }
                },
                error: function() {
                    Translio.alert('Translation request failed', 'error');
                },
                complete: function() {
                    self.hideLoader();
                    $btn.removeClass('loading').prop('disabled', false).text(translioAdmin.strings.translated);
                    setTimeout(function() {
                        $btn.text('Auto-translate all fields');
                    }, 2000);
                }
            });
        },

        applyTranslations: function(translations) {
            for (var field in translations) {
                if (translations.hasOwnProperty(field)) {
                    var $input = $('[name="translio_' + field + '"]');
                    if ($input.length) {
                        if (typeof tinymce !== 'undefined' && tinymce.get('translio_' + field)) {
                            tinymce.get('translio_' + field).setContent(translations[field]);
                        } else {
                            $input.val(translations[field]);
                        }
                    }
                }
            }
        },

        handleTranslateAll: async function(e) {
            e.preventDefault();
            var confirmed = await Translio.confirm(translioAdmin.strings.confirmTranslateAll || 'Translate all fields?');
            if (!confirmed) return;
            this.runBulkTranslation('translio_translate_all');
        },

        handleTranslateChanges: async function(e) {
            e.preventDefault();
            var confirmed = await Translio.confirm(translioAdmin.strings.confirmTranslateChanges || 'Translate changed fields?');
            if (!confirmed) return;
            this.runBulkTranslation('translio_translate_changes');
        },

        runBulkTranslation: function(action) {
            var self = this;
            var $btnAll = $('#translio-translate-all');
            var $btnChanges = $('#translio-translate-changes');

            $btnAll.prop('disabled', true);
            $btnChanges.prop('disabled', true);
            self.showProgress(translioAdmin.strings.translating || 'Translating...', 0, 0);

            var totalTranslated = 0;

            function translateBatch() {
                // Check if stop was requested
                if (self.isStopRequested()) {
                    self.hideProgress();
                    $('#translio-content-status').text(translioAdmin.strings.stopped || 'Stopped');
                    $btnAll.prop('disabled', false);
                    $btnChanges.prop('disabled', false);
                    return;
                }

                $.ajax({
                    url: translioAdmin.ajaxUrl,
                    type: 'POST',
                    data: Translio.getAjaxData({ action: action }),
                    success: function(response) {
                        if (response.success) {
                            if (response.data.translated) {
                                totalTranslated += response.data.translated;
                            }
                            if (response.data.stats) {
                                self.updateProgress(totalTranslated, response.data.stats.total, translioAdmin.strings.translating || 'Translating...');
                            }
                            if (response.data.done) {
                                self.hideProgress();
                                $('#translio-content-status').text(response.data.message);
                                $btnAll.prop('disabled', false);
                                $btnChanges.prop('disabled', false);
                            } else {
                                setTimeout(translateBatch, 500);
                            }
                        } else {
                            self.hideProgress();
                            $('#translio-content-status').text('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                            $btnAll.prop('disabled', false);
                            $btnChanges.prop('disabled', false);
                        }
                    },
                    error: function() {
                        self.hideProgress();
                        $('#translio-content-status').text('Request failed');
                        $btnAll.prop('disabled', false);
                        $btnChanges.prop('disabled', false);
                    }
                });
            }

            translateBatch();
        }
    };

    // ========================================
    // COMMON UTILITIES
    // ========================================
    var TranslioUtils = {
        // Checkbox select all handler
        initCheckboxes: function(config) {
            var $selectAll = $(config.selectAll);
            var $checkboxes = $(config.checkboxes);
            var $countDisplay = $(config.countDisplay);
            var $button = $(config.button);

            function updateCount() {
                var count = $(config.checkboxes + ':checked').length;
                $countDisplay.text('(' + count + ')');
                $button.prop('disabled', count === 0);
            }

            $selectAll.on('change', function() {
                $(config.checkboxes).prop('checked', $(this).is(':checked'));
                updateCount();
            });

            $(document).on('change', config.checkboxes, function() {
                var total = $(config.checkboxes).length;
                var checked = $(config.checkboxes + ':checked').length;
                $selectAll.prop('checked', total === checked);
                updateCount();
            });
        },

        // Sequential translation handler
        translateSequentially: function(config) {
            var $btn = $(config.button);
            var $checked = $(config.checkboxes + ':checked');
            var total = $checked.length;

            if (total === 0) return;

            $btn.prop('disabled', true);
            var current = 0;
            var errors = 0;

            function translateNext() {
                if (current >= total) {
                    $btn.prop('disabled', false);
                    $(config.statusDisplay).text(translioAdmin.strings.done || 'Done!');
                    setTimeout(function() {
                        $(config.statusDisplay).text('');
                        if (config.reloadOnComplete) location.reload();
                    }, 1500);
                    return;
                }

                var $checkbox = $($checked[current]);
                var $row = $checkbox.closest(config.rowSelector || 'tr');
                var data = config.getData($checkbox, $row);

                current++;
                $(config.statusDisplay).text((translioAdmin.strings.translating || 'Translating') + ' ' + current + '/' + total);
                $row.css('background-color', '#fff8e5');

                $.post(translioAdmin.ajaxUrl, $.extend({
                    nonce: translioAdmin.nonce
                }, data), function(response) {
                    if (response.success) {
                        $row.css('background-color', '#e7f7e7');
                        if (config.onSuccess) config.onSuccess($row, response);
                    } else {
                        errors++;
                        $row.css('background-color', '#fce4e4');
                    }
                    setTimeout(function() {
                        $row.css('background-color', '');
                    }, 1500);
                    translateNext();
                }).fail(function() {
                    errors++;
                    $row.css('background-color', '#fce4e4');
                    setTimeout(function() {
                        $row.css('background-color', '');
                    }, 1500);
                    translateNext();
                });
            }

            translateNext();
        },

        // Auto-save input handler
        initAutoSave: function(config) {
            var saveTimeout;

            $(document).on('input change', config.inputSelector, function() {
                var $input = $(this);
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    var data = config.getData($input);
                    $.post(translioAdmin.ajaxUrl, $.extend({
                        action: 'translio_save_translation',
                        nonce: translioAdmin.nonce
                    }, data), function(response) {
                        if (response.success && config.statusSelector) {
                            $(config.statusSelector).text(translioAdmin.strings.saved || 'Saved').fadeIn().delay(1000).fadeOut();
                        }
                    });
                }, 500);
            });
        },

        // Single translate button handler
        initTranslateButton: function(config) {
            $(document).on('click', config.buttonSelector, function() {
                var $btn = $(this);
                var data = config.getData($btn);
                var $input = config.getInput($btn);

                $btn.prop('disabled', true).text('...');

                if (window.TranslioAdmin) {
                    TranslioAdmin.showLoader(translioAdmin.strings.translating || 'Translating', data.original);
                }

                $.post(translioAdmin.ajaxUrl, $.extend({
                    nonce: translioAdmin.nonce
                }, data), function(response) {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    if (response.success && response.data.translation) {
                        $input.val(response.data.translation).trigger('input');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                }).fail(function() {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                });
            });
        }
    };

    // ========================================
    // PAGE MODULES
    // ========================================

    // Dashboard
    var TranslioDashboard = {
        init: function() {
            // Dashboard doesn't need special JS
        }
    };

    // Content list
    var TranslioContent = {
        init: function() {
            var self = this;

            // Update selected count
            function updateSelectedCount() {
                var count = $('input[name="post_ids[]"]:checked').length;
                $('#translio-selected-count').text('(' + count + ')');
                $('#translio-translate-selected').prop('disabled', count === 0);
            }

            $(document).on('change', 'input[name="post_ids[]"]', updateSelectedCount);

            $(document).on('change', '#cb-select-all-1, #cb-select-all-2', function() {
                setTimeout(updateSelectedCount, 10);
            });

            // Translate selected posts
            $('#translio-translate-selected').on('click', async function() {
                var $btn = $(this);
                var postIds = [];

                $('input[name="post_ids[]"]:checked').each(function() {
                    postIds.push($(this).val());
                });

                if (postIds.length === 0) {
                    Translio.alert(translioAdmin.strings.selectItem || 'Please select at least one item', 'info');
                    return;
                }

                var confirmed = await Translio.confirm((translioAdmin.strings.confirmTranslate || 'Translate selected items?') + ' (' + postIds.length + ')');
                if (!confirmed) return;

                $btn.prop('disabled', true);
                Translio.showProgress(translioAdmin.strings.translating || 'Translating...', 0, postIds.length);

                var total = postIds.length;
                var current = 0;
                var errors = 0;

                function translateNext() {
                    // Check if stop was requested
                    if (Translio.isStopRequested()) {
                        $btn.prop('disabled', false);
                        Translio.hideProgress();
                        $('#translio-content-status').text((translioAdmin.strings.stopped || 'Stopped') + ' ' + current + '/' + total);
                        $('input[name="post_ids[]"]').prop('checked', false);
                        updateSelectedCount();
                        return;
                    }

                    if (current >= total) {
                        $btn.prop('disabled', false);
                        Translio.hideProgress();
                        var msg = (translioAdmin.strings.complete || 'Complete!') + ' ' + (total - errors) + '/' + total;
                        if (errors > 0) {
                            msg += ' (' + (translioAdmin.strings.errors || 'errors:') + ' ' + errors + ')';
                        }
                        $('#translio-content-status').text(msg);
                        $('input[name="post_ids[]"]').prop('checked', false);
                        updateSelectedCount();
                        return;
                    }

                    var postId = postIds[current];
                    Translio.updateProgress(current, total, translioAdmin.strings.translating || 'Translating...');

                    var $row = $('input[value="' + postId + '"]').closest('tr');
                    $row.css('background-color', '#fffbcc');

                    $.ajax({
                        url: translioAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'translio_translate_single_post',
                            nonce: translioAdmin.nonce,
                            post_id: postId
                        },
                        success: function(response) {
                            if (response.success) {
                                $row.css('background-color', '#d4edda');
                                $row.find('.translio-status').removeClass('translio-status-missing translio-status-outdated').addClass('translio-status-ok').text(translioAdmin.strings.translated || 'Translated');
                            } else {
                                $row.css('background-color', '#f8d7da');
                                errors++;
                            }
                        },
                        error: function() {
                            $row.css('background-color', '#f8d7da');
                            errors++;
                        },
                        complete: function() {
                            current++;
                            setTimeout(translateNext, 500);
                        }
                    });
                }

                translateNext();
            });
        }
    };

    // Translate post
    var TranslioTranslate = {
        init: function() {
            // Uses core autosave and translate handlers
        }
    };

    // Theme Strings
    var TranslioStrings = {
        init: function() {
            TranslioUtils.initCheckboxes({
                selectAll: '#translio-select-all-strings',
                checkboxes: '.translio-string-checkbox',
                countDisplay: '#translio-selected-strings-count',
                button: '#translio-translate-selected-strings'
            });

            // Scan theme files
            $('#translio-scan-theme-files').on('click', function() {
                var $btn = $(this);
                var $status = $('.translio-scan-status');

                $btn.prop('disabled', true);
                $status.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span> Scanning theme files...');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_scan_files',
                    nonce: translioAdmin.nonce,
                    type: 'theme'
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + (response.data || 'Error') + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.html('<span style="color: #dc3232;">✗ Request failed</span>');
                });
            });

            // Scan plugin files
            $('#translio-scan-plugin-files').on('click', function() {
                var $btn = $(this);
                var $status = $('.translio-scan-status');

                $btn.prop('disabled', true);
                $status.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span> Scanning plugin files...');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_scan_files',
                    nonce: translioAdmin.nonce,
                    type: 'plugins'
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + (response.data || 'Error') + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.html('<span style="color: #dc3232;">✗ Request failed</span>');
                });
            });

            // Save single string
            $(document).on('click', '.translio-save-string', function() {
                var $row = $(this).closest('tr');
                var data = {
                    action: 'translio_save_string',
                    nonce: translioAdmin.nonce,
                    string_id: $row.data('string-id'),
                    original: $row.data('original'),
                    domain: $row.data('domain'),
                    type: $row.data('type') || 'string',
                    translation: $row.find('.translio-string-input').val()
                };

                $.post(translioAdmin.ajaxUrl, data, function(response) {
                    if (response.success) {
                        $row.addClass('translio-string-saved');
                        setTimeout(function() { $row.removeClass('translio-string-saved'); }, 1000);
                    }
                });
            });

            // Translate single string
            $(document).on('click', '.translio-translate-string', function() {
                var $btn = $(this);
                var $row = $btn.closest('tr');

                $btn.prop('disabled', true).text('...');
                if (window.TranslioAdmin) TranslioAdmin.showLoader('Translating', $row.data('original').substring(0, 50));

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_strings',
                    nonce: translioAdmin.nonce,
                    strings: JSON.stringify([{
                        id: $row.data('string-id'),
                        text: $row.data('original'),
                        domain: $row.data('domain'),
                        type: $row.data('type') || 'string'
                    }])
                }, function(response) {
                    $btn.prop('disabled', false).text('Auto');
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    if (response.success && response.data.translations) {
                        var translated = response.data.translations[$row.data('string-id')];
                        if (translated) {
                            $row.find('.translio-string-input').val(translated);
                            $row.addClass('translio-string-saved');
                            setTimeout(function() { $row.removeClass('translio-string-saved'); }, 1000);
                        }
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Auto');
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                });
            });

            // Translate selected strings
            $('#translio-translate-selected-strings').on('click', async function() {
                var $btn = $(this);
                var strings = [];

                $('.translio-string-checkbox:checked').each(function() {
                    var $row = $(this).closest('tr');
                    strings.push({
                        id: $row.data('string-id'),
                        text: $row.data('original'),
                        domain: $row.data('domain'),
                        type: $row.data('type') || 'string'
                    });
                });

                if (strings.length === 0) return;
                var confirmed = await Translio.confirm('Translate selected strings? (' + strings.length + ')');
                if (!confirmed) return;

                $btn.prop('disabled', true);
                $('.translio-bulk-status').text(translioAdmin.strings.translating || 'Translating...');
                if (window.TranslioAdmin) TranslioAdmin.showLoader('Translating', strings.length + ' strings');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_strings',
                    nonce: translioAdmin.nonce,
                    strings: JSON.stringify(strings)
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    if (response.success && response.data.translations) {
                        $.each(response.data.translations, function(stringId, translated) {
                            var $row = $('tr[data-string-id="' + stringId + '"]');
                            $row.find('.translio-string-input').val(translated);
                            $row.css('background-color', '#d4edda');
                        });
                        $('.translio-bulk-status').text('Done!');
                        $('.translio-string-checkbox, #translio-select-all-strings').prop('checked', false);
                        $('#translio-selected-strings-count').text('(0)');
                        $btn.prop('disabled', true);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    $('.translio-bulk-status').text('Error');
                });
            });

            // Translate all visible strings on current page
            $('#translio-translate-all-strings').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                var strings = [];

                // Collect all visible strings (untranslated ones)
                $('.translio-strings-table tbody tr').each(function() {
                    var $row = $(this);
                    var $input = $row.find('.translio-string-input');
                    // Only include if translation is empty
                    if ($input.length && $input.val().trim() === '') {
                        strings.push({
                            id: $row.data('string-id'),
                            text: $row.data('original'),
                            domain: $row.data('domain'),
                            type: $row.data('type') || 'string'
                        });
                    }
                });

                if (strings.length === 0) {
                    Translio.alert('All visible strings are already translated.', 'success');
                    return;
                }

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.stopRequested = false;
                Translio.showProgress('Translating ' + strings.length + ' strings (please wait)...', 0, strings.length);

                // Animate progress bar while waiting
                var fakeProgress = 0;
                var progressInterval = setInterval(function() {
                    if (Translio.isStopRequested()) {
                        clearInterval(progressInterval);
                        return;
                    }
                    if (fakeProgress < strings.length - 1) {
                        fakeProgress++;
                        Translio.updateProgress(fakeProgress, strings.length, 'Translating via API...');
                    }
                }, 2000); // Update every 2 seconds

                Translio.currentXhr = $.ajax({
                    url: translioAdmin.ajaxUrl,
                    type: 'POST',
                    timeout: 300000, // 5 minutes timeout
                    data: {
                        action: 'translio_translate_strings',
                        nonce: translioAdmin.nonce,
                        strings: JSON.stringify(strings)
                    },
                    success: function(response) {
                        clearInterval(progressInterval);
                        if (Translio.isStopRequested()) {
                            Translio.hideProgress();
                            $btn.prop('disabled', false).text(originalText);
                            $('.translio-strings-status').text('Stopped');
                            return;
                        }
                        if (response.success && response.data.translations) {
                            var count = 0;
                            var translations = response.data.translations;
                            var keys = Object.keys(translations);

                            if (keys.length === 0) {
                                Translio.hideProgress();
                                $btn.prop('disabled', false).text(originalText);
                                Translio.alert('No translations returned. Check API key.', 'error');
                                return;
                            }

                            keys.forEach(function(stringId, index) {
                                setTimeout(function() {
                                    if (Translio.isStopRequested()) return;
                                    var $row = $('tr[data-string-id="' + stringId + '"]');
                                    $row.find('.translio-string-input').val(translations[stringId]);
                                    $row.css('background-color', '#d4edda');
                                    count++;
                                    Translio.updateProgress(count, keys.length, 'Filling translations...');

                                    if (count === keys.length) {
                                        setTimeout(function() {
                                            Translio.hideProgress();
                                            $btn.prop('disabled', false).text(originalText);
                                            $('.translio-strings-status').text('Complete! ' + keys.length + ' translated');
                                        }, 300);
                                    }
                                }, index * 50);
                            });
                        } else {
                            Translio.hideProgress();
                            Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                            $btn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        clearInterval(progressInterval);
                        Translio.hideProgress();
                        $btn.prop('disabled', false).text(originalText);
                        if (status === 'abort') {
                            $('.translio-strings-status').text('Translation stopped');
                        } else if (status === 'timeout') {
                            Translio.alert('Request timed out. Try translating fewer strings.', 'error');
                        } else {
                            Translio.alert('Error: ' + error, 'error');
                        }
                    }
                });
            });

            // Translate all untranslated
            $('#translio-translate-untranslated').on('click', async function() {
                var $btn = $(this);
                var originalText = $btn.text();
                var confirmed = await Translio.confirm('Translate all untranslated strings? This may take a while.');
                if (!confirmed) return;

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.showProgress(translioAdmin.strings.translating || 'Translating all...', 0, 0);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_bulk_translate_strings',
                    nonce: translioAdmin.nonce
                }, function(response) {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);
                    if (response.success) {
                        $('.translio-strings-status').text(translioAdmin.strings.complete + ' ' + response.data.translated + ' strings');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                }).fail(function() {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);
                });
            });

            // Clear strings
            $('#translio-clear-strings').on('click', async function() {
                var confirmed = await Translio.confirm('Clear all scanned strings? This cannot be undone.');
                if (!confirmed) return;

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_clear_strings',
                    nonce: translioAdmin.nonce
                }, function(response) {
                    if (response.success) location.reload();
                });
            });

            // Add custom string
            $('#translio-add-string').on('click', function() {
                var original = $('#translio-new-string').val();
                var translation = $('#translio-new-translation').val();
                var domain = $('#translio-new-domain').val();

                if (!original) {
                    Translio.alert('Please enter the original string', 'info');
                    return;
                }

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_save_string',
                    nonce: translioAdmin.nonce,
                    original: original,
                    domain: domain,
                    translation: translation
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
        }
    };

    // Taxonomies list
    var TranslioTaxonomies = {
        init: function() {
            var self = this;

            TranslioUtils.initCheckboxes({
                selectAll: '#cb-select-all',
                checkboxes: 'input[name="term_ids[]"]',
                countDisplay: '#translio-selected-terms-count',
                button: '#translio-translate-selected-terms'
            });

            // Translate Selected
            $('#translio-translate-selected-terms').on('click', async function() {
                var termIds = [];
                $('input[name="term_ids[]"]:checked').each(function() {
                    termIds.push($(this).val());
                });

                if (termIds.length === 0) return;
                var confirmed = await Translio.confirm('Translate selected terms? (' + termIds.length + ')');
                if (!confirmed) return;

                self.translateTerms(termIds, $(this));
            });

            // Translate All Untranslated
            $('#translio-translate-all-terms').on('click', async function() {
                var termIds = [];
                $('tr').each(function() {
                    var $row = $(this);
                    if ($row.find('.translio-status-missing').length > 0) {
                        var termId = $row.find('input[name="term_ids[]"]').val();
                        if (termId) termIds.push(termId);
                    }
                });

                if (termIds.length === 0) {
                    Translio.alert('All terms are already translated!', 'info');
                    return;
                }

                var confirmed = await Translio.confirm('Translate all untranslated terms? (' + termIds.length + ')');
                if (!confirmed) return;

                self.translateTerms(termIds, $(this));
            });

            // Inline Translate button
            $(document).on('click', '.translio-translate-term-inline', function() {
                var $btn = $(this);
                var termId = $btn.data('term-id');
                var $row = $btn.closest('tr');

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                $row.css('background-color', '#fffbcc');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_taxonomy_term',
                    nonce: translioAdmin.nonce,
                    term_id: termId,
                    fields: ['name', 'description']
                }, function(response) {
                    if (response.success) {
                        $row.css('background-color', '#d4edda');
                        $row.find('.translio-status')
                            .removeClass('translio-status-missing')
                            .addClass('translio-status-ok')
                            .text(translioAdmin.strings.translated || 'Translated');
                        $btn.remove();
                    } else {
                        $row.css('background-color', '#f8d7da');
                        $btn.prop('disabled', false).text('Translate');
                        Translio.alert('Translation failed: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    }
                }).fail(function() {
                    $row.css('background-color', '#f8d7da');
                    $btn.prop('disabled', false).text('Translate');
                    Translio.alert('Translation request failed', 'error');
                });
            });
        },

        translateTerms: function(termIds, $btn) {
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');

            Translio.stopRequested = false;
            Translio.showProgress(translioAdmin.strings.translating || 'Translating...', 0, termIds.length);

            var total = termIds.length;
            var current = 0;
            var errors = 0;

            function translateNext() {
                if (Translio.isStopRequested()) {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).html(originalHtml);
                    $('#translio-taxonomies-status').text((translioAdmin.strings.stopped || 'Stopped') + ' ' + current + '/' + total);
                    if (current > 0) setTimeout(function() { location.reload(); }, 1000);
                    return;
                }

                if (current >= total) {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).html(originalHtml);
                    $('#translio-taxonomies-status').text((translioAdmin.strings.complete || 'Complete!') + ' ' + (total - errors) + '/' + total);
                    setTimeout(function() { location.reload(); }, 1000);
                    return;
                }

                Translio.updateProgress(current, total, translioAdmin.strings.translating || 'Translating...');

                var $row = $('input[name="term_ids[]"][value="' + termIds[current] + '"]').closest('tr');
                $row.css('background-color', '#fffbcc');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_taxonomy_term',
                    nonce: translioAdmin.nonce,
                    term_id: termIds[current],
                    fields: ['name', 'description']
                }, function(response) {
                    if (response.success) {
                        $row.css('background-color', '#d4edda');
                        $row.find('.translio-status')
                            .removeClass('translio-status-missing')
                            .addClass('translio-status-ok')
                            .text(translioAdmin.strings.translated || 'Translated');
                        $row.find('.translio-translate-term-inline').remove();
                    } else {
                        $row.css('background-color', '#f8d7da');
                        errors++;
                    }
                }).fail(function() {
                    $row.css('background-color', '#f8d7da');
                    errors++;
                }).always(function() {
                    current++;
                    translateNext();
                });
            }

            translateNext();
        }
    };

    // Translate term
    var TranslioTranslateTerm = {
        init: function() {
            var termId = $('.translio-editor').data('term-id');
            var taxonomy = $('.translio-editor').data('taxonomy');

            // Auto-save term fields
            $('.translio-input, .translio-textarea').on('change', function() {
                var $field = $(this);
                var $row = $field.closest('.translio-field-row');
                var $status = $row.find('.translio-save-status');

                $status.html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_save_translation',
                    nonce: translioAdmin.nonce,
                    object_id: termId,
                    object_type: 'term',
                    field: $field.data('field'),
                    translation: $field.val()
                }, function(response) {
                    if (response.success) {
                        $status.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span>');
                    } else {
                        $status.html('<span style="color: #dc3232;">Error saving</span>');
                    }
                    setTimeout(function() { $status.html(''); }, 3000);
                });
            });

            // Translate term field
            $('.translio-translate-term-field').on('click', function() {
                var $btn = $(this);
                var field = $btn.data('field');
                var text = $btn.data('text');
                var $input = $('#translio-' + field);
                var $row = $btn.closest('.translio-field-row');
                var $status = $row.find('.translio-save-status');

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                $status.html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_term_field',
                    nonce: translioAdmin.nonce,
                    term_id: termId,
                    taxonomy: taxonomy,
                    field: field,
                    text: text
                }, function(response) {
                    if (response.success) {
                        $input.val(response.data.translation).trigger('change');
                        $status.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span>');
                    } else {
                        $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span>');
                        Translio.alert(response.data.message || 'Translation failed', 'error');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                    setTimeout(function() { $status.html(''); }, 2000);
                });
            });
        }
    };

    // Media list
    var TranslioMedia = {
        init: function() {
            var self = this;

            // Checkbox handling
            $('#translio-select-all-media').on('change', function() {
                var isChecked = $(this).prop('checked');
                $('.translio-media-checkbox').prop('checked', isChecked);
                self.updateSelectedCount();
            });

            $(document).on('change', '.translio-media-checkbox', function() {
                self.updateSelectedCount();
            });

            // Translate selected media
            $('#translio-translate-selected-media').on('click', function() {
                var $btn = $(this);
                var selectedIds = [];
                $('.translio-media-checkbox:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) return;

                self.translateMedia(selectedIds, $btn);
            });

            // Translate all untranslated
            $('#translio-translate-all-untranslated-media').on('click', function() {
                var $btn = $(this);
                var untranslatedIds = [];
                $('.translio-media-table tbody tr[data-untranslated="1"]').each(function() {
                    untranslatedIds.push($(this).data('attachment-id'));
                });

                if (untranslatedIds.length === 0) {
                    Translio.alert('No untranslated media found on this page.', 'info');
                    return;
                }

                self.translateMedia(untranslatedIds, $btn);
            });

            // Inline translate button
            $(document).on('click', '.translio-translate-media-inline', function() {
                var $btn = $(this);
                var attachmentId = $btn.data('attachment-id');
                var $row = $btn.closest('tr');

                $btn.prop('disabled', true).text('...');
                $row.css('background-color', '#fffbea');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_media_all_fields',
                    nonce: translioAdmin.nonce,
                    attachment_id: attachmentId
                }, function(response) {
                    if (response.success) {
                        $row.css('background-color', '#d4edda');
                        $btn.remove();
                        $row.attr('data-untranslated', '0');
                        // Update status icons
                        $row.find('.translio-status-missing').removeClass('translio-status-missing').addClass('translio-status-ok').text('✓');
                    } else {
                        $row.css('background-color', '#f8d7da');
                        $btn.prop('disabled', false).text('Translate');
                        Translio.alert(response.data.message || 'Translation failed', 'error');
                    }
                }).fail(function() {
                    $row.css('background-color', '#f8d7da');
                    $btn.prop('disabled', false).text('Translate');
                });
            });
        },

        updateSelectedCount: function() {
            var count = $('.translio-media-checkbox:checked').length;
            $('#translio-selected-media-count').text('(' + count + ')');
            $('#translio-translate-selected-media').prop('disabled', count === 0);
        },

        translateMedia: function(ids, $btn) {
            var self = this;
            var total = ids.length;
            var current = 0;
            var translated = 0;
            var errors = 0;

            Translio.resetStop();
            Translio.showProgress('Translating media...', 0, total);
            $btn.prop('disabled', true);

            function translateNext() {
                if (Translio.isStopRequested()) {
                    Translio.hideProgress();
                    $btn.prop('disabled', false);
                    $('.translio-bulk-status').text('Stopped. Translated: ' + translated);
                    return;
                }

                if (current >= total) {
                    Translio.hideProgress();
                    $btn.prop('disabled', false);
                    $('.translio-bulk-status').html('<span style="color: #46b450;">✓ Translated ' + translated + ' of ' + total + '</span>');
                    if (translated > 0) {
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                    return;
                }

                var attachmentId = ids[current];
                var $row = $('tr[data-attachment-id="' + attachmentId + '"]');
                $row.css('background-color', '#fffbea');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_media_all_fields',
                    nonce: translioAdmin.nonce,
                    attachment_id: attachmentId
                }, function(response) {
                    if (response.success) {
                        $row.css('background-color', '#d4edda');
                        translated++;
                    } else {
                        $row.css('background-color', '#f8d7da');
                        errors++;
                    }
                }).fail(function() {
                    $row.css('background-color', '#f8d7da');
                    errors++;
                }).always(function() {
                    current++;
                    Translio.updateProgress(current, total, 'Translating ' + current + ' of ' + total);
                    translateNext();
                });
            }

            translateNext();
        }
    };

    // Translate media
    var TranslioTranslateMedia = {
        init: function() {
            var attachmentId = $('.translio-editor').data('attachment-id');

            // Auto-save media fields
            $('.translio-input, .translio-textarea').on('change', function() {
                var $field = $(this);
                var $row = $field.closest('.translio-field-row');
                var $status = $row.find('.translio-save-status');

                $status.html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_save_translation',
                    nonce: translioAdmin.nonce,
                    post_id: attachmentId,
                    object_type: 'attachment',
                    field_name: $field.data('field'),
                    translated_content: $field.val()
                }, function(response) {
                    $status.html(response.success ?
                        '<span class="dashicons dashicons-yes" style="color: #46b450;"></span>' :
                        '<span class="dashicons dashicons-no" style="color: #dc3232;"></span>');
                    setTimeout(function() { $status.html(''); }, 2000);
                });
            });

            // Translate single media field
            $(document).on('click', '.translio-translate-field', function() {
                var $btn = $(this);
                var field = $btn.data('field');
                var $row = $btn.closest('.translio-field-row');
                var $input = $row.find('[data-field="' + field + '"]');
                var $status = $row.find('.translio-save-status');

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                $status.html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_single',
                    nonce: translioAdmin.nonce,
                    post_id: attachmentId,
                    field_name: field,
                    object_type: 'attachment'
                }, function(response) {
                    if (response.success && response.data.translations) {
                        $input.val(response.data.translations[field]);
                        $status.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span>');
                    } else {
                        $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span>');
                        Translio.alert(response.data.message || 'Translation failed', 'error');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                    setTimeout(function() { $status.html(''); }, 2000);
                });
            });

            // Translate all media fields
            $('#translio-translate-media-all').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                var totalFields = $('.translio-input, .translio-textarea').filter('[data-field]').length;

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.showProgress(translioAdmin.strings.translating || 'Translating...', 0, totalFields);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_media_all_fields',
                    nonce: translioAdmin.nonce,
                    attachment_id: attachmentId
                }, function(response) {
                    if (response.success && response.data.translations) {
                        var count = 0;
                        var translations = response.data.translations;
                        var keys = Object.keys(translations);

                        keys.forEach(function(field, index) {
                            setTimeout(function() {
                                $('[data-field="' + field + '"]').val(translations[field]);
                                count++;
                                Translio.updateProgress(count, keys.length, translioAdmin.strings.translating || 'Translating...');

                                if (count === keys.length) {
                                    setTimeout(function() {
                                        Translio.hideProgress();
                                        $btn.prop('disabled', false).text(originalText);
                                        $('.translio-save-status').text(translioAdmin.strings.complete || 'Complete!').fadeIn().delay(2000).fadeOut();
                                    }, 300);
                                }
                            }, index * 100);
                        });

                        if (keys.length === 0) {
                            Translio.hideProgress();
                            $btn.prop('disabled', false).text(originalText);
                        }
                    } else {
                        Translio.hideProgress();
                        Translio.alert(response.data.message || 'Translation failed', 'error');
                        $btn.prop('disabled', false).text(originalText);
                    }
                }).fail(function() {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        }
    };

    // Site Options
    var TranslioOptions = {
        init: function() {
            var saveTimeout;

            // Auto-save site options
            $('.translio-option-input').on('input change', function() {
                var $input = $(this);
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    $.post(translioAdmin.ajaxUrl, {
                        action: 'translio_save_translation',
                        nonce: translioAdmin.nonce,
                        object_id: 1,
                        object_type: 'option',
                        field: $input.data('option-name'),
                        translation: $input.val()
                    }, function(response) {
                        if (response.success) {
                            $('.translio-save-status').text(translioAdmin.strings.saved || 'Saved').fadeIn().delay(1000).fadeOut();
                        }
                    });
                }, 500);
            });

            // Translate single option
            $('.translio-translate-option').on('click', function() {
                var $btn = $(this);
                var optionName = $btn.data('option') || $btn.data('option-name');
                var original = $btn.data('original');
                var $input = $btn.closest('.translio-field-row').find('.translio-option-input');

                if (!original) return;

                $btn.prop('disabled', true).text('...');
                if (window.TranslioAdmin) TranslioAdmin.showLoader('Translating', original);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_option',
                    nonce: translioAdmin.nonce,
                    option_name: optionName,
                    original: original
                }, function(response) {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    if (response.success && response.data.translation) {
                        $input.val(response.data.translation).trigger('input');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                }).fail(function() {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                });
            });

            // Options checkboxes
            TranslioUtils.initCheckboxes({
                selectAll: '#translio-select-all-options',
                checkboxes: '.translio-option-checkbox',
                countDisplay: '#translio-selected-options-count',
                button: '#translio-translate-selected-options'
            });

            // Translate selected options
            $('#translio-translate-selected-options').on('click', function() {
                TranslioUtils.translateSequentially({
                    button: '#translio-translate-selected-options',
                    checkboxes: '.translio-option-checkbox',
                    statusDisplay: '#translio-options-status',
                    getData: function($checkbox, $row) {
                        return {
                            action: 'translio_translate_option',
                            option_name: $checkbox.val(),
                            original: $row.data('original')
                        };
                    },
                    onSuccess: function($row, response) {
                        if (response.data.translation) {
                            $row.find('.translio-option-input').val(response.data.translation).trigger('input');
                        }
                    }
                });
            });

            // Widget handlers
            this.initWidgets();
        },

        initWidgets: function() {
            var saveTimeout;

            // Auto-save widget titles
            $('.translio-widget-title-input').on('input change', function() {
                var $input = $(this);
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    $.post(translioAdmin.ajaxUrl, {
                        action: 'translio_save_translation',
                        nonce: translioAdmin.nonce,
                        object_id: $input.data('object-id'),
                        object_type: 'widget',
                        field: 'title',
                        translation: $input.val()
                    }, function(response) {
                        if (response.success) {
                            $('.translio-save-status').text(translioAdmin.strings.saved || 'Saved').fadeIn().delay(1000).fadeOut();
                        }
                    });
                }, 500);
            });

            // Auto-save widget content
            $('.translio-widget-content-input').on('input change', function() {
                var $input = $(this);
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    $.post(translioAdmin.ajaxUrl, {
                        action: 'translio_save_translation',
                        nonce: translioAdmin.nonce,
                        object_id: $input.data('object-id'),
                        object_type: 'widget',
                        field: $input.data('field'),
                        translation: $input.val()
                    }, function(response) {
                        if (response.success) {
                            $('.translio-save-status').text(translioAdmin.strings.saved || 'Saved').fadeIn().delay(1000).fadeOut();
                        }
                    });
                }, 500);
            });

            // Translate widget title
            $('.translio-translate-widget-title').on('click', function() {
                var $btn = $(this);
                var objectId = $btn.data('object-id');
                var original = $btn.data('original');
                var $input = $btn.closest('.translio-widget-field').find('.translio-widget-title-input');

                if (!original) return;

                $btn.prop('disabled', true).text('...');
                if (window.TranslioAdmin) TranslioAdmin.showLoader('Translating', original.substring(0, 50));

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_single',
                    nonce: translioAdmin.nonce,
                    post_id: objectId,
                    object_type: 'widget',
                    field_name: 'title',
                    content: original
                }, function(response) {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    if (response.success && response.data.translations && response.data.translations.title) {
                        $input.val(response.data.translations.title).trigger('input');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                }).fail(function() {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                });
            });

            // Translate widget content
            $('.translio-translate-widget-content').on('click', function() {
                var $btn = $(this);
                var objectId = $btn.data('object-id');
                var field = $btn.data('field');
                var original = $btn.data('original');
                var $input = $btn.closest('.translio-widget-field').find('.translio-widget-content-input');

                if (!original) return;

                $btn.prop('disabled', true).text('...');
                if (window.TranslioAdmin) TranslioAdmin.showLoader('Translating', original.substring(0, 50) + '...');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_single',
                    nonce: translioAdmin.nonce,
                    post_id: objectId,
                    object_type: 'widget',
                    field_name: field,
                    content: original
                }, function(response) {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    if (response.success && response.data.translations && response.data.translations[field]) {
                        $input.val(response.data.translations[field]).trigger('input');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                }).fail(function() {
                    if (window.TranslioAdmin) TranslioAdmin.hideLoader();
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                });
            });

            // Widgets checkboxes
            TranslioUtils.initCheckboxes({
                selectAll: '#translio-select-all-widgets',
                checkboxes: '.translio-widget-checkbox',
                countDisplay: '#translio-selected-widgets-count',
                button: '#translio-translate-selected-widgets'
            });

            // Translate selected widgets
            $('#translio-translate-selected-widgets').on('click', function() {
                var $btn = $(this);
                var $checked = $('.translio-widget-checkbox:checked');
                var total = $checked.length;

                if (total === 0) return;

                $btn.prop('disabled', true);
                var current = 0;

                function translateNextWidget() {
                    if (current >= total) {
                        $btn.prop('disabled', false);
                        $('#translio-widgets-status').text('Done!');
                        setTimeout(function() { $('#translio-widgets-status').text(''); }, 2000);
                        return;
                    }

                    var $checkbox = $($checked[current]);
                    var $box = $checkbox.closest('.translio-widget-box');
                    var titleObjId = $box.data('title-obj-id');
                    var contentObjId = $box.data('content-obj-id');
                    var titleOriginal = $box.data('title-original');
                    var contentOriginal = $box.data('content-original');
                    var contentField = $box.data('content-field');

                    current++;
                    $('#translio-widgets-status').text('Translating ' + current + '/' + total);
                    $box.css('background-color', '#fff8e5');

                    var promises = [];

                    if (titleObjId && titleOriginal) {
                        promises.push($.post(translioAdmin.ajaxUrl, {
                            action: 'translio_translate_single',
                            nonce: translioAdmin.nonce,
                            post_id: titleObjId,
                            object_type: 'widget',
                            field_name: 'title',
                            content: titleOriginal
                        }).then(function(response) {
                            if (response.success && response.data.translations && response.data.translations.title) {
                                $box.find('.translio-widget-title-input').val(response.data.translations.title).trigger('input');
                            }
                        }));
                    }

                    if (contentObjId && contentOriginal) {
                        promises.push($.post(translioAdmin.ajaxUrl, {
                            action: 'translio_translate_single',
                            nonce: translioAdmin.nonce,
                            post_id: contentObjId,
                            object_type: 'widget',
                            field_name: contentField,
                            content: contentOriginal
                        }).then(function(response) {
                            if (response.success && response.data.translations && response.data.translations[contentField]) {
                                $box.find('.translio-widget-content-input').val(response.data.translations[contentField]).trigger('input');
                            }
                        }));
                    }

                    $.when.apply($, promises).done(function() {
                        $box.css('background-color', '#e7f7e7');
                        setTimeout(function() { $box.css('background-color', ''); }, 1500);
                        translateNextWidget();
                    }).fail(function() {
                        $box.css('background-color', '#fce4e4');
                        setTimeout(function() { $box.css('background-color', ''); }, 1500);
                        translateNextWidget();
                    });
                }

                translateNextWidget();
            });
        }
    };

    // WooCommerce Attributes
    var TranslioWC = {
        init: function() {
            var saveTimeout;

            // Auto-save on input change
            $('.translio-wc-attr-input').on('input change', function() {
                var $input = $(this);
                var $status = $input.siblings('.translio-save-status');
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    $.post(translioAdmin.ajaxUrl, {
                        action: 'translio_save_wc_attribute',
                        nonce: translioAdmin.nonce,
                        attr_id: $input.data('attr-id'),
                        translation: $input.val()
                    }, function(response) {
                        if (response.success) {
                            $status.text(translioAdmin.strings.saved || 'Saved').fadeIn().delay(1000).fadeOut();
                            // Update translated flag
                            $input.closest('tr').attr('data-translated', $input.val() ? '1' : '0');
                        }
                    });
                }, 500);
            });

            // Translate single attribute
            $('.translio-translate-wc-attr').on('click', function() {
                var $btn = $(this);
                var attrId = $btn.data('attr-id');
                var original = $btn.data('original');
                var $input = $btn.closest('tr').find('.translio-wc-attr-input');

                if (!original) return;

                $btn.prop('disabled', true).text('...');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_wc_attribute',
                    nonce: translioAdmin.nonce,
                    attr_id: attrId,
                    original: original
                }, function(response) {
                    if (response.success && response.data.translation) {
                        $input.val(response.data.translation).trigger('input');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                }).fail(function() {
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                });
            });

            // Checkboxes
            TranslioUtils.initCheckboxes({
                selectAll: '#translio-wc-select-all',
                checkboxes: '.translio-wc-attr-checkbox',
                countDisplay: '#translio-wc-selected-count',
                button: '#translio-wc-translate-selected'
            });

            // Translate selected attributes
            $('#translio-wc-translate-selected').on('click', function() {
                var $btn = $(this);
                var originalHtml = $btn.html();
                var attrIds = [];

                $('.translio-wc-attr-checkbox:checked').each(function() {
                    attrIds.push($(this).val());
                });

                if (attrIds.length === 0) return;

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.stopRequested = false;
                Translio.showProgress(translioAdmin.strings.translating || 'Translating...', 0, attrIds.length);

                var current = 0;
                var errors = 0;

                function translateNext() {
                    if (Translio.isStopRequested()) {
                        Translio.hideProgress();
                        $btn.prop('disabled', false).html(originalHtml);
                        $('.translio-wc-status').text('Stopped. ' + current + '/' + attrIds.length);
                        return;
                    }

                    if (current >= attrIds.length) {
                        Translio.hideProgress();
                        $btn.prop('disabled', false).html(originalHtml);
                        $('.translio-wc-status').text('Complete! ' + (attrIds.length - errors) + '/' + attrIds.length);
                        $('.translio-wc-attr-checkbox').prop('checked', false);
                        $('#translio-wc-select-all').prop('checked', false);
                        $('#translio-wc-selected-count').text('(0)');
                        return;
                    }

                    var attrId = attrIds[current];
                    var $row = $('tr[data-attr-id="' + attrId + '"]');
                    var original = $row.data('original');

                    Translio.updateProgress(current, attrIds.length, translioAdmin.strings.translating || 'Translating...');

                    $.post(translioAdmin.ajaxUrl, {
                        action: 'translio_translate_wc_attribute',
                        nonce: translioAdmin.nonce,
                        attr_id: attrId,
                        original: original
                    }, function(response) {
                        if (response.success && response.data.translation) {
                            $row.find('.translio-wc-attr-input').val(response.data.translation).trigger('input');
                            $row.css('background-color', '#d4edda');
                        } else {
                            errors++;
                        }
                    }).fail(function() {
                        errors++;
                    }).always(function() {
                        current++;
                        translateNext();
                    });
                }

                translateNext();
            });

            // Translate all untranslated
            $('#translio-wc-translate-all').on('click', function() {
                var $btn = $(this);
                var originalHtml = $btn.html();
                var attrIds = [];

                // Collect untranslated
                $('#translio-wc-attrs-table tbody tr').each(function() {
                    if ($(this).attr('data-translated') === '0') {
                        attrIds.push($(this).data('attr-id'));
                    }
                });

                if (attrIds.length === 0) {
                    Translio.alert('All attributes are already translated.', 'success');
                    return;
                }

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.stopRequested = false;
                Translio.showProgress(translioAdmin.strings.translating || 'Translating...', 0, attrIds.length);

                var current = 0;
                var errors = 0;

                function translateNext() {
                    if (Translio.isStopRequested()) {
                        Translio.hideProgress();
                        $btn.prop('disabled', false).html(originalHtml);
                        $('.translio-wc-status').text('Stopped. ' + current + '/' + attrIds.length);
                        return;
                    }

                    if (current >= attrIds.length) {
                        Translio.hideProgress();
                        $btn.prop('disabled', false).html(originalHtml);
                        $('.translio-wc-status').text('Complete! ' + (attrIds.length - errors) + '/' + attrIds.length);
                        return;
                    }

                    var attrId = attrIds[current];
                    var $row = $('tr[data-attr-id="' + attrId + '"]');
                    var original = $row.data('original');

                    Translio.updateProgress(current, attrIds.length, translioAdmin.strings.translating || 'Translating...');

                    $.post(translioAdmin.ajaxUrl, {
                        action: 'translio_translate_wc_attribute',
                        nonce: translioAdmin.nonce,
                        attr_id: attrId,
                        original: original
                    }, function(response) {
                        if (response.success && response.data.translation) {
                            $row.find('.translio-wc-attr-input').val(response.data.translation).trigger('input');
                            $row.css('background-color', '#d4edda');
                        } else {
                            errors++;
                        }
                    }).fail(function() {
                        errors++;
                    }).always(function() {
                        current++;
                        translateNext();
                    });
                }

                translateNext();
            });
        }
    };

    // Elementor list
    var TranslioElementor = {
        init: function() {
            // Elementor list doesn't need special handlers
        }
    };

    // Translate Elementor
    var TranslioTranslateElementor = {
        init: function() {
            // Auto-save on blur
            $('.translation-field').on('blur', function() {
                var $row = $(this).closest('tr');
                var $status = $row.find('.translio-save-status');
                var translated = $(this).val();

                if (!translated) return;

                $status.text(translioAdmin.strings.saving || 'Saving...').show();

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_save_elementor_translation',
                    nonce: translioAdmin.nonce,
                    post_id: $row.data('post-id'),
                    element_id: $row.data('element-id'),
                    field: $row.data('field'),
                    original: $row.find('.original-value').val(),
                    translated: translated
                }, function(response) {
                    if (response.success) {
                        $status.text(translioAdmin.strings.saved || 'Saved').css('color', 'green');
                        setTimeout(function() { $status.fadeOut(); }, 2000);
                    } else {
                        $status.text('Error').css('color', 'red');
                    }
                }).fail(function() {
                    $status.text('Error').css('color', 'red');
                });
            });

            // Single field translation
            $('.translate-elementor-field').on('click', function() {
                var $btn = $(this);
                var $row = $btn.closest('tr');
                var $field = $row.find('.translation-field');
                var $status = $row.find('.translio-save-status');

                $btn.prop('disabled', true);
                $status.text(translioAdmin.strings.translating || 'Translating...').css('color', '#666').show();

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_elementor_field',
                    nonce: translioAdmin.nonce,
                    post_id: $row.data('post-id'),
                    element_id: $row.data('element-id'),
                    field: $row.data('field'),
                    original: $row.find('.original-value').val()
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $field.val(response.data.translated);
                        $status.text(translioAdmin.strings.translated || 'Translated').css('color', 'green');
                        setTimeout(function() { $status.fadeOut(); }, 2000);
                    } else {
                        $status.text(response.data || 'Error').css('color', 'red');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.text('Error').css('color', 'red');
                });
            });

            // Translate all untranslated
            $('#translio-translate-elementor-all').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                var postId = $btn.data('post-id');

                // Find all empty translation fields
                var $emptyInputs = $('.translio-elementor-input').filter(function() {
                    return $(this).val() === '';
                });

                var total = $emptyInputs.length;

                if (total === 0) {
                    Translio.alert('All fields are already translated.', 'success');
                    return;
                }

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.showProgress(translioAdmin.strings.translating || 'Translating...', 0, total);

                var current = 0;

                function translateNext() {
                    if (current >= total) {
                        setTimeout(function() {
                            Translio.hideProgress();
                            $btn.prop('disabled', false).text(originalText);
                            $('.translio-save-status').text(translioAdmin.strings.complete || 'Complete!').fadeIn().delay(2000).fadeOut();
                        }, 300);
                        return;
                    }

                    var $input = $($emptyInputs[current]);
                    var $translateBtn = $input.closest('.translio-field-row').find('.translio-translate-elementor-field');

                    if ($translateBtn.length) {
                        // Trigger the translate button click
                        $translateBtn.trigger('click');

                        // Wait for translation to complete (check for value change)
                        var checkInterval = setInterval(function() {
                            if ($input.val() !== '' || !$translateBtn.prop('disabled')) {
                                clearInterval(checkInterval);
                                current++;
                                Translio.updateProgress(current, total, translioAdmin.strings.translating || 'Translating...');
                                setTimeout(translateNext, 200);
                            }
                        }, 100);

                        // Timeout after 10 seconds
                        setTimeout(function() {
                            clearInterval(checkInterval);
                            if ($input.val() === '') {
                                current++;
                                Translio.updateProgress(current, total, translioAdmin.strings.translating || 'Translating...');
                                translateNext();
                            }
                        }, 10000);
                    } else {
                        current++;
                        Translio.updateProgress(current, total, translioAdmin.strings.translating || 'Translating...');
                        translateNext();
                    }
                }

                translateNext();
            });
        }
    };

    // CF7 list
    var TranslioCF7 = {
        init: function() {
            TranslioUtils.initCheckboxes({
                selectAll: '#translio-select-all-cf7',
                checkboxes: '.translio-cf7-checkbox',
                countDisplay: '#translio-selected-cf7-count',
                button: '#translio-translate-selected-cf7'
            });

            // Translate selected forms
            $('#translio-translate-selected-cf7').on('click', function() {
                TranslioUtils.translateSequentially({
                    button: '#translio-translate-selected-cf7',
                    checkboxes: '.translio-cf7-checkbox',
                    statusDisplay: '#translio-cf7-status',
                    reloadOnComplete: true,
                    getData: function($checkbox) {
                        return {
                            action: 'translio_translate_cf7_all',
                            form_id: $checkbox.val()
                        };
                    },
                    onSuccess: function($row) {
                        $row.find('.translio-status').removeClass('translio-status-missing').addClass('translio-status-ok').text('Translated');
                    }
                });
            });
        }
    };

    // Translate CF7
    var TranslioTranslateCF7 = {
        init: function() {
            var formId = $('input[name="form_id"]').val() || new URLSearchParams(window.location.search).get('form_id');
            var saveTimeout;

            // Auto-save on input change
            $('.translio-cf7-input').on('input change', function() {
                var $input = $(this);
                var field = $input.data('field');
                var value = $input.val();

                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    var objectType = 'cf7_form';
                    var fieldName = field;

                    if (field.indexOf('mail_') === 0) {
                        objectType = 'cf7_mail';
                        fieldName = field.replace('mail_', '');
                    } else if (field.indexOf('message_') === 0) {
                        objectType = 'cf7_message';
                        fieldName = field.replace('message_', '');
                    }

                    $.post(translioAdmin.ajaxUrl, {
                        action: 'translio_save_translation',
                        nonce: translioAdmin.nonce,
                        object_id: formId,
                        object_type: objectType,
                        field: fieldName,
                        translation: value
                    }, function(response) {
                        if (response.success) {
                            $('.translio-save-status').text(translioAdmin.strings.saved || 'Saved').fadeIn().delay(1000).fadeOut();
                        }
                    });
                }, 500);
            });

            // Translate single CF7 field
            $('.translio-translate-cf7-field').on('click', function() {
                var $btn = $(this);
                var field = $btn.data('field');
                var $input = $('.translio-cf7-input[data-field="' + field + '"]');
                var original = $input.data('original');

                if (!original) return;

                $btn.prop('disabled', true).text('...');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_cf7_field',
                    nonce: translioAdmin.nonce,
                    form_id: formId,
                    field: field,
                    original: original
                }, function(response) {
                    if (response.success && response.data.translation) {
                        $input.val(response.data.translation).trigger('input');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                }).fail(function() {
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                });
            });

            // Translate all CF7 fields
            $('#translio-translate-cf7-all').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                var totalFields = $('.translio-cf7-input').length;

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.showProgress(translioAdmin.strings.translating || 'Translating...', 0, totalFields);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_cf7_all',
                    nonce: translioAdmin.nonce,
                    form_id: formId
                }, function(response) {
                    if (response.success && response.data.translations) {
                        var count = 0;
                        var translations = response.data.translations;
                        var keys = Object.keys(translations);

                        // Animate filling fields one by one
                        keys.forEach(function(field, index) {
                            setTimeout(function() {
                                $('.translio-cf7-input[data-field="' + field + '"]').val(translations[field]);
                                count++;
                                Translio.updateProgress(count, keys.length, translioAdmin.strings.translating || 'Translating...');

                                if (count === keys.length) {
                                    setTimeout(function() {
                                        Translio.hideProgress();
                                        $btn.prop('disabled', false).text(originalText);
                                        $('.translio-save-status').text(translioAdmin.strings.complete || 'Complete!').fadeIn().delay(2000).fadeOut();
                                    }, 300);
                                }
                            }, index * 100);
                        });

                        if (keys.length === 0) {
                            Translio.hideProgress();
                            $btn.prop('disabled', false).text(originalText);
                        }
                    } else {
                        Translio.hideProgress();
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                        $btn.prop('disabled', false).text(originalText);
                    }
                }).fail(function() {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        }
    };

    // ========================================
    // DIVI MODULE
    // ========================================
    var TranslioDivi = {
        init: function() {
            // Divi list page - no special handlers needed
        }
    };

    // Translate Divi
    var TranslioTranslateDivi = {
        init: function() {
            var $form = $('#translio-divi-form');
            if (!$form.length) return;

            var postId = $form.data('post-id');

            // Form submission - save translations
            $form.on('submit', function(e) {
                e.preventDefault();

                var translations = {};
                $('.translio-divi-input').each(function() {
                    var name = $(this).attr('name');
                    var match = name.match(/translio_divi\[([^\]]+)\]/);
                    if (match && $(this).val()) {
                        translations[match[1]] = $(this).val();
                    }
                });

                if (Object.keys(translations).length === 0) {
                    Translio.alert('No translations to save', 'info');
                    return;
                }

                Translio.showProgress('Saving translations...', 0, 0);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_save_divi_translations',
                    nonce: translioAdmin.nonce,
                    post_id: postId,
                    translations: translations
                }, function(response) {
                    Translio.hideProgress();
                    if (response.success) {
                        Translio.alert(response.data.message || 'Translations saved!', 'success');
                        // Remove needs-translation class
                        $('.translio-needs-translation').removeClass('translio-needs-translation');
                        $('.translio-needs-update').removeClass('translio-needs-update');
                        $('.translio-update-badge').remove();
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Save failed', 'error');
                    }
                }).fail(function() {
                    Translio.hideProgress();
                    Translio.alert('Request failed', 'error');
                });
            });

            // Translate single field
            $('.translio-translate-divi-field').on('click', function() {
                var $btn = $(this);
                var $row = $btn.closest('tr');
                var fieldId = $row.data('field-id');
                var $input = $row.find('.translio-divi-input');
                var $status = $row.find('.translio-field-status');

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_divi_field',
                    nonce: translioAdmin.nonce,
                    post_id: postId,
                    field_id: fieldId
                }, function(response) {
                    if (response.success && response.data.translation) {
                        $input.val(response.data.translation);
                        $row.removeClass('translio-needs-translation translio-needs-update');
                        $row.find('.translio-update-badge').remove();
                        $status.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span>');
                    } else {
                        $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span>');
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                    setTimeout(function() { $status.html(''); }, 2000);
                }).fail(function() {
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                    $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span>');
                });
            });

            // Translate all fields
            $('#translio-translate-all-divi').on('click', async function() {
                var $btn = $(this);
                var originalText = $btn.text();

                var untranslated = $('.translio-needs-translation, .translio-needs-update').length;
                var total = $('.translio-divi-input').length;

                if (total === 0) {
                    Translio.alert('No fields to translate', 'info');
                    return;
                }

                var message = 'Translate all ' + total + ' fields?';
                if (untranslated > 0 && untranslated < total) {
                    message = 'Translate all ' + total + ' fields (' + untranslated + ' need update)?';
                }

                var confirmed = await Translio.confirm(message);
                if (!confirmed) return;

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.showProgress('Translating Divi content...', 0, total);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_divi_all',
                    nonce: translioAdmin.nonce,
                    post_id: postId
                }, function(response) {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);

                    if (response.success && response.data.translations) {
                        var translations = response.data.translations;
                        var count = 0;

                        $.each(translations, function(fieldId, translated) {
                            var $row = $('tr[data-field-id="' + fieldId + '"]');
                            $row.find('.translio-divi-input').val(translated);
                            $row.removeClass('translio-needs-translation translio-needs-update');
                            $row.find('.translio-update-badge').remove();
                            count++;
                        });

                        Translio.alert('Translated ' + count + ' fields!', 'success');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                }).fail(function() {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);
                    Translio.alert('Request failed', 'error');
                });
            });
        }
    };

    // ========================================
    // AVADA MODULE
    // ========================================
    var TranslioAvada = {
        init: function() {
            // Avada list page - no special handlers needed
        }
    };

    // Translate Avada
    var TranslioTranslateAvada = {
        init: function() {
            var $form = $('#translio-avada-form');
            if (!$form.length) return;

            var postId = $form.data('post-id');

            // Form submission - save translations
            $form.on('submit', function(e) {
                e.preventDefault();

                var translations = {};
                $('.translio-avada-input').each(function() {
                    var name = $(this).attr('name');
                    var match = name.match(/translio_avada\[([^\]]+)\]/);
                    if (match && $(this).val()) {
                        translations[match[1]] = $(this).val();
                    }
                });

                if (Object.keys(translations).length === 0) {
                    Translio.alert('No translations to save', 'info');
                    return;
                }

                Translio.showProgress('Saving translations...', 0, 0);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_save_avada_translations',
                    nonce: translioAdmin.nonce,
                    post_id: postId,
                    translations: translations
                }, function(response) {
                    Translio.hideProgress();
                    if (response.success) {
                        Translio.alert(response.data.message || 'Translations saved!', 'success');
                        // Remove needs-translation class
                        $('.translio-needs-translation').removeClass('translio-needs-translation');
                        $('.translio-needs-update').removeClass('translio-needs-update');
                        $('.translio-update-badge').remove();
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Save failed', 'error');
                    }
                }).fail(function() {
                    Translio.hideProgress();
                    Translio.alert('Request failed', 'error');
                });
            });

            // Translate single field
            $('.translio-translate-avada-field').on('click', function() {
                var $btn = $(this);
                var $row = $btn.closest('tr');
                var fieldId = $row.data('field-id');
                var $input = $row.find('.translio-avada-input');
                var $status = $row.find('.translio-field-status');

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_avada_field',
                    nonce: translioAdmin.nonce,
                    post_id: postId,
                    field_id: fieldId
                }, function(response) {
                    if (response.success && response.data.translation) {
                        $input.val(response.data.translation);
                        $row.removeClass('translio-needs-translation translio-needs-update');
                        $row.find('.translio-update-badge').remove();
                        $status.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span>');
                    } else {
                        $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span>');
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                    setTimeout(function() { $status.html(''); }, 2000);
                }).fail(function() {
                    $btn.prop('disabled', false).text(translioAdmin.strings.translate || 'Translate');
                    $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span>');
                });
            });

            // Translate all fields
            $('#translio-translate-all-avada').on('click', async function() {
                var $btn = $(this);
                var originalText = $btn.text();

                var untranslated = $('.translio-needs-translation, .translio-needs-update').length;
                var total = $('.translio-avada-input').length;

                if (total === 0) {
                    Translio.alert('No fields to translate', 'info');
                    return;
                }

                var message = 'Translate all ' + total + ' fields?';
                if (untranslated > 0 && untranslated < total) {
                    message = 'Translate all ' + total + ' fields (' + untranslated + ' need update)?';
                }

                var confirmed = await Translio.confirm(message);
                if (!confirmed) return;

                $btn.prop('disabled', true).text(translioAdmin.strings.translating || 'Translating...');
                Translio.showProgress('Translating Avada content...', 0, total);

                $.post(translioAdmin.ajaxUrl, {
                    action: 'translio_translate_avada_all',
                    nonce: translioAdmin.nonce,
                    post_id: postId
                }, function(response) {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);

                    if (response.success && response.data.translations) {
                        var translations = response.data.translations;
                        var count = 0;

                        $.each(translations, function(fieldId, translated) {
                            var $row = $('tr[data-field-id="' + fieldId + '"]');
                            $row.find('.translio-avada-input').val(translated);
                            $row.removeClass('translio-needs-translation translio-needs-update');
                            $row.find('.translio-update-badge').remove();
                            count++;
                        });

                        Translio.alert('Translated ' + count + ' fields!', 'success');
                    } else {
                        Translio.alert(response.data && response.data.message ? response.data.message : 'Translation failed', 'error');
                    }
                }).fail(function() {
                    Translio.hideProgress();
                    $btn.prop('disabled', false).text(originalText);
                    Translio.alert('Request failed', 'error');
                });
            });
        }
    };

    // ========================================
    // FEEDBACK MODULE
    // ========================================
    var TranslioFeedback = {
        init: function() {
            this.addFeedbackButton();
            this.bindEvents();
        },

        addFeedbackButton: function() {
            // Find the page header h1
            var $header = $('.wrap > h1').first();
            if (!$header.length) return;

            // Check if button already exists
            if ($('.translio-feedback-btn').length) return;

            // Wrap header in flex container if not already wrapped
            if (!$header.parent().hasClass('translio-page-header')) {
                $header.wrap('<div class="translio-page-header"></div>');
            }

            // Add feedback button
            var $btn = $('<button type="button" class="translio-feedback-btn">' +
                '<span class="dashicons dashicons-feedback"></span>' +
                '<span>Feedback</span>' +
                '</button>');

            $header.parent().append($btn);
        },

        bindEvents: function() {
            var self = this;

            // Open modal on button click
            $(document).on('click', '.translio-feedback-btn', function(e) {
                e.preventDefault();
                self.openModal();
            });

            // Close modal
            $(document).on('click', '.translio-modal-close, .translio-modal-close-btn', function() {
                self.closeModal();
            });

            // Close on overlay click
            $(document).on('click', '.translio-modal-overlay', function() {
                self.closeModal();
            });

            // Close on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#translio-feedback-modal').is(':visible')) {
                    self.closeModal();
                }
            });

            // Character counter
            $(document).on('input', '#translio-feedback-message', function() {
                var len = $(this).val().length;
                $('#translio-char-current').text(len);
            });

            // Form submission
            $(document).on('submit', '#translio-feedback-form', function(e) {
                e.preventDefault();
                self.submitFeedback();
            });
        },

        openModal: function() {
            var $modal = $('#translio-feedback-modal');
            var $form = $('#translio-feedback-form');
            var $success = $('#translio-feedback-success');

            // Reset form state
            $form.show();
            $success.hide();
            $form[0].reset();
            $('#translio-char-current').text('0');

            // Pre-fill email with admin email
            if (typeof translioAdmin !== 'undefined' && translioAdmin.adminEmail) {
                $('#translio-feedback-email').val(translioAdmin.adminEmail);
            }

            // Show modal
            $modal.fadeIn(200);
            $('body').css('overflow', 'hidden');

            // Focus on message field
            setTimeout(function() {
                $('#translio-feedback-message').focus();
            }, 300);
        },

        closeModal: function() {
            $('#translio-feedback-modal').fadeOut(200);
            $('body').css('overflow', '');
        },

        submitFeedback: function() {
            var $form = $('#translio-feedback-form');
            var $btn = $form.find('button[type="submit"]');
            var $success = $('#translio-feedback-success');

            var email = $('#translio-feedback-email').val().trim();
            var message = $('#translio-feedback-message').val().trim();

            if (!email || !message) {
                Translio.alert('Please fill in all fields.', 'error');
                return;
            }

            // Disable button
            var originalText = $btn.text();
            $btn.prop('disabled', true).text(translioAdmin.strings.feedbackSending || 'Sending...');

            $.ajax({
                url: translioAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'translio_send_feedback',
                    nonce: translioAdmin.nonce,
                    email: email,
                    message: message
                }
            }).done(function(response) {
                if (response.success) {
                    // Show success message
                    $form.hide();
                    $success.fadeIn(200);
                } else {
                    Translio.alert(response.data.message || translioAdmin.strings.feedbackError, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            }).fail(function() {
                Translio.alert(translioAdmin.strings.feedbackError || 'Failed to send feedback.', 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        }
    };

    // ========================================
    // SETTINGS PAGE MODULE
    // ========================================
    var TranslioSettings = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;
            $('#translio-check-updates').on('click', function() {
                self.checkUpdates($(this));
            });
        },

        checkUpdates: function($btn) {
            var $status = $('#translio-update-status');
            var originalText = $btn.text();

            $btn.prop('disabled', true).text('Checking...');
            $status.html('<span style="color: #666;">Checking for updates...</span>');

            $.ajax({
                url: translioAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'translio_check_updates',
                    nonce: translioAdmin.nonce
                }
            }).done(function(response) {
                $btn.prop('disabled', false).text(originalText);

                if (response.success) {
                    var data = response.data;
                    if (data.update_available) {
                        $status.html(
                            '<span style="color: #d63638; font-weight: bold;">Update available: v' + data.latest_version + '</span> ' +
                            '<a href="' + translioAdmin.adminUrl + 'plugins.php" class="button button-small button-primary" style="margin-left: 5px;">Update now</a>'
                        );
                    } else {
                        $status.html('<span style="color: #00a32a;">✓ You have the latest version</span>');
                        setTimeout(function() { $status.fadeOut(300); }, 3000);
                    }
                } else {
                    $status.html('<span style="color: #d63638;">Error: ' + (response.data.message || 'Unknown error') + '</span>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text(originalText);
                $status.html('<span style="color: #d63638;">Failed to check for updates</span>');
            });
        }
    };

    // ========================================
    // INITIALIZATION
    // ========================================
    window.TranslioAdmin = Translio;
    window.TranslioUtils = TranslioUtils;

    function initTranslio() {
        if (Translio._initialized) return;
        Translio._initialized = true;
        Translio.init();
        TranslioFeedback.init();
    }

    $(document).ready(initTranslio);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTranslio);
    } else {
        initTranslio();
    }

    setTimeout(initTranslio, 100);

})(jQuery);
