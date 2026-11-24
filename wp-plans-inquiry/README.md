# Faria Design: Plans page and inquiry system (WordPress)

Code sample from the Plans page on [fariadesign.com/plans](https://www.fariadesign.com/plans/), my studio's website. It lets agencies and businesses pick a monthly retainer or a website care plan and send an inquiry, which is saved in WordPress and emailed to me.

These files live in a custom child theme. Divi, third-party plugins and server config are not included.

## What's here

| File | What it does |
| --- | --- |
| `template-plans.php` | Page template: plan cards, FAQ and the inquiry form, with all output escaped. |
| `includes/plans.php` | Form handling through `admin-post.php`: nonce check, honeypot, sanitizing and validating every field, saving, emailing, and a post/redirect/get flow. |
| `includes/plans-inquiries.php` | A private custom post type that stores each inquiry, with a read-only admin list, custom columns, a detail view and a "Reply by email" action. |
| `assets/js/plans.js` | Vanilla JavaScript (no libraries) for the form: conditional fields, live pricing text, client-side validation and double-submit protection. |
| `assets/css/plans.css` | Styles for the page, loaded only on this template. |

## Decisions worth noting

- **Security first.** Every input is sanitized and length-limited, choices are checked against an allowlist, output is escaped, the form uses a nonce plus a honeypot, and inquiries are visible to administrators only.
- **No lost leads.** Each inquiry is saved before the notification email is sent, and the admin list shows whether the email went out.
- **Clean redirects.** After submitting, the visitor's data never goes in the URL. A random token in an HttpOnly cookie points to a short-lived transient that holds the result and any errors, so the form can show messages and keep what was typed.
- **Analytics.** A `plan_inquiry` event is pushed to the GTM data layer once, only after a successful send.
- **Performance.** CSS and JavaScript load only on the Plans template, versioned by file time for cache busting.
