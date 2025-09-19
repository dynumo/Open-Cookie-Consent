/**
 * Open Cookie Consent - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initTabSwitching();
        initFormHandling();
        initScanActions();
        initImportExport();
        initConditionalFields();
        initColorPicker();
    });
    
    function initTabSwitching() {
        $('.nav-tab').on('click', function(e) {
            const href = $(this).attr('href');
            if (href && href.indexOf('#') === -1) {
                return true;
            }
            
            e.preventDefault();
            const target = href.replace('#', '');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.occ-tab-content').hide();
            $('#' + target).show();
        });
    }
    
    function initFormHandling() {
        $('#occ-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('input[type="submit"]');
            const originalText = $submitBtn.val();
            
            $submitBtn.val(occAdmin.strings.saving || 'Saving...').prop('disabled', true);
            
            const formData = new FormData(this);
            formData.append('action', 'occ_save_settings');
            formData.append('nonce', occAdmin.nonce);
            formData.append('tab', $form.data('tab'));
            
            $.ajax({
                url: occAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                    } else {
                        showNotice(response.data.message || occAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotice(occAdmin.strings.error, 'error');
                },
                complete: function() {
                    $submitBtn.val(originalText).prop('disabled', false);
                }
            });
        });
    }
    
    function initScanActions() {
        $('#occ-run-scan').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.text(occAdmin.strings.scanning || 'Scanning...').prop('disabled', true);
            $('#occ-scan-status').html('<div class="notice notice-info"><p>' + (occAdmin.strings.scanning || 'Scanning...') + '</p></div>');
            
            $.ajax({
                url: occAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'occ_manual_scan',
                    nonce: occAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(occAdmin.strings.scan_complete || 'Scan completed', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotice(response.data.message || 'Scan failed', 'error');
                    }
                },
                error: function() {
                    showNotice('Scan failed', 'error');
                },
                complete: function() {
                    $btn.text(originalText).prop('disabled', false);
                    $('#occ-scan-status').empty();
                }
            });
        });
        
        $('#occ-update-ocd').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.text('Updating...').prop('disabled', true);
            
            $.ajax({
                url: occAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'occ_update_ocd',
                    nonce: occAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                    } else {
                        showNotice(response.data.message || 'Update failed', 'error');
                    }
                },
                error: function() {
                    showNotice('Update failed', 'error');
                },
                complete: function() {
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        });
        
        $('#occ-clear-cache').on('click', function() {
            if (confirm('Are you sure you want to clear all stored consent data?')) {
                localStorage.removeItem('occ_consent_v1');
                showNotice('Consent cache cleared', 'success');
            }
        });

        $('#occ-reset-settings').on('click', function() {
            if (!confirm('Reset all settings to defaults? This cannot be undone.')) { return; }
            $.post(occAdmin.ajaxUrl, {
                action: 'occ_reset_settings',
                nonce: occAdmin.nonce
            }).done(function(resp){
                if (resp && resp.success) {
                    showNotice(resp.data && resp.data.message ? resp.data.message : 'Settings reset', 'success');
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    showNotice((resp && resp.data && resp.data.message) || 'Reset failed', 'error');
                }
            }).fail(function(){
                showNotice('Reset failed', 'error');
            });
        });
    }
    
    function initImportExport() {
        $('#occ-export-settings').on('click', function() {
            window.location.href = occAdmin.ajaxUrl + '?action=occ_export_settings&nonce=' + occAdmin.nonce;
        });
        
        $('#occ-import-settings').on('click', function() {
            const fileInput = document.getElementById('occ-import-file');
            const file = fileInput.files[0];
            
            if (!file) {
                showNotice('Please select a file to import', 'error');
                return;
            }
            
            if (!file.name.endsWith('.json')) {
                showNotice('Please select a JSON file', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'occ_import_settings');
            formData.append('nonce', occAdmin.nonce);
            formData.append('settings_file', file);
            
            const $btn = $(this);
            const originalText = $btn.text();
            $btn.text('Importing...').prop('disabled', true);
            
            $.ajax({
                url: occAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    showNotice('Import failed', 'error');
                },
                complete: function() {
                    $btn.text(originalText).prop('disabled', false);
                    fileInput.value = '';
                }
            });
        });
    }
    
    function initConditionalFields() {
        $('#occ-scan-interval').on('change', function() {
            const interval = $(this).val();
            
            $('#occ-daily-time-row').toggle(interval === 'daily');
            $('#occ-custom-cron-row').toggle(interval === 'custom');
        });
        
        // Counters removed: no conditional fields to toggle
    }
    
    function initColorPicker() {
        if (typeof $.fn.wpColorPicker !== 'undefined') {
            $('.occ-color-picker').wpColorPicker();
        }
    }
    
    function showNotice(message, type) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.occ-admin h1').after($notice);
        
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut();
        });
        
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        $('html, body').animate({
            scrollTop: 0
        }, 300);
    }
    
    window.occAdminShowNotice = showNotice;
    
})(jQuery);
