/**
 * Open Cookie Consent - UI Module
 * Handles banner, preferences modal, floating icon, and updated notice
 */

(function() {
    'use strict';
    
    class UI {
        constructor() {
            this.banner = null;
            this.bannerOverlay = null;
            this.preferencesModal = null;
            this.floatingIcon = null;
            this.updatedNotice = null;
            this.focusTrap = null;
            this.isPreferencesOpen = false;
            this.hasInteracted = false;
            
            this.init();
        }
        
        init() {
            this.banner = document.getElementById('occ-banner');
            this.bannerOverlay = document.getElementById('occ-banner-overlay');
            this.preferencesModal = document.getElementById('occ-preferences-modal');
            this.floatingIcon = document.getElementById('occ-floating-icon');
            this.updatedNotice = document.getElementById('occ-updated-notice');
            
            this.bindEvents();
            this.checkInitialDisplay();
        }
        
        bindEvents() {
            this.bindBannerEvents();
            this.bindPreferencesEvents();
            this.bindFloatingIconEvents();
            this.bindUpdatedNoticeEvents();
            this.bindKeyboardEvents();
        }
        
        bindBannerEvents() {
            if (!this.banner) return;
            
            const acceptAllBtn = document.getElementById('occ-accept-all');
            const rejectBtn = document.getElementById('occ-reject-nonessential');
            const preferencesBtn = document.getElementById('occ-open-preferences');
            
            if (acceptAllBtn) {
                acceptAllBtn.addEventListener('click', () => this.handleAcceptAll('banner'));
            }
            
            if (rejectBtn) {
                rejectBtn.addEventListener('click', () => this.handleRejectNonEssential('banner'));
            }
            
            if (preferencesBtn) {
                preferencesBtn.addEventListener('click', () => {
                    this.openPreferences();
                });
            }
        }
        
        bindPreferencesEvents() {
            if (!this.preferencesModal) return;
            
            const closeBtn = document.getElementById('occ-close-preferences');
            const acceptAllBtn = document.getElementById('occ-accept-all-preferences');
            const rejectBtn = document.getElementById('occ-reject-nonessential-preferences');
            const saveBtn = document.getElementById('occ-save-preferences');
            const backdrop = this.preferencesModal.querySelector('.occ-modal-backdrop');
            
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closePreferences());
            }
            
            if (backdrop) {
                backdrop.addEventListener('click', () => this.closePreferences());
            }
            
            if (acceptAllBtn) {
                acceptAllBtn.addEventListener('click', () => this.handleAcceptAll('modal'));
            }
            
            if (rejectBtn) {
                rejectBtn.addEventListener('click', () => this.handleRejectNonEssential('modal'));
            }
            
            if (saveBtn) {
                saveBtn.addEventListener('click', () => this.handleSavePreferences());
            }
            
            this.bindToggleEvents();
        }
        
        bindToggleEvents() {
            const toggles = this.preferencesModal.querySelectorAll('.occ-toggle[data-category]');
            const labels = this.preferencesModal.querySelectorAll('.occ-category-label');
            labels.forEach(label => {
                label.addEventListener('click', (e) => {
                    // Toggle when clicking anywhere on the label row, without submitting or navigating
                    e.preventDefault();
                    e.stopPropagation();
                    const container = label.closest('.occ-category');
                    if (!container) return;
                    const toggle = container.querySelector('.occ-toggle[data-category]');
                    if (!toggle) return; // locked/necessary has no checkbox
                    if (toggle.getAttribute('aria-disabled') === 'true') {
                        return;
                    }
                    const checkbox = toggle.querySelector('input[type="checkbox"]');
                    if (!checkbox || checkbox.disabled) return;
                    checkbox.checked = !checkbox.checked;
                    const isChecked = checkbox.checked;
                    const category = toggle.getAttribute('data-category');
                    toggle.setAttribute('aria-checked', isChecked.toString());
                    window.occ.consent.set(category, isChecked ? 'granted' : 'denied');
                });
            });
            
            toggles.forEach(toggle => {
                const checkbox = toggle.querySelector('input[type="checkbox"]');
                
                const handleToggle = () => {
                    const category = toggle.getAttribute('data-category');
                    const isChecked = checkbox.checked;
                    const newState = isChecked ? 'granted' : 'denied';
                    
                    toggle.setAttribute('aria-checked', isChecked.toString());
                    window.occ.consent.set(category, newState);
                };
                
                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (toggle.getAttribute('aria-disabled') === 'true' || checkbox.disabled) {
                        return;
                    }
                    if (e.target !== checkbox) {
                        checkbox.checked = !checkbox.checked;
                        handleToggle();
                    }
                });
                
                toggle.addEventListener('keydown', (e) => {
                    if (toggle.getAttribute('aria-disabled') === 'true' || checkbox.disabled) {
                        return;
                    }
                    if (e.key === ' ' || e.key === 'Enter') {
                        e.preventDefault();
                        checkbox.checked = !checkbox.checked;
                        handleToggle();
                    }
                });
                
                checkbox.addEventListener('change', (e) => {
                    if (checkbox.disabled) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    handleToggle();
                });
            });
        }
        
        bindFloatingIconEvents() {
            if (!this.floatingIcon) return;
            
            const reopenBtn = document.getElementById('occ-reopen-preferences');
            if (reopenBtn) {
                reopenBtn.addEventListener('click', () => this.openPreferences());
            }
        }
        
        bindUpdatedNoticeEvents() {
            if (!this.updatedNotice) return;
            
            const acceptAllBtn = document.getElementById('occ-updated-accept-all');
            const reviewBtn = document.getElementById('occ-updated-review');
            const keepBtn = document.getElementById('occ-updated-keep');
            
            if (acceptAllBtn) {
                acceptAllBtn.addEventListener('click', () => this.handleUpdatedAcceptAll());
            }
            
            if (reviewBtn) {
                reviewBtn.addEventListener('click', () => this.handleUpdatedReview());
            }
            
            if (keepBtn) {
                keepBtn.addEventListener('click', () => this.handleUpdatedKeep());
            }
        }
        
        bindKeyboardEvents() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.isPreferencesOpen) {
                        this.closePreferences();
                    } else if (this.updatedNotice && this.updatedNotice.style.display !== 'none') {
                        this.handleUpdatedKeep();
                    }
                }
            });
        }
        
        checkInitialDisplay() {
            const hasNonEssential = window.occData?.inventory?.hasNonEssential;
            const conditionalBanner = window.occData?.settings?.ui?.conditional_banner !== false;
            
            if (hasNonEssential || !conditionalBanner) {
                if (!this.hasInteracted && !window.occ.consent.get().timestamp) {
                    this.showBanner();
                } else {
                    this.showFloatingIcon();
                }
            } else {
                this.showFloatingIcon();
            }
            
            if (window.occ.consent.hasVersionMismatch()) {
                this.showUpdatedNotice();
            }
        }
        
        showBanner() {
            if (!this.banner) return;

            if (this.bannerOverlay) {
                this.bannerOverlay.style.display = 'block';
                this.bannerOverlay.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => {
                    this.bannerOverlay.classList.add('occ-visible');
                });
            }

            this.banner.style.display = 'block';
            this.banner.setAttribute('aria-hidden', 'false');

            requestAnimationFrame(() => {
                this.banner.classList.add('occ-visible');
                this.focusBanner();
            });
        }

        hideBanner() {
            if (this.banner) {
                this.banner.classList.remove('occ-visible');
                this.banner.setAttribute('aria-hidden', 'true');

                setTimeout(() => {
                    this.banner.style.display = 'none';
                }, 300);
            }

            if (this.bannerOverlay) {
                this.bannerOverlay.classList.remove('occ-visible');
                this.bannerOverlay.setAttribute('aria-hidden', 'true');
                setTimeout(() => {
                    this.bannerOverlay.style.display = 'none';
                }, 300);
            }
        }
        
        openPreferences() {
            if (!this.preferencesModal) return;

            this.hideBanner();

            this.isPreferencesOpen = true;
            this.syncPreferencesWithState();
            
            this.preferencesModal.style.display = 'flex';
            this.preferencesModal.setAttribute('aria-hidden', 'false');
            
            requestAnimationFrame(() => {
                this.preferencesModal.classList.add('occ-visible');
                this.setupFocusTrap(this.preferencesModal);
                this.focusPreferences();
            });
            
            document.body.style.overflow = 'hidden';
        }
        
        closePreferences() {
            if (!this.preferencesModal || !this.isPreferencesOpen) return;
            
            this.isPreferencesOpen = false;
            this.preferencesModal.classList.remove('occ-visible');
            this.preferencesModal.setAttribute('aria-hidden', 'true');
            
            setTimeout(() => {
                this.preferencesModal.style.display = 'none';
                this.releaseFocusTrap();
            }, 300);
            
            document.body.style.overflow = '';
        }
        
        showFloatingIcon() {
            if (!this.floatingIcon) return;
            
            this.floatingIcon.style.display = 'block';
            this.floatingIcon.setAttribute('aria-hidden', 'false');
            
            requestAnimationFrame(() => {
                this.floatingIcon.classList.add('occ-visible');
            });
        }
        
        maybeReload(reset = true) {
            if (window.occ?.consent && typeof window.occ.consent.shouldReload === 'function') {
                if (window.occ.consent.shouldReload(reset)) {
                    window.location.reload();
                }
            }
        }

        showUpdatedNotice() {
            if (!this.updatedNotice) return;
            
            this.updatedNotice.style.display = 'block';
            this.updatedNotice.setAttribute('aria-hidden', 'false');
            
            requestAnimationFrame(() => {
                this.updatedNotice.classList.add('occ-visible');
                this.focusUpdatedNotice();
            });
        }
        
        hideUpdatedNotice() {
            if (!this.updatedNotice) return;
            
            this.updatedNotice.classList.remove('occ-visible');
            this.updatedNotice.setAttribute('aria-hidden', 'true');
            
            setTimeout(() => {
                this.updatedNotice.style.display = 'none';
            }, 300);
        }
        
        syncPreferencesWithState() {
            const state = window.occ.consent.get();
            const toggles = this.preferencesModal.querySelectorAll('.occ-toggle[data-category]');
            
            toggles.forEach(toggle => {
                const category = toggle.getAttribute('data-category');
                const checkbox = toggle.querySelector('input[type="checkbox"]');
                const isGranted = state.choices[category] === 'granted';
                
                if (checkbox) {
                    checkbox.checked = isGranted;
                    toggle.setAttribute('aria-checked', isGranted.toString());
                }
            });
        }
        
        handleAcceptAll(source) {
            window.occ.consent.grantAllNonNecessary();
            window.occ.consent.updateVersion(window.occInventoryVersion || '');
            window.occ.consent.sendConsentUpdate('accept_all', source);
            window.occ.consent.emitEvent('occ:accept_all', { source });
            
            this.hasInteracted = true;
            this.hideBanner();
            this.closePreferences();
            this.showFloatingIcon();
            
            this.announceToScreenReader(__('all_cookies_accepted'));
        }
        
        handleRejectNonEssential(source) {
            window.occ.consent.rejectAllNonEssential();
            window.occ.consent.updateVersion(window.occInventoryVersion || '');
            window.occ.consent.sendConsentUpdate('reject_nonessential', source);
            window.occ.consent.emitEvent('occ:reject_nonessential', { source });
            
            this.hasInteracted = true;
            this.hideBanner();
            this.closePreferences();
            this.showFloatingIcon();
            
            this.announceToScreenReader(__('non_essential_cookies_rejected'));
            // Force immediate script revocation without reload
            this.revokeNonEssentialScripts();
        }
        
        handleSavePreferences() {
            window.occ.consent.updateVersion(window.occInventoryVersion || '');
            window.occ.consent.sendConsentUpdate('save_preferences', 'modal');
            window.occ.consent.emitEvent('occ:preferences_saved', { source: 'modal' });
            
            this.hasInteracted = true;
            this.hideBanner();
            this.closePreferences();
            this.showFloatingIcon();
            
            this.announceToScreenReader(__('cookie_preferences_saved'));
            // Check if scripts need revocation after preference changes
            this.checkScriptRevocation();
        }
        
        handleUpdatedAcceptAll() {
            window.occ.consent.grantAllNonNecessary();
            window.occ.consent.updateVersion(window.occInventoryVersion || '');
            window.occ.consent.sendConsentUpdate('accept_all', 'updated_notice');
            window.occ.consent.emitEvent('occ:updated_accept_all', { source: 'updated_notice' });
            
            this.hideUpdatedNotice();
            this.announceToScreenReader(__('all_cookies_accepted'));
            if (window.occ?.consent && typeof window.occ.consent.shouldReload === 'function') {
                window.occ.consent.shouldReload(false);
            }
        }
        
        handleUpdatedReview() {
            window.occ.consent.emitEvent('occ:updated_review', { source: 'updated_notice' });
            window.occ.consent.sendConsentUpdate('review', 'updated_notice');
            
            this.hideUpdatedNotice();
            this.openPreferences();
        }
        
        handleUpdatedKeep() {
            window.occ.consent.updateVersion(window.occInventoryVersion || '');
            window.occ.consent.sendConsentUpdate('keep_current', 'updated_notice');
            window.occ.consent.emitEvent('occ:updated_keep', { source: 'updated_notice' });
            
            this.hideUpdatedNotice();
            this.announceToScreenReader(__('current_settings_kept'));
            this.maybeReload();
        }
        
        focusBanner() {
            const firstButton = this.banner.querySelector('button');
            if (firstButton) {
                firstButton.focus();
            }
        }
        
        focusPreferences() {
            const firstFocusable = this.preferencesModal.querySelector('button, input, [tabindex]:not([tabindex="-1"])');
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }
        
        focusUpdatedNotice() {
            const initialFocusBtn = this.updatedNotice.querySelector('[data-initial-focus="true"]');
            if (initialFocusBtn) {
                initialFocusBtn.focus();
            } else {
                const firstButton = this.updatedNotice.querySelector('button');
                if (firstButton) {
                    firstButton.focus();
                }
            }
        }
        
        setupFocusTrap(container) {
            const focusableElements = container.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            if (focusableElements.length === 0) return;
            
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            this.focusTrap = (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                }
            };
            
            document.addEventListener('keydown', this.focusTrap);
        }
        
        releaseFocusTrap() {
            if (this.focusTrap) {
                document.removeEventListener('keydown', this.focusTrap);
                this.focusTrap = null;
            }
        }
        
        announceToScreenReader(message) {
            const announcement = document.createElement('div');
            announcement.setAttribute('aria-live', 'polite');
            announcement.setAttribute('aria-atomic', 'true');
            announcement.style.position = 'absolute';
            announcement.style.left = '-10000px';
            announcement.style.width = '1px';
            announcement.style.height = '1px';
            announcement.style.overflow = 'hidden';
            
            document.body.appendChild(announcement);
            announcement.textContent = message;
            
            setTimeout(() => {
                document.body.removeChild(announcement);
            }, 1000);
        }
        
        revokeNonEssentialScripts() {
            // Force immediate revocation of non-essential scripts
            const result = window.occ.consent.applyGating();
            if (result.disabled > 0) {
                console.log(`OCC: Revoked ${result.disabled} non-essential scripts`);
            }
            
            // Clear all non-essential cookies
            this.clearNonEssentialCookies();
        }
        
        checkScriptRevocation() {
            // Check if any scripts need to be revoked based on preference changes
            const result = window.occ.consent.applyGating();
            if (result.disabled > 0) {
                console.log(`OCC: Revoked ${result.disabled} scripts after preference change`);
                this.clearNonEssentialCookies();
            }
        }
        
        clearNonEssentialCookies() {
            // Simple and effective: clear all cookies when consent is revoked
            console.log('OCC: Clearing all cookies due to consent revocation');
            this.clearAllCookies();
        }
        
        clearAllCookies() {
            // Get all current cookies before deletion
            const beforeCookies = document.cookie.split(';').map(c => c.split('=')[0].trim()).filter(n => n);
            console.log('OCC: Cookies before clearing:', beforeCookies);
            
            // WordPress essential cookies that should be preserved
            const essentialCookies = [
                'wordpress_logged_in_',
                'wordpress_sec_',
                'wp-settings-',
                'wp-settings-time-',
                'wordpressuser_',
                'wordpresspass_',
                'wp_lang',
                'PHPSESSID',
                'occ_consent_v1' // Our own consent state
            ];
            
            beforeCookies.forEach(cookieName => {
                // Skip if it's an essential cookie
                const isEssential = essentialCookies.some(essential => 
                    cookieName.startsWith(essential)
                );
                
                if (!isEssential && cookieName) {
                    console.log(`OCC: Attempting to delete cookie: ${cookieName}`);
                    this.deleteAllCookieVariations(cookieName);
                }
            });
            
            // Check what's left after deletion attempt
            setTimeout(() => {
                const afterCookies = document.cookie.split(';').map(c => c.split('=')[0].trim()).filter(n => n);
                const remainingNonEssential = afterCookies.filter(name => 
                    !essentialCookies.some(essential => name.startsWith(essential))
                );
                
                if (remainingNonEssential.length > 0) {
                    console.warn('OCC: Failed to delete these cookies:', remainingNonEssential);
                } else {
                    console.log('OCC: All non-essential cookies successfully cleared');
                }
            }, 500);
        }
        
        getCookie(name) {
            return document.cookie.split(';').some(c => {
                return c.trim().startsWith(name + '=');
            });
        }
        
        deleteCookie(name, path, domain) {
            const beforeExists = this.getCookie(name);
            if (beforeExists) {
                document.cookie = name + "=" +
                    ((path) ? ";path=" + path : "") +
                    ((domain) ? ";domain=" + domain : "") +
                    ";expires=Thu, 01 Jan 1970 00:00:01 GMT";
                
                // Check if it worked
                setTimeout(() => {
                    const afterExists = this.getCookie(name);
                    if (!afterExists) {
                        console.log(`OCC: ✅ Successfully deleted cookie ${name} with path=${path||'/'} domain=${domain||'none'}`);
                    } else {
                        console.log(`OCC: ❌ Failed to delete cookie ${name} with path=${path||'/'} domain=${domain||'none'}`);
                    }
                }, 10);
            }
        }
        
        deleteAllCookieVariations(name) {
            // Try the most common combinations since we don't know exact path/domain
            const hostname = window.location.hostname;
            const paths = ['/', window.location.pathname, ''];
            
            // Start with basic domains
            const domains = [
                '', // No domain (most common for same-origin cookies)
                hostname, // exact hostname (example.com, subdomain.example.com)
                '.' + hostname, // .hostname (works for subdomains too)
            ];
            
            // Add root domain variations for subdomains
            const parts = hostname.split('.');
            if (parts.length >= 3) {
                // Handle different TLD patterns
                let rootDomain;
                
                // UK/Australian style domains (.co.uk, .com.au, etc.)
                const secondLevelTlds = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac'];
                if (parts.length >= 4 && 
                    secondLevelTlds.includes(parts[parts.length - 2]) && 
                    parts[parts.length - 1].length === 2) {
                    // Take last 3 parts: subdomain.example.co.uk -> example.co.uk
                    rootDomain = parts.slice(-3).join('.');
                } else {
                    // Regular TLD: subdomain.example.com -> example.com
                    rootDomain = parts.slice(-2).join('.');
                }
                
                // Only add if different from hostname
                if (rootDomain !== hostname) {
                    domains.push(rootDomain, '.' + rootDomain);
                }
            }
            
            // Add common Google domains for cookies set by external scripts
            // (though this is less common now due to same-site policies)
            if (name.startsWith('_g') || name.includes('google') || name.includes('goog')) {
                domains.push('.google.com', 'google.com', '.doubleclick.net', 'doubleclick.net');
            }
            
            console.log(`OCC: Trying to delete ${name} with domains:`, domains, 'and paths:', paths);
            
            // Try all reasonable combinations
            paths.forEach(path => {
                domains.forEach(domain => {
                    this.deleteCookie(name, path, domain || undefined);
                });
            });
        }
    }
    
    window.occ = window.occ || {};
    
    window.occ.ui = {
        instance: null,
        
        init() {
            if (!this.instance) {
                this.instance = new UI();
            }
            return this.instance;
        },
        
        showBanner() {
            this.init().showBanner();
        },
        
        hideBanner() {
            this.init().hideBanner();
        },
        
        openPreferences() {
            this.init().openPreferences();
        },
        
        closePreferences() {
            this.init().closePreferences();
        },
        
        maybeReload(reset = true) {
            if (window.occ?.consent && typeof window.occ.consent.shouldReload === 'function') {
                if (window.occ.consent.shouldReload(reset)) {
                    window.location.reload();
                }
            }
        },

        showUpdatedNotice() {
            this.init().showUpdatedNotice();
        }
    };
    
    document.addEventListener('DOMContentLoaded', function() {
        window.occ.ui.init();
    });
    
    function __(key) {
        return window.occData?.strings?.[key] || key;
    }
    
    window.__ = __;
    
})();

