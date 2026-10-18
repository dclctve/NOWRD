# NOWRD CMS v22 — Public Visitor Theme Switch

## What's new
- Public visitors see a compact floating theme switcher on the public homepage and article pages.
- Visitors can switch between Classic and Glassmorphism without an account.
- The choice is saved in that visitor's browser (`localStorage`) and persists across public pages on the same browser/device.
- Admin's global visual preferences remain the default for visitors who have not made a choice.
- The switch does not modify global settings or affect other visitors.
- Includes keyboard-accessible buttons, responsive sizing, and reduced-motion support.

## Install
1. Back up the current website and database.
2. Upload the contents of this ZIP while preserving the existing NOWRD directory structure.
3. Clear any hosting/CDN/browser cache for `public/assets/ui-v10.js` and `public/assets/style.css`.
4. Open the public homepage and an article. Use the floating `Tampilan · Classic · Glass` control at the bottom-right.

No database migration is required.

## Validation
Run PHP lint across the project, `node --check public/assets/ui-v10.js`, and `unzip -t` on the ZIP. Browser/production hosting validation is still recommended.
