# RUP -- You Shall Pass

Take control of WordPress.org repository updates.

**You Shall Pass** is a lightweight WordPress plugin that
retrieves update metadata directly from WordPress.org and injects
updates into the native WordPress update system using the [Universal
Updater Drop-In (UUPD)](https://github.com/stingray82/UUPD).

Designed as an opt-in solution, You Shall Pass gives site owners full
control over which plugins and themes receive update notifications
without waiting for repository rollout delays.

------------------------------------------------------------------------

## Features

-   Uses official WordPress.org metadata
-   Supports both plugins and themes
-   Integrates with the native WordPress Updates screen
-   Built on UUPD for reliable update injection
-   Disabled by default for safety
-   Select individual plugins and themes
-   Enable updates for all plugins and themes
-   Filter-based automation support
-   Developer-friendly architecture
-   No external update server required

------------------------------------------------------------------------

## Installation

1.  Upload the plugin to your WordPress installation.
2.  Activate **You Shall Pass**.
3.  Navigate to:

`Settings → You Shall Pass`

4.  Choose one of the following modes:

### Selected Plugins and Themes Only

Only selected plugins and themes will receive update overrides.

### All Plugins and Themes

All supported WordPress.org plugins and themes will receive update
overrides.

------------------------------------------------------------------------

## Default Behaviour

For safety, the plugin is disabled by default.

After activation:

-   Mode is set to **Selected Plugins and Themes Only**
-   No plugins are selected
-   No themes are selected

This means no update overrides occur until explicitly configured.

------------------------------------------------------------------------

## Developer Filters

### Enable All Updates Programmatically

``` php
add_filter('rup_ysp_apply_all_updates', '__return_true');
```

### Add Plugin Slugs Programmatically

``` php
add_filter('rup_ysp_selected_plugin_slugs', function ($slugs) {
    $slugs[] = 'woocommerce';
    $slugs[] = 'fluent-crm';
    $slugs[] = 'elementor';
    return $slugs;
});
```

### Add Theme Slugs Programmatically

``` php
add_filter('rup_ysp_selected_theme_slugs', function ($slugs) {
    $slugs[] = 'generatepress';
    $slugs[] = 'kadence';
    return $slugs;
});
```

### Adjust caching

```php
add_filter( 'rup_ysp_cache_ttl', function() {
    return 6 * HOUR_IN_SECONDS;
} );
```



------------------------------------------------------------------------

## How It Works

You Shall Pass queries WordPress.org metadata endpoints and converts the
response into a format understood by UUPD.

When a newer version is available, the plugin injects the update into
WordPress' native update transients so it appears exactly like a
standard update notification.

No custom update interface is used.

Everything remains inside the standard WordPress update workflow.

------------------------------------------------------------------------

## Requirements

-   WordPress 6.0+
-   PHP 7.4+
-   Access to WordPress.org update APIs

------------------------------------------------------------------------

## Credits

Built by Nathan Foley / Reallyusefulplugins.com.

Powered by the [Universal Updater Drop-In (UUPD)](https://github.com/stingray82/UUPD).

------------------------------------------------------------------------

## Disclaimer

This project is not affiliated with or endorsed by WordPress.org,
Automattic, or the WordPress Foundation.

WordPress is a trademark of the WordPress Foundation.



------------------------------------------------------------------------

## License

GPL-2.0-or-later

------------------------------------------------------------------------

*"All we have to decide is what to do with the updates that are given to us."*
