# Implementation Plan: Project Refactor

**Status**: Draft  
**Spec**: [spec.md](spec.md)

## Summary
Refactor the "Fanpage Insight Sync" plugin to achieve a premium "Asset Marketplace" look and a professional code structure. This involves a complete overhaul of the CSS design token system and modularizing the JavaScript logic.

## Technical Context
- **Languages**: PHP 7.4+, Vanilla JavaScript (ES6+), CSS3.
- **Platform**: WordPress.
- **Dependencies**: jQuery (for WP compatibility), dashicons.
- **Architecture**: MVC-lite (PHP includes), Modular Frontend.

## Execution Phases

### Phase 1: Style Foundation (Refactor CSS)
- **Task 1.1**: Define and implement HSL Design Tokens in `assets/fpis-frontend.css`.
- **Task 1.2**: Wrap all frontend HTML in `#fpis-app-root` for isolation.
- **Task 1.3**: Implement Glassmorphism utility classes and card lift animations.
- **Task 1.4**: Remove all non-essential `!important` flags.

### Phase 2: Logic Modernization (Refactor JS)
- **Task 2.1**: Rewrite `fpis-frontend.js` using a class-based structure (`class FPISApp`).
- **Task 2.2**: Decouple API fetching from DOM rendering.
- **Task 2.3**: Implement a proper template literal system for rendering cards and modals.
- **Task 2.4**: Add loading states and smooth transition logic.

### Phase 3: Backend Cleanup (Refactor PHP)
- **Task 3.1**: Standardize error handling in `class-fpis-sync.php` and `class-fpis-ai.php`.
- **Task 3.2**: Improve data sanitization/escaping in `class-fpis-db.php`.
- **Task 3.3**: Optimize REST API responses to return only necessary fields based on user role.

## Success Metrics
- **Performance**: Lighthouse score improvement for CLS (Cumulative Layout Shift).
- **Maintainability**: Reduced code duplication in JS.
- **Visuals**: "Wow" factor achieved via depth and animation.
