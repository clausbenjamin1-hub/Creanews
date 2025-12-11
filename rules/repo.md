---
description: Repository Information Overview
alwaysApply: true
---

# KYOCERA Newsletter Editor Information

## Summary
A web-based newsletter editor for KYOCERA Document Solutions featuring a fixed header/footer, central editable area, and responsive design. The editor supports rich content insertion (images, videos, tables, text), local and server-based saving, and a robust history/undo system.

## Structure
- **Root**: Main HTML files, editor scripts, and styles
- **Image/**: Contains all image assets for the newsletter
- **login/**: Role-based authentication system for protected PDF downloads
- **Data_test/**: Test files for history, data setup, and direct link behaviors

## Language & Runtime
**Language**: HTML, CSS, JavaScript, PHP
**Runtime**: Web browser (client-side), PHP server (for saving)
**Build System**: None (static files)
**Package Manager**: npm (for login component only)

## Dependencies
**Main Dependencies**:
- Font Awesome 6.4.0 (CDN)
- Google Fonts - Montserrat (CDN)
- Express.js (login component)
- body-parser (login component)
- cookie-session (login component)

## Build & Installation
```bash
# Simple installation (client-side only)
# Just open modèle_newsletter.html in a browser

# For server-side saving functionality
# Install a local web server (XAMPP, WAMP, etc.)
# Place files in web server directory
# Access via http://localhost/newsletter/modèle_newsletter.html

# For login component (optional)
Set-Location "c:\Newsletter\modele\login"
npm install
npm start
```

## Main Files
**Entry Points**:
- `modèle_newsletter.html`: Main editor interface
- `index.html`: Landing page
- `inscription.html`: Registration page
- `login/login_page/index.html`: Login interface for protected content

**Core Components**:
- `editor.js`: Handles all editing logic, content insertion, formatting, history, and autosave
- `styles.css`: Global and component styles
- `save_file.php`: Server-side script for saving newsletters to C:\Newsletter
- `login/src/auth.js`: Authentication logic for role-based access

## Testing
**Framework**: Manual browser testing
**Test Location**: `Data_test/` directory
**Test Files**:
- `test_history.html`: Tests for history functionality
- `test_direct_links.html`: Tests for direct link behavior
- `test_image_deletion.html`: Tests for image deletion functionality

**Run Command**:
```bash
# Manual testing - open in browser
# Example:
Start-Process "c:\Newsletter\modele\Data_test\test_history.html"
```

## Features
- **Rich Text Editing**: Formatting, colors, alignment, lists
- **Media Insertion**: Images (local/URL), videos (YouTube/local)
- **Table Management**: Create and edit tables with floating toolbar
- **Section Templates**: Two columns, article, gallery, quote, webinars, contact
- **History System**: Up to 50 undo actions and 20 saved projects
- **Autosave**: Every 30 seconds with browser localStorage
- **Role-Based Access**: Protected PDF downloads with authentication

## Defaults & Content Hygiene (applies to all future creations)
- **Default Font**: Verdana site-wide (`styles.css` `body { font-family: 'Verdana'; }`).
- **Default Text Color**: Inherit from content theme (`--kyo-text`, currently `#111111`). Links/CTAs use `--kyo-red` and `--kyo-red-dark`.
- **Floating Toolbar Colors**: All text/background colors should be applied via the toolbar palettes in `index.html`.
- **Paste Sanitization**: Pasting into `#editableContent` removes inline `font-family`, `font-size`, `color`, `background-color`, presentational attributes (`color`, `face`, `size`) and converts `<font>` tags to `<span>`, ensuring defaults (Verdana + theme colors) are enforced. See `editor.js` `sanitizePastedHtml()` and the `paste` handler in `setupEventListeners()`.
- **Preview Parity**: The preview generated from `index.html` (`previewFull()` in `editor.js`) uses the same stylesheet versioning as `preview.html` to keep typography and colors consistent.

## Change Management & Testing
- "when you update or change ensure you didn't change the UI and another function , link and syc,. when finished test all other function not affected"
- **No unintended UI changes**: Preserve visual design and behavior.
- **No regressions**: Do not break existing functions, links, or sync features.
- **Post-change testing**: After any modification, test unaffected areas to confirm they still work as before (editor tools, media insertion, tables, templates, history/autosave, authentication, saving/loading, link integrity, synchronization).
- **External links security**: When a subject or content contains an external link, ensure it uses HTTPS and points to a secure site (no plain HTTP).