# UMD Libraries UI Pattern Sources

A collection of custom UI Pattern sources maintained by the
UMD Libraries.

## Currently maintained sources

### Date

Date picker with customizable format.

#### Usage

Enable the module along with all dependencies

Edit the layout of any piece of content

Add a component block to a region with a Date slot. E.g., UMD Libraries Card

For the Date slot, select the _Date_ source.

Select a date using the calendar widget and a format from _Format_ dropdown.

Save the block, and the date and format should be reflected on the page.

### Facet

Configured facets for a selected endpoint.

Intended for Search Web Components integrations.

#### Usage

> [!WARNING]
> Unstable feature

Create a facet for a decoupled endpoint.

In layouts, add a UI Patterns UMD Search Web Component facet widget.

Use the _Facet_ source for populating any _Facet_ slot.

## Installation

```bash
composer require umd-lib/umdlib_ui_pattern_sources
```