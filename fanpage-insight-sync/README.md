# Fanpage Insight Sync Plugin

A professional WordPress plugin that syncs fanpage data from Google Sheets, analyzes insight images with AI, and exposes a beautiful frontend search/filter UI.

## Features

- **Multi-Source Sync**: Connect multiple Google Sheets via CSV export.
- **AI-Powered Insights**: Automatically analyzes insight images (via OG tag resolution) using AI (Claude/GPT).
- **Premium UI**: Token-based CSS architecture with glassmorphism and smooth animations.
- **Role-Based Visibility**: Automatically filters sensitive data based on user roles.
- **Dynamic Pricing**: Calculates estimated prices based on follower count tiers.
- **Zalo Integration**: Direct contact buttons for lead generation.

## Installation

1. Upload the `fanpage-insight-sync` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress Admin.
3. Go to **Fanpage Insights > Settings** to configure your AI API key.
4. Go to **Fanpage Insights > Sheet Sources** to add your Google Sheets.

## Shortcode

Use `[fanpage_insights]` on any page to display the insight list.

## Development

Built with:
- PHP 7.4+
- WordPress Custom Tables (`$wpdb`)
- Vanilla JS + CSS (Design Token Architecture)
- REST API integration
