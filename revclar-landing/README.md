# Revclar landing page

Self-contained, portable landing page for `bookings.revclar.com`.

## Folder structure

```
revclar-landing/
├── index.html          # Single-page bilingual site
├── css/
│   └── style.css       # All styles, no external dependencies
├── js/
│   └── main.js         # Language detection, switcher, mobile nav, form handling
├── assets/
│   └── favicon.svg     # Placeholder favicon
└── README.md           # This file
```

## Portability

This folder has **no external dependencies**. It does not link to the parent
appointment platform's PHP runtime, routes, CSS, JS, or images. You can copy the
entire `revclar-landing/` folder into any static hosting environment and it will
work.

## Style references used

The following platform files were used as the design reference. All needed
tokens (colors, spacing, border radius, shadows, typography) were copied into
`css/style.css` so the folder remains standalone:

- `application/views/components/revclar_theme_style.php`
- `assets/css/backend.scss`
- `assets/css/themes/default.scss`
- `assets/css/general.scss`
- `assets/css/frontend.scss`

The landing page uses the platform's default public theme palette: teal primary
(`#429A82`), blue accent (`#5C9DC0`), light surfaces, and rounded cards.

## Language detection

The page detects the visitor's preferred language automatically:

1. **Manual switcher preference** — stored in `localStorage` (`revclar-language`).
2. **IP geolocation** — a lightweight call to `https://ipapi.co/json/` with a
   1.5-second timeout. If the country code is `RO`, the page loads in Romanian;
   otherwise it loads in English.
3. **Browser fallback** — if ipapi fails or times out, `navigator.language` is
   used.

**No API key is required** for the ipapi free endpoint. For high-traffic or
production use, consider ipapi's paid plan or move detection to a small backend
endpoint.

## Deployment

### Static hosting (recommended)

1. Copy the `revclar-landing/` folder to your host.
2. Point the web server document root for `bookings.revclar.com` to this folder.
3. Ensure `index.html` is served as the default document.

### Railway

Create a static service or use Nginx with a config like:

```nginx
server {
    listen 80;
    server_name bookings.revclar.com;
    root /var/www/html/revclar-landing;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

No PHP or database is needed.

## Placeholders to fill in

Search `index.html` for the `<!-- PLACEHOLDER -->` comments and update:

- Contact email (form `data-contact-email` and mailto link)
- Contact phone number
- Contact address
- Social media links in the footer
- Favicon (`assets/favicon.svg`) if you want a custom icon

## Form handling

The contact form currently builds a `mailto:` link so visitors can send an email
using their own email client. To use a real form backend (e.g. Formspree,
Getform, or your own API), replace the form's `action` attribute and remove the
JavaScript submit handler in `js/main.js`.

The form includes a honeypot field (`name="website"`) for basic spam protection.
If that field is filled, submission is silently aborted.
