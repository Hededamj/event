# Service-Specific Images Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow vendors to upload up to 8 images per service, replacing the shared vendor gallery.

**Architecture:** Add `vendor_service_images` table linked to `vendor_services`. Extend services.php with image management (upload, delete, set primary) via a per-service image modal. Convert service list from table to card layout showing thumbnails. Remove gallery.php and its sidebar link.

**Tech Stack:** PHP 7.4, PDO/MySQL, vanilla JS, existing Nordic design system CSS variables

---

### Task 1: Database Migration 020

**Files:**
- Create: `database/migrations/020_service_images.sql`

**Step 1: Write the migration SQL**

```sql
-- Migration 020: Service-specific images
-- Replaces vendor-level gallery with per-service image galleries.

CREATE TABLE IF NOT EXISTS vendor_service_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    vendor_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (service_id) REFERENCES vendor_services(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_service_images_service (service_id),
    INDEX idx_service_images_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drop the old vendor-level gallery table
DROP TABLE IF EXISTS vendor_gallery;
```

**Step 2: Commit**

```bash
git add database/migrations/020_service_images.sql
git commit -m "feat: migration 020 — vendor_service_images table"
```

---

### Task 2: Image POST handlers in services.php

**Files:**
- Modify: `subcontractor/dashboard/services.php` (lines 20-147, POST handler section)

Add three new POST actions after the existing `reorder` handler (line 146):

**Step 1: Add `upload_images` handler**

After the closing `}` of the `reorder` action block, add:

```php
// --- UPLOAD IMAGES ---
if ($action === 'upload_images') {
    $serviceId = (int) ($_POST['service_id'] ?? 0);

    // Verify service belongs to this vendor
    $checkStmt = $db->prepare("SELECT id FROM vendor_services WHERE id = ? AND vendor_id = ?");
    $checkStmt->execute([$serviceId, $vendorId]);
    if (!$checkStmt->fetch()) {
        setFlash('error', 'Ydelse ikke fundet.');
        redirect('/subcontractor/dashboard/services.php');
    }

    // Count existing images for this service
    $countStmt = $db->prepare("SELECT COUNT(*) FROM vendor_service_images WHERE service_id = ?");
    $countStmt->execute([$serviceId]);
    $currentCount = (int) $countStmt->fetchColumn();
    $maxImages = 8;

    if ($currentCount >= $maxImages) {
        setFlash('error', 'Maks ' . $maxImages . ' billeder pr. ydelse.');
        redirect('/subcontractor/dashboard/services.php');
    }

    $files = $_FILES['service_images'] ?? null;
    if (!$files || !is_array($files['name']) || empty($files['name'][0])) {
        setFlash('error', 'Ingen billeder valgt.');
        redirect('/subcontractor/dashboard/services.php');
    }

    $remaining = $maxImages - $currentCount;
    $fileCount = min(count($files['name']), $remaining);

    $uploadDir = __DIR__ . '/../../uploads/vendors/services';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $sortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM vendor_service_images WHERE service_id = ?");
    $sortStmt->execute([$serviceId]);
    $nextSort = (int) $sortStmt->fetchColumn();

    $insertStmt = $db->prepare("
        INSERT INTO vendor_service_images (service_id, vendor_id, filename, original_name, is_primary, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $timestamp = time();
    $uploaded = 0;

    for ($i = 0; $i < $fileCount; $i++) {
        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];

        if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

        $fileErrors = validateVendorImageUpload($file);
        if (!empty($fileErrors)) continue;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'svc_' . $serviceId . '_' . $timestamp . '_' . $i . '.' . $ext;

        if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
            $nextSort++;
            $isPrimary = ($currentCount === 0 && $uploaded === 0) ? 1 : 0;
            try {
                $insertStmt->execute([$serviceId, $vendorId, $filename, $file['name'], $isPrimary, $nextSort]);
                $uploaded++;
            } catch (Exception $e) {
                error_log("Failed to insert service image: " . $e->getMessage());
                @unlink($uploadDir . '/' . $filename);
            }
        }
    }

    if ($uploaded > 0) {
        setFlash('success', $uploaded . ' billede(r) uploadet.');
    } else {
        setFlash('error', 'Ingen billeder blev uploadet.');
    }
    redirect('/subcontractor/dashboard/services.php');
}
```

**Step 2: Add `delete_image` handler**

```php
// --- DELETE IMAGE ---
if ($action === 'delete_image') {
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $uploadDir = __DIR__ . '/../../uploads/vendors/services';

    try {
        $stmt = $db->prepare("SELECT id, service_id, filename, is_primary FROM vendor_service_images WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$imageId, $vendorId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($image) {
            $db->prepare("DELETE FROM vendor_service_images WHERE id = ?")->execute([$imageId]);

            $filePath = $uploadDir . '/' . $image['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // If deleted image was primary, promote the next one
            if ($image['is_primary']) {
                $nextStmt = $db->prepare("
                    SELECT id FROM vendor_service_images
                    WHERE service_id = ? ORDER BY sort_order ASC LIMIT 1
                ");
                $nextStmt->execute([$image['service_id']]);
                $nextImage = $nextStmt->fetch();
                if ($nextImage) {
                    $db->prepare("UPDATE vendor_service_images SET is_primary = 1 WHERE id = ?")->execute([$nextImage['id']]);
                }
            }

            setFlash('success', 'Billede slettet.');
        }
    } catch (Exception $e) {
        error_log("Failed to delete service image: " . $e->getMessage());
        setFlash('error', 'Kunne ikke slette billede.');
    }
    redirect('/subcontractor/dashboard/services.php');
}
```

**Step 3: Add `set_primary_image` handler**

```php
// --- SET PRIMARY IMAGE ---
if ($action === 'set_primary') {
    $imageId = (int) ($_POST['image_id'] ?? 0);

    try {
        // Get the image and its service
        $stmt = $db->prepare("SELECT service_id FROM vendor_service_images WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$imageId, $vendorId]);
        $image = $stmt->fetch();

        if ($image) {
            // Unset all primaries for this service, then set the new one
            $db->prepare("UPDATE vendor_service_images SET is_primary = 0 WHERE service_id = ?")->execute([$image['service_id']]);
            $db->prepare("UPDATE vendor_service_images SET is_primary = 1 WHERE id = ?")->execute([$imageId]);
            setFlash('success', 'Primært billede opdateret.');
        }
    } catch (Exception $e) {
        error_log("Failed to set primary image: " . $e->getMessage());
        setFlash('error', 'Kunne ikke opdatere primært billede.');
    }
    redirect('/subcontractor/dashboard/services.php');
}
```

**Step 4: Update the DELETE service handler** (around line 103)

When a service is deleted, also delete its image files from disk:

```php
// Before the existing DELETE query, add:
$imgStmt = $db->prepare("SELECT filename FROM vendor_service_images WHERE service_id = ? AND vendor_id = ?");
$imgStmt->execute([$serviceId, $vendorId]);
$images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
$imgDir = __DIR__ . '/../../uploads/vendors/services';
foreach ($images as $imgFile) {
    $path = $imgDir . '/' . $imgFile;
    if (file_exists($path)) {
        @unlink($path);
    }
}
// DB rows are deleted by CASCADE, but files need manual cleanup
```

**Step 5: Load images alongside services** (around line 183-194)

After loading services, load all images grouped by service:

```php
// After $services = $stmt->fetchAll(...)
$serviceImages = [];
if (!empty($services)) {
    $serviceIds = array_column($services, 'id');
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $imgStmt = $db->prepare("
        SELECT * FROM vendor_service_images
        WHERE service_id IN ($placeholders) AND vendor_id = ?
        ORDER BY is_primary DESC, sort_order ASC
    ");
    $imgStmt->execute(array_merge($serviceIds, [$vendorId]));
    foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $img) {
        $serviceImages[$img['service_id']][] = $img;
    }
}
```

**Step 6: Commit**

```bash
git add subcontractor/dashboard/services.php
git commit -m "feat: service image upload/delete/primary handlers"
```

---

### Task 3: Update service list UI — cards with thumbnails + image modal

**Files:**
- Modify: `subcontractor/dashboard/services.php` (HTML section, lines 202-531)

**Step 1: Replace table layout with card layout**

Replace the `<!-- Services table -->` section (lines 253-351) with a card grid. Each card shows:
- Primary image thumbnail (or placeholder)
- Title, price, status badge
- Action buttons: Edit, Images, Toggle, Delete
- Image count badge on the Images button

**Step 2: Add image management modal**

After the existing service modal, add a new `#imageModal` with:
- Upload zone (drag & drop, click to select)
- Grid of current images with delete button + "set as primary" star
- Max 8 images counter
- Form posts to `action=upload_images` with `service_id`

**Step 3: Add JS functions**

- `openImageModal(serviceId)` — opens the image modal, populates with images
- `deleteServiceImage(imageId)` — confirms and submits delete
- `setPrimaryImage(imageId)` — submits set_primary form
- Image data is embedded as JSON in each service card's data attributes

**Step 4: Add CSS for card layout and image modal**

Inline `<style>` block with:
- `.service-cards` grid layout (responsive)
- `.service-card` with thumbnail, info section, actions
- `.image-modal` styling matching existing caption-modal pattern
- `.image-grid` for thumbnail management

**Step 5: Commit**

```bash
git add subcontractor/dashboard/services.php
git commit -m "feat: card layout with thumbnails + image management modal"
```

---

### Task 4: Remove gallery page and sidebar link

**Files:**
- Modify: `subcontractor/includes/vendor-header.php` (line ~107-112)
- Delete or replace: `subcontractor/dashboard/gallery.php`

**Step 1: Remove gallery nav link from sidebar**

In vendor-header.php, remove the gallery `<a>` block (the one linking to gallery.php).

**Step 2: Replace gallery.php with redirect**

Replace gallery.php contents with a simple redirect to services.php:

```php
<?php
header('Location: /subcontractor/dashboard/services.php');
exit;
```

**Step 3: Commit**

```bash
git add subcontractor/includes/vendor-header.php subcontractor/dashboard/gallery.php
git commit -m "feat: remove gallery page, redirect to services"
```

---

### Task 5: Deploy and run migration

**Step 1: Upload all changed files via FTP**

Files to upload:
- `database/migrations/020_service_images.sql`
- `subcontractor/dashboard/services.php`
- `subcontractor/dashboard/gallery.php`
- `subcontractor/includes/vendor-header.php`

**Step 2: Create and upload temp migration runner**

Script that:
1. Checks if `vendor_service_images` table exists
2. If not, runs migration 020
3. Creates `/uploads/vendors/services/` directory

**Step 3: Execute migration and verify**

**Step 4: Delete temp script from server**

**Step 5: Final commit and push**

```bash
git add -A
git commit -m "feat: service-specific images with per-product galleries"
git push
```
