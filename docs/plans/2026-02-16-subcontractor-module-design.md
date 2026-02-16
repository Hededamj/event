# Subcontractor Module - Design Document

**Date:** 2026-02-16
**Status:** Approved
**Replaces:** Existing partner marketplace (`/partners/`, `/admin-platform/partners.php`, `includes/partner-*.php`, `database/setup/06-09*.sql`)

---

## 1. Overview

Build a complete subcontractor/vendor management system from scratch. No code reuse from the existing partner marketplace. The module enables event organizers to find, book, and pay service providers (photographers, caterers, DJs, etc.) through the platform, and allows vendors to manage their business via a dedicated dashboard.

### Revenue Model
- **Depositum:** 25% of vendor's quoted price, paid by organizer via Stripe
- **Platform commission:** 15% of depositum retained by platform
- **Guarantee:** Full depositum refund if vendor fails to deliver
- **Access:** All subscription plans (Free, Basic, Premium, Pro)

---

## 2. What Gets Replaced

### Files to remove (existing partner marketplace):
- `/partners/` (entire directory)
- `/admin-platform/partners.php`
- `/admin-platform/commissions.php`
- `/includes/partner-auth.php`
- `/includes/partner-commission.php`
- `/includes/partner-reviews.php`
- `/includes/partner-verification.php`
- `/includes/partner-header.php`
- `/database/setup/06-partner-categories.sql`
- `/database/setup/07-partners.sql`
- `/database/setup/08-partner-gallery.sql`
- `/database/setup/09-partner-inquiries.sql`
- `/database/migrations/003_partner_marketplace.sql`
- `/database/migrations/004_partner_commission.sql`
- `/database/migrations/005_partner_reviews.sql`
- `/database/migrations/006_partner_verification.sql`

### Tables to drop (replaced by new schema):
- `partners`, `partner_categories`, `partner_gallery`, `partner_reviews`
- `partner_commissions`, `partner_verification`, `partner_inquiries`

---

## 3. Architecture

### 3.1 Directory Structure (new code only)

```
/subcontractor/
├── index.php                    # Marketplace browse/search
├── category.php                 # Category listing
├── profile.php                  # Vendor public profile
├── book.php                     # Booking request flow
├── payment.php                  # Depositum payment (Stripe Checkout)
├── payment-success.php          # Post-payment confirmation
├── payment-cancel.php           # Payment cancelled
│
├── dashboard/                   # Vendor dashboard (authenticated)
│   ├── index.php               # Dashboard home (stats, upcoming bookings)
│   ├── login.php               # Vendor login
│   ├── register.php            # Vendor registration
│   ├── logout.php              # Vendor logout
│   ├── profile.php             # Edit vendor profile
│   ├── services.php            # Manage services/pricing
│   ├── gallery.php             # Manage photos
│   ├── bookings.php            # Booking management
│   ├── booking-detail.php      # Single booking detail + messaging
│   ├── calendar.php            # Calendar view
│   ├── earnings.php            # Revenue/payout overview
│   └── reviews.php             # View received reviews
│
├── assets/
│   ├── css/
│   │   └── subcontractor.css   # Module-specific styles
│   └── js/
│       └── subcontractor.js    # Module-specific interactivity
│
└── includes/
    ├── vendor-auth.php          # Vendor authentication & sessions
    ├── vendor-header.php        # Vendor dashboard header/nav
    ├── vendor-footer.php        # Vendor dashboard footer
    ├── marketplace-header.php   # Public marketplace header
    ├── booking-service.php      # Booking business logic
    ├── payment-service.php      # Stripe Connect payment logic
    ├── commission-service.php   # Commission calculations
    ├── review-service.php       # Review management
    ├── notification-service.php # Email notifications for bookings
    └── vendor-validation.php   # Input validation functions

/app/events/pages/
    └── vendors.php              # Event vendor management tab (organizer view)

/admin-platform/
    ├── vendors.php              # Platform admin: vendor management
    ├── vendor-detail.php        # Platform admin: vendor detail
    └── vendor-payouts.php       # Platform admin: payout overview

/app/webhooks/
    └── stripe-connect.php       # Stripe Connect webhooks

/database/migrations/
    └── 015_subcontractor_module.sql  # Complete schema migration

/tests/
    └── subcontractor/
        ├── BookingServiceTest.php
        ├── PaymentServiceTest.php
        ├── CommissionServiceTest.php
        └── VendorAuthTest.php
```

### 3.2 Design Principles

1. **Clean separation:** Business logic in service files, presentation in page files
2. **No global state:** Functions receive dependencies as parameters
3. **Input validation:** All user input validated via vendor-validation.php
4. **Prepared statements only:** All SQL via PDO prepared statements
5. **CSRF on all forms:** Using existing CSRF infrastructure
6. **Testable:** All service functions can be unit tested
7. **Nordic design:** Consistent with existing platform aesthetic

---

## 4. Database Schema

### 4.1 vendor_categories

```sql
CREATE TABLE vendor_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(10) NOT NULL,
    description VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default categories:
-- ⛺ Teltudlejning, 🍽️ Catering, 🏛️ Festlokaler, 🎵 DJ & Musik,
-- 📷 Fotograf, 💐 Blomster, 🎂 Kage & Dessert, 🎭 Underholdning,
-- 🚐 Transport, 📌 Andet
```

### 4.2 vendors

```sql
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    website VARCHAR(255),
    description TEXT,
    short_description VARCHAR(500),

    -- Location
    address VARCHAR(255),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    service_areas TEXT COMMENT 'JSON array of postal code ranges or city names',
    nationwide TINYINT(1) DEFAULT 0,

    -- Media
    logo_filename VARCHAR(255),
    cover_filename VARCHAR(255),

    -- Stripe Connect
    stripe_account_id VARCHAR(255) COMMENT 'Stripe Connect Express account ID',
    stripe_onboarding_complete TINYINT(1) DEFAULT 0,

    -- Status & moderation
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    rejection_reason TEXT,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,

    -- Stats (denormalized for performance)
    avg_rating DECIMAL(2,1) DEFAULT 0,
    review_count INT DEFAULT 0,
    booking_count INT DEFAULT 0,
    view_count INT DEFAULT 0,

    -- Session
    last_login_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_rating (avg_rating DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3 vendor_category_links (many-to-many)

```sql
CREATE TABLE vendor_category_links (
    vendor_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (vendor_id, category_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES vendor_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4 vendor_services

```sql
CREATE TABLE vendor_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price_from DECIMAL(10,2) NOT NULL,
    price_to DECIMAL(10,2) NULL COMMENT 'NULL = fixed price, set = price range',
    price_unit ENUM('fixed', 'per_person', 'per_hour') DEFAULT 'fixed',
    duration_hours DECIMAL(4,1) NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.5 vendor_gallery

```sql
CREATE TABLE vendor_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    caption VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.6 bookings

```sql
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    vendor_id INT NOT NULL,
    vendor_service_id INT NULL COMMENT 'NULL if custom/manual booking',
    account_id INT NOT NULL COMMENT 'Organizer who booked',

    -- Booking details
    status ENUM(
        'requested',    -- Organizer sent request
        'quoted',       -- Vendor responded with quote
        'accepted',     -- Organizer accepted quote
        'deposited',    -- Depositum paid via Stripe
        'confirmed',    -- Vendor confirmed after payment
        'completed',    -- Event happened, service delivered
        'reviewed',     -- Organizer left review
        'cancelled',    -- Cancelled by either party
        'refunded',     -- Depositum refunded
        'disputed'      -- Under dispute
    ) DEFAULT 'requested',

    -- Financial
    quoted_price DECIMAL(10,2) NULL COMMENT 'Vendor quoted price',
    depositum_amount DECIMAL(10,2) NULL COMMENT '25% of quoted_price',
    commission_amount DECIMAL(10,2) NULL COMMENT '15% of depositum',
    vendor_payout DECIMAL(10,2) NULL COMMENT '85% of depositum',

    -- Stripe
    stripe_payment_intent_id VARCHAR(255) NULL,
    stripe_transfer_id VARCHAR(255) NULL,
    stripe_refund_id VARCHAR(255) NULL,

    -- Event context
    event_date DATE NOT NULL,
    guest_count INT NULL,
    organizer_message TEXT COMMENT 'Initial request message',
    vendor_message TEXT COMMENT 'Quote/response message',

    -- Timestamps
    quoted_at TIMESTAMP NULL,
    accepted_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    confirmed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    cancelled_by ENUM('organizer', 'vendor', 'platform') NULL,
    cancel_reason TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (vendor_service_id) REFERENCES vendor_services(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_event (event_id),
    INDEX idx_vendor (vendor_id),
    INDEX idx_status (status),
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.7 booking_messages

```sql
CREATE TABLE booking_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    sender_type ENUM('organizer', 'vendor', 'platform') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_unread (booking_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.8 vendor_reviews

```sql
CREATE TABLE vendor_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE COMMENT 'One review per booking',
    vendor_id INT NOT NULL,
    account_id INT NOT NULL,
    rating TINYINT NOT NULL COMMENT '1-5 stars',
    title VARCHAR(255),
    review_text TEXT,
    vendor_response TEXT NULL,
    vendor_responded_at TIMESTAMP NULL,
    is_visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_vendor (vendor_id),
    INDEX idx_rating (vendor_id, rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.9 manual_vendors (organizer's own vendors, not on marketplace)

```sql
CREATE TABLE manual_vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(255),
    agreed_price DECIMAL(10,2),
    is_paid TINYINT(1) DEFAULT 0,
    paid_at DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.10 refund_requests

```sql
CREATE TABLE refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    requested_by INT NOT NULL COMMENT 'account_id of organizer',
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES accounts(id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. User Flows

### 5.1 Organizer: Browse & Book from Marketplace

```
1. Navigate to /subcontractor/ (or event tab "Leverandører")
2. Filter by category, city/area, price range, rating
3. View vendor profile (photos, services, reviews, availability)
4. Click "Send forespørgsel" on a service
5. Fill in: event date, guest count, message
6. Vendor receives notification → responds with quote
7. Organizer reviews quote in booking messages
8. Organizer accepts → redirected to Stripe Checkout for 25% depositum
9. Payment succeeds → booking status = "deposited"
10. Vendor confirms → status = "confirmed", appears in event vendor tab
11. After event → organizer marks complete, writes review
```

### 5.2 Organizer: Add Manual Vendor

```
1. Go to event → "Leverandører" tab
2. Click "Tilføj egen leverandør"
3. Fill in: name, contact, category, price, notes
4. Vendor appears in event vendor list and connects to budget
5. Can mark as paid/unpaid
```

### 5.3 Vendor: Onboarding

```
1. Visit /subcontractor/dashboard/register.php
2. Fill company info, category, services, pricing
3. Upload logo, cover image, gallery photos
4. Submit for platform approval
5. Platform admin reviews and approves/rejects
6. If approved → vendor completes Stripe Connect onboarding
7. Profile goes live on marketplace
```

### 5.4 Vendor: Handle Booking

```
1. Receive email notification of new request
2. Log in to /subcontractor/dashboard/
3. View booking request details (event date, guest count, message)
4. Reply with quote and message
5. Wait for organizer to accept and pay depositum
6. Receive confirmation notification
7. Event happens → booking marked complete
8. Receive payout (depositum minus 15% commission) via Stripe Connect
```

### 5.5 Refund Flow

```
1. Organizer reports issue via booking detail page
2. Selects reason and submits refund request
3. Platform admin reviews request
4. If approved → Stripe refund issued to organizer
5. Vendor payout reversed/cancelled
6. Both parties notified
```

---

## 6. Stripe Connect Integration

### 6.1 Vendor Onboarding
- Create Stripe Connect Express account via API
- Redirect vendor to Stripe-hosted onboarding form
- Handle return URL to verify onboarding complete
- Store `stripe_account_id` on vendor record

### 6.2 Depositum Payment
- Create Stripe Checkout Session with:
  - `payment_intent_data.transfer_data.destination` = vendor's Stripe account
  - `payment_intent_data.application_fee_amount` = 15% commission
  - Amount = 25% of quoted price
- Handle success/cancel redirects
- Verify payment via webhook

### 6.3 Webhooks (stripe-connect.php)
- `checkout.session.completed` → update booking to "deposited"
- `payment_intent.succeeded` → confirm payment received
- `charge.refunded` → update booking to "refunded"
- `account.updated` → update vendor onboarding status
- `transfer.created` → log vendor payout

### 6.4 Refunds
- Stripe Refund API to refund organizer's payment
- Reverse transfer to vendor if already paid out
- Log refund in refund_requests table

---

## 7. Email Notifications

Using existing email-service.php infrastructure:

| Event | Recipient | Content |
|-------|-----------|---------|
| New booking request | Vendor | Event details, link to dashboard |
| Quote received | Organizer | Vendor quote, link to accept |
| Quote accepted | Vendor | Confirmation, payment pending |
| Depositum paid | Both | Payment confirmed, booking details |
| Booking confirmed | Organizer | Vendor confirmed, contact info |
| Booking cancelled | Both | Cancellation notice |
| Refund approved | Both | Refund confirmation |
| Review received | Vendor | New review notification |
| Reminder: upcoming event | Vendor | 7 days before event date |

---

## 8. Event Integration

### 8.1 Vendors Tab (app/events/pages/vendors.php)

New tab in event management showing:
- **Marketplace bookings:** Status, vendor name, service, price, payment status
- **Manual vendors:** Name, category, price, paid/unpaid
- **Budget summary:** Total vendor costs linked to budget module
- **Actions:** Browse marketplace, add manual vendor, message vendor

### 8.2 Budget Integration

When a booking is confirmed or manual vendor added:
- Auto-create budget_item linked to the vendor/booking
- Category mapped from vendor category
- Estimated = quoted price (full, not just depositum)
- Actual updates when marked complete
- Paid status syncs with payment status

---

## 9. Admin Platform Integration

### 9.1 Vendor Management (admin-platform/vendors.php)
- List all vendors with status filter
- Approve/reject pending vendor applications
- Suspend vendors
- View vendor details and booking history

### 9.2 Payout Overview (admin-platform/vendor-payouts.php)
- List all deposits and commissions
- Filter by date, status, vendor
- Total platform revenue from commissions
- Pending payouts

---

## 10. UI/UX Guidelines

- **Design system:** Use existing Nordic palette (sage, cream, wood)
- **Typography:** Consistent with platform (Fraunces headings, DM Sans body)
- **Components:** Reuse existing card, button, form, modal patterns
- **Responsive:** Mobile-first, same breakpoints as platform
- **Vendor dashboard:** Follow same layout pattern as organizer dashboard (sidebar + content)
- **Marketplace:** Grid layout for vendor cards, filters in sidebar/top bar
- **Frontend skill:** Use `frontend-design` skill for all UI implementation

---

## 11. Testing Strategy

### Unit Tests (PHPUnit)
- `BookingServiceTest` - booking state machine, price calculations
- `PaymentServiceTest` - depositum calculation, commission calculation
- `CommissionServiceTest` - commission rates, payout amounts
- `VendorAuthTest` - login, registration, session management

### Integration Tests
- Full booking flow: request → quote → accept → pay → confirm → complete → review
- Refund flow: request → approve → Stripe refund
- Stripe webhook handling

---

## 12. Migration Strategy

### Phase 1: Database & Backend Services
- Create new migration with all tables
- Build all service files (booking, payment, commission, review, notification)
- Build vendor auth system
- Write tests for services

### Phase 2: Vendor Dashboard
- Registration & login
- Profile management
- Service/pricing management
- Gallery management
- Stripe Connect onboarding

### Phase 3: Marketplace Public Pages
- Browse/search with filters
- Vendor profile pages
- Booking request flow
- Stripe Checkout integration

### Phase 4: Event Integration
- Vendors tab in event management
- Manual vendor management
- Budget integration
- Booking messages

### Phase 5: Admin & Polish
- Admin vendor management
- Admin payout overview
- Email notifications
- Remove old partner marketplace code
- Final testing & review

---

## Decisions Log

| Decision | Choice | Reasoning |
|----------|--------|-----------|
| Revenue model | Depositum + 15% commission | Low friction, platform earns on every booking |
| Depositum size | 25% of quoted price | Enough to commit, not too high to deter |
| Feature gate | All plans | Maximize volume and commission revenue |
| Vendor auth | Separate from accounts | Vendors are a different user type with different needs |
| Code reuse | None | Existing partner code is low quality, fresh build |
| Guarantee | Full depositum refund | Simple policy, builds trust |
| Payment | Stripe Connect Express | Simplest onboarding for vendors, handles payouts |
| Manual vendors | Separate table | Don't force marketplace vendors for personal contacts |
