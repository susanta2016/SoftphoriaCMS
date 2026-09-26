# app/Shared/Support

Framework-agnostic utilities and platform infrastructure that are genuinely
used by more than one module (or by the platform itself), and that carry no
business/domain meaning of their own.

Currently contains:

- `Modules/` — the Softphoria module registration mechanism
  (`ModuleServiceProvider`, `ModuleRegistry`). See `docs/ARCHITECTURE.md`.
- `Spam/FormTimeTrap` — encrypted "form rendered at" token that rejects
  submissions sent back faster than a human could fill the form in. Used by
  the Contact page/widget; reusable by any public form alongside the
  `hp_website` honeypot.
- `Contact/ContactDetailMasker` — masks the site's email/phone/WhatsApp for
  the public HTML and builds the real links for `contact.reveal`.

Do not add speculative helpers here. A class only belongs in `Support` once
at least one real, current need for it exists.
