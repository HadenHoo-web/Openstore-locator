# OpenStore Locator

![WordPress](https://img.shields.io/badge/WordPress-plugin-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![Leaflet](https://img.shields.io/badge/Maps-Leaflet-199900?style=for-the-badge&logo=leaflet&logoColor=white)
![OpenStreetMap](https://img.shields.io/badge/No%20Google%20Maps%20API-OpenStreetMap-7EBC6F?style=for-the-badge&logo=openstreetmap&logoColor=white)
![Version](https://img.shields.io/badge/version-1.1.18-111827?style=for-the-badge)

A lightweight WordPress store locator plugin for publishing beautiful, searchable store maps with Leaflet and OpenStreetMap. No Google Maps API key required.

## Why It Exists

OpenStore Locator gives WordPress sites a clean store-finder experience without the friction of paid map APIs. Add stores in the admin dashboard, organize them by province and district, then drop one shortcode into any page to render a responsive map with filters, store cards, popups, and direction links.

## Highlights

- Custom post type for managing store locations.
- Hierarchical region taxonomy for province and district filtering.
- Interactive admin map for choosing exact latitude and longitude.
- Frontend shortcode with keyword search, province filter, district filter, result count, list view, and map view.
- Leaflet map rendering with CARTO Voyager/OpenStreetMap tiles.
- Optional custom marker icon from the WordPress media library.
- Theme-friendly primary color setting.
- Optional Font Awesome icons.
- Optional Google Maps direction links for customers.
- Auto-seeding for Vietnam provinces and districts through `provinces.open-api.vn`.
- Import-friendly custom fields for CSV workflows.

## Repository Name Ideas

Recommended:

```text
openstore-locator
```

Other good options:

```text
leaflet-store-locator-wp
wordpress-openstreetmap-store-locator
local-store-maps-openstreetmap
mapwise-store-locator
storefront-map-locator
```

## GitHub Description

```text
A lightweight WordPress store locator plugin powered by Leaflet and OpenStreetMap, with searchable stores, region filters, custom markers, and no Google Maps API key required.
```

## Screenshots

Add your plugin screenshots here after publishing the repo:

```md
![Store locator frontend](assets/screenshots/frontend.png)
![Store editor map](assets/screenshots/admin-map.png)
![Plugin settings](assets/screenshots/settings.png)
```

## Installation

1. Download or clone this repository.
2. Copy the plugin folder into `wp-content/plugins/`.
3. In WordPress Admin, go to `Plugins`.
4. Activate `Local Store Maps`.
5. Go to `Stores > Settings` and configure the default map center, zoom, marker, color, and direction setting.
6. Add stores under the `Stores` custom post type.
7. Place the shortcode on any page:

```text
[local_store]
```

## Shortcode

Basic usage:

```text
[local_store]
```

Custom map height:

```text
[local_store height="720"]
```

Limit displayed stores:

```text
[local_store posts_per_page="20"]
```

Available attributes:

| Attribute | Default | Description |
| --- | --- | --- |
| `height` | `650` | Map area height in pixels. The plugin enforces a minimum of `420`. |
| `posts_per_page` | `-1` | Number of published stores to load. Use `-1` for all stores. |

## Store Fields

Each store supports:

| Field | Purpose |
| --- | --- |
| Title | Store name. |
| Content | Store details or description. |
| Featured Image | Store card and popup image. |
| Address | Displayed in cards and popups. |
| Phone | Customer contact number. |
| Hotline | Fallback contact number. |
| Email | Optional contact email. |
| Opening Hours | Store schedule. |
| More Link | URL for the `Read more` action. |
| Latitude | Map marker latitude. |
| Longitude | Map marker longitude. |
| Region | Province or district taxonomy term. |

## CSV Import Mapping

Suggested import mapping:

| CSV Column | WordPress Target |
| --- | --- |
| `Title` | Post title |
| `Content` | Post content |
| `Image Featured` | Featured image |
| `localstore_address` | Custom field `localstore_address` |
| `localstore_phone` | Custom field `localstore_phone` |
| `localstore_hotline` | Custom field `localstore_hotline` |
| `localstore_email` | Custom field `localstore_email` |
| `localstore_open_hours` | Custom field `localstore_open_hours` |
| `localstore_link_to` | Custom field `localstore_link_to` |
| `Latitude` | Custom field `localstore_maps` |
| `Longitude` | Custom field `localstore_maps_lng` |
| `Region` | Taxonomy `local_store_region` |

For hierarchical regions, import terms in a parent-child format such as:

```text
Ha Noi > Ba Dinh
Ho Chi Minh > District 1
Da Nang > Hai Chau
```

The exact format depends on your WordPress import tool.

## Map Provider

The frontend map uses Leaflet with CARTO Voyager tiles:

```text
https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png
```

This keeps the plugin simple and avoids requiring a Google Maps API key. Direction buttons can still open Google Maps directions when enabled.

## Requirements

- WordPress 5.8 or newer recommended.
- PHP 7.4 or newer recommended.
- Internet access for map tiles and optional external assets:
  - Leaflet CDN
  - CARTO/OpenStreetMap tiles
  - Font Awesome CDN when enabled
  - Vietnam province/district seed API during setup

## File Structure

```text
local-store-maps.php
assets/
  css/
    admin.css
    frontend.css
  js/
    admin.js
    frontend.js
README.md
```

## Development Notes

The plugin is intentionally compact:

- `local-store-maps.php` registers the post type, taxonomy, settings, shortcode, admin UI, and frontend data.
- `assets/js/admin.js` powers the admin map, media selector, tabs, and color picker.
- `assets/js/frontend.js` powers filtering, marker rendering, popups, and store list interactions.
- `assets/css/admin.css` and `assets/css/frontend.css` style the admin and public experiences.

## Roadmap Ideas

- Gutenberg block support.
- REST API endpoint for async store loading.
- Per-store custom marker icons.
- Clustered markers for large store networks.
- Distance search using browser geolocation.
- Admin export/import helpers.

## License

No license has been selected yet. Add a license before publishing if you want others to use, modify, or redistribute the plugin.

## Credits

Built with WordPress, Leaflet, OpenStreetMap, CARTO tiles, and the Vietnam administrative data API from `provinces.open-api.vn`.
