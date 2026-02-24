# Service-Specific Images Design

**Date:** 2026-02-24
**Status:** Approved

## Problem

Vendors (e.g. tent rental companies) need to showcase individual products with photos. Currently images are vendor-level only (shared gallery), not linked to specific services.

## Decision

Replace the shared vendor gallery with per-service image uploads. Each service gets its own gallery (max 8 images).

## Database

New table `vendor_service_images`:
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `service_id` INT NOT NULL (FK → vendor_services ON DELETE CASCADE)
- `vendor_id` INT NOT NULL (FK → vendors, for easy querying)
- `filename` VARCHAR(255) NOT NULL
- `original_name` VARCHAR(255)
- `is_primary` TINYINT(1) DEFAULT 0
- `sort_order` INT DEFAULT 0
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

Migration 020: Create table, drop `vendor_gallery`.

## UI Changes

### services.php
- Service form (modal) gets image upload area below existing fields
- Shows existing images as thumbnails with delete button
- First uploaded image auto-set as primary; can change primary
- Service list shows primary image thumbnail

### gallery.php
- Remove or redirect to services.php

### vendor-header.php (sidebar)
- Remove "Galleri" nav link

## Constraints

- Max 8 images per service
- Max 5MB per image
- JPEG, PNG, GIF, WebP only (finfo validation)
- Files stored in `/uploads/vendors/services/`
- Filename format: `svc_{service_id}_{timestamp}_{index}.{ext}`

## Not Building

- Image cropping/resizing
- Drag-and-drop reordering (use sort_order via buttons)
- Service variants/attributes
- Service packages/bundles
