/**
 * Revclar landing page — language detection, switcher, mobile nav, form handling.
 *
 * Language detection:
 *   1. Manual switcher preference (stored in localStorage).
 *   2. IP geolocation via https://ipapi.co/json/ (free, no API key).
 *      Country code "RO" → Romanian, anything else → English.
 *   3. Browser language (navigator.language) as fallback if ipapi fails or times out.
 */

(function () {
    const SUPPORTED_LANGUAGES = ['en', 'ro'];
    const STORAGE_KEY = 'revclar-language';
    const IP_API_URL = 'https://ipapi.co/json/';
    const IP_TIMEOUT_MS = 1500;

    function getStoredLanguage() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function setStoredLanguage(lang) {
        try {
            localStorage.setItem(STORAGE_KEY, lang);
        } catch (e) {
            // Ignore private-mode errors.
        }
    }

    function getBrowserLanguage() {
        const raw = navigator.language || navigator.languages?.[0] || 'en';
        const code = raw.slice(0, 2).toLowerCase();
        return SUPPORTED_LANGUAGES.includes(code) ? code : 'en';
    }

    async function detectLanguageByIp() {
        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), IP_TIMEOUT_MS);

            const response = await fetch(IP_API_URL, { signal: controller.signal });
            clearTimeout(timeout);

            if (!response.ok) {
                throw new Error('IP geolocation service returned ' + response.status);
            }

            const data = await response.json();
            const countryCode = String(data.country_code || '').toLowerCase();

            return countryCode === 'ro' ? 'ro' : 'en';
        } catch (e) {
            // Fall back to browser language silently.
            return getBrowserLanguage();
        }
    }

    async function initLanguage() {
        let lang = getStoredLanguage();

        if (!lang) {
            lang = await detectLanguageByIp();
            setStoredLanguage(lang);
        }

        applyLanguage(lang);
        updateLanguageSwitcher(lang);
    }

    function applyLanguage(lang) {
        if (!SUPPORTED_LANGUAGES.includes(lang)) {
            lang = 'en';
        }

        document.documentElement.lang = lang === 'ro' ? 'ro' : 'en';

        document.querySelectorAll('[data-i18n]').forEach(function (element) {
            const key = element.getAttribute('data-i18n');
            const translation = element.getAttribute('data-i18n-' + lang);

            if (translation !== null) {
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    if (element.hasAttribute('placeholder')) {
                        element.setAttribute('placeholder', translation);
                    } else {
                        element.value = translation;
                    }
                } else if (element.tagName === 'TITLE') {
                    element.textContent = translation;
                } else {
                    element.innerHTML = translation;
                }
            }
        });

        document.querySelectorAll('[data-i18n-visible]').forEach(function (element) {
            const visibleFor = element.getAttribute('data-i18n-visible');
            element.style.display = visibleFor === lang ? '' : 'none';
        });
    }

    function updateLanguageSwitcher(activeLang) {
        document.querySelectorAll('.lang-switcher button').forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-lang') === activeLang);
        });
    }

    function bindLanguageSwitcher() {
        document.querySelectorAll('.lang-switcher button').forEach(function (button) {
            button.addEventListener('click', function () {
                const lang = button.getAttribute('data-lang');
                setStoredLanguage(lang);
                applyLanguage(lang);
                updateLanguageSwitcher(lang);
            });
        });
    }

    function bindMobileMenu() {
        const toggle = document.querySelector('.mobile-menu-btn');
        const mobileNav = document.querySelector('.mobile-nav');

        if (!toggle || !mobileNav) {
            return;
        }

        toggle.addEventListener('click', function () {
            mobileNav.classList.toggle('open');
        });

        mobileNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                mobileNav.classList.remove('open');
            });
        });
    }

    function bindContactForm() {
        const form = document.querySelector('.contact-form');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            // Honeypot: if the hidden field is filled, silently abort.
            const honeypot = form.querySelector('.honeypot input');
            if (honeypot && honeypot.value) {
                event.preventDefault();
                return;
            }

            // The form action is a mailto: link. Let the browser open the user's
            // email client. If you replace this with a form endpoint, remove this handler.
            const emailPlaceholder = form.getAttribute('data-contact-email') || 'contact@example.com';
            const name = encodeURIComponent(form.querySelector('[name="name"]').value);
            const email = encodeURIComponent(form.querySelector('[name="email"]').value);
            const phone = encodeURIComponent(form.querySelector('[name="phone"]').value);
            const message = encodeURIComponent(form.querySelector('[name="message"]').value);

            const subject = encodeURIComponent('Contact request via bookings.revclar.com');
            const body = encodeURIComponent(
                'Name: ' + decodeURIComponent(name) + '\n' +
                'Email: ' + decodeURIComponent(email) + '\n' +
                'Phone: ' + decodeURIComponent(phone) + '\n\n' +
                'Message:\n' + decodeURIComponent(message)
            );

            form.setAttribute('action', 'mailto:' + emailPlaceholder + '?subject=' + subject + '&body=' + body);
        });
    }

    function initZoom() {
        const main = document.querySelector('main');
        const toggle = document.getElementById('zoom-toggle');
        const drawer = document.getElementById('zoom-drawer');
        const backdrop = document.getElementById('zoom-backdrop');
        const closeBtn = document.getElementById('zoom-drawer-close');
        const options = document.querySelectorAll('.zoom-option');

        if (!main || !toggle || !drawer || !backdrop || !options.length) {
            return;
        }

        let currentZoom = 80;

        function applyZoom(zoom) {
            currentZoom = zoom;

            main.classList.remove('zoom-100', 'zoom-80', 'zoom-60', 'zoom-40');
            main.classList.add('zoom-' + zoom);

            updateActiveOption();
        }

        function updateActiveOption() {
            options.forEach(function (option) {
                const zoom = parseInt(option.getAttribute('data-zoom'), 10);
                const isActive = zoom === currentZoom;
                option.classList.toggle('is-active', isActive);
                option.setAttribute('aria-pressed', String(isActive));
            });
        }

        function openDrawer() {
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            backdrop.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function closeDrawer() {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            backdrop.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            if (drawer.classList.contains('open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });

        closeBtn?.addEventListener('click', closeDrawer);
        backdrop.addEventListener('click', closeDrawer);

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                const zoom = parseInt(option.getAttribute('data-zoom'), 10);
                applyZoom(zoom);
                closeDrawer();
            });
        });

        applyZoom(80);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLanguage();
        bindLanguageSwitcher();
        bindMobileMenu();
        bindContactForm();
        initZoom();
    });
})();
