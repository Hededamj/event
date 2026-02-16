# Subcontractor Module Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete vendor/subcontractor marketplace with booking, Stripe Connect payments, vendor dashboard, and event integration — replacing the existing partner marketplace entirely.

**Architecture:** New `/subcontractor/` directory with clean service-layer separation. Vendor auth is separate from account auth. Stripe Connect Express for vendor payouts. All business logic in `/subcontractor/includes/` service files. UI follows existing Nordic design system and platform patterns.

**Tech Stack:** PHP 7.4+, MySQL/PDO, Stripe Connect Express (raw cURL via existing stripeRequest pattern), vanilla JS, existing CSS design system.

**Design Doc:** `docs/plans/2026-02-16-subcontractor-module-design.md`

---

## Phase 1: Database & Backend Services

### Task 1: Create database migration

**Files:**
- Create: `database/migrations/015_subcontractor_module.sql`

**Step 1: Write the migration file**

Create the complete migration with all 10 tables: `vendor_categories`, `vendors`, `vendor_category_links`, `vendor_services`, `vendor_gallery`, `bookings`, `booking_messages`, `vendor_reviews`, `manual_vendors`, `refund_requests`. Include default category seed data (10 categories with Danish names and emoji icons). Use exact patterns from existing migrations: InnoDB, utf8mb4, TIMESTAMP DEFAULT CURRENT_TIMESTAMP, foreign keys with ON DELETE CASCADE where appropriate.

**Step 2: Run migration to verify SQL is valid**

Run: `php database/migrate.php`
Expected: All 10 tables created successfully, categories seeded.

**Step 3: Commit**

```bash
git add database/migrations/015_subcontractor_module.sql
git commit -m "feat(subcontractor): add database migration for vendor module"
```

---

### Task 2: Build vendor authentication service

**Files:**
- Create: `subcontractor/includes/vendor-auth.php`

**Step 1: Write vendor auth service**

Implement these functions following the exact pattern from `includes/partner-auth.php`:
- Session setup (30-day lifetime, HttpOnly, Secure, SameSite=Lax)
- `isVendorLoggedIn(): bool`
- `requireVendorLogin(): void` — redirects to `/subcontractor/dashboard/login.php`
- `getCurrentVendorId(): ?int`
- `getCurrentVendor(): ?array` — returns id, email, company_name from session
- `vendorLogin(int $vendorId, string $email, string $companyName): void` — regenerate session, set session vars, update last_login_at
- `vendorLogout(): void` — unset session vars
- `verifyVendorPassword(string $email, string $password): ?array` — query vendors table, check is_active and status='approved', password_verify
- `registerVendor(string $email, string $password, string $companyName, string $contactName, ?string $phone): array` — validate email/password, hash password, insert vendor with status='pending', return success/error array
- CSRF functions with `function_exists()` guards: `generateVendorCsrfToken()`, `verifyVendorCsrfToken()`, `vendorCsrfField()`

**Step 2: Commit**

```bash
git add subcontractor/includes/vendor-auth.php
git commit -m "feat(subcontractor): add vendor authentication service"
```

---

### Task 3: Build booking service

**Files:**
- Create: `subcontractor/includes/booking-service.php`

**Step 1: Write booking service**

Implement these functions:
- `createBookingRequest(int $eventId, int $vendorId, int $accountId, ?int $serviceId, string $message, string $eventDate, ?int $guestCount): array` — insert booking with status='requested', return success/error + booking_id
- `submitQuote(int $bookingId, int $vendorId, float $price, string $message): array` — verify vendor owns booking, update quoted_price, calculate depositum (25%), commission (15% of depositum), vendor_payout (85% of depositum), set status='quoted', insert booking_message
- `acceptQuote(int $bookingId, int $accountId): array` — verify organizer owns booking via event, set status='accepted', return booking data for payment
- `confirmBooking(int $bookingId): bool` — set status='deposited' (called after Stripe payment)
- `vendorConfirmBooking(int $bookingId, int $vendorId): bool` — set status='confirmed'
- `completeBooking(int $bookingId, int $accountId): bool` — set status='completed'
- `cancelBooking(int $bookingId, string $cancelledBy, string $reason): bool` — set status='cancelled'
- `getBooking(int $bookingId): ?array` — fetch booking with vendor and event data joined
- `getEventBookings(int $eventId): array` — all bookings for an event
- `getVendorBookings(int $vendorId, ?string $status): array` — vendor's bookings with optional status filter
- `sendBookingMessage(int $bookingId, string $senderType, string $message): bool` — insert into booking_messages
- `getBookingMessages(int $bookingId): array` — ordered by created_at ASC
- `markMessagesRead(int $bookingId, string $readerType): void` — mark is_read=1

Constants: `DEPOSITUM_RATE = 0.25`, `COMMISSION_RATE = 0.15`

**Step 2: Commit**

```bash
git add subcontractor/includes/booking-service.php
git commit -m "feat(subcontractor): add booking service with state machine"
```

---

### Task 4: Build payment service (Stripe Connect)

**Files:**
- Create: `subcontractor/includes/payment-service.php`

**Step 1: Write payment service**

Implement these functions using `stripeRequest()` from `includes/stripe.php`:
- `createConnectAccount(string $email, string $companyName): ?string` — POST to /accounts with type=express, return account_id
- `createOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): ?string` — POST to /account_links, return url
- `isOnboardingComplete(string $accountId): bool` — GET /accounts/{id}, check charges_enabled && payouts_enabled
- `createDepositumCheckout(array $booking, string $successUrl, string $cancelUrl): ?array` — create Checkout Session with payment_intent_data.application_fee_amount and transfer_data.destination, mode='payment' (not subscription)
- `refundPayment(string $paymentIntentId): ?array` — POST to /refunds
- `reverseTransfer(string $transferId): ?array` — POST to /transfers/{id}/reversals
- `getPaymentIntent(string $paymentIntentId): ?array` — GET /payment_intents/{id}

Add new env vars to `.env.example`:
```
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_connect_XXXXXXXX
```

**Step 2: Commit**

```bash
git add subcontractor/includes/payment-service.php .env.example
git commit -m "feat(subcontractor): add Stripe Connect payment service"
```

---

### Task 5: Build commission service

**Files:**
- Create: `subcontractor/includes/commission-service.php`

**Step 1: Write commission service**

Implement these functions:
- `calculateDepositum(float $quotedPrice): float` — returns quotedPrice * 0.25
- `calculateCommission(float $depositumAmount): float` — returns depositumAmount * 0.15
- `calculateVendorPayout(float $depositumAmount): float` — returns depositumAmount * 0.85
- `getVendorEarnings(int $vendorId, ?string $fromDate, ?string $toDate): array` — aggregate booking payments (total earned, total commission, total paid out)
- `getPlatformRevenue(?string $fromDate, ?string $toDate): array` — aggregate all commissions (total bookings, total depositum, total commission, total vendor payouts)

**Step 2: Commit**

```bash
git add subcontractor/includes/commission-service.php
git commit -m "feat(subcontractor): add commission calculation service"
```

---

### Task 6: Build review service

**Files:**
- Create: `subcontractor/includes/review-service.php`

**Step 1: Write review service**

Implement these functions:
- `createReview(int $bookingId, int $vendorId, int $accountId, int $rating, ?string $title, ?string $text): array` — validate rating 1-5, check booking is completed, check no existing review, insert, update vendor avg_rating and review_count
- `respondToReview(int $reviewId, int $vendorId, string $response): bool` — verify vendor owns review, update vendor_response and vendor_responded_at
- `getVendorReviews(int $vendorId, int $limit, int $offset): array` — ordered by created_at DESC with account name joined
- `getReviewForBooking(int $bookingId): ?array`
- `updateVendorRatingStats(int $vendorId): void` — recalculate avg_rating and review_count from vendor_reviews

**Step 2: Commit**

```bash
git add subcontractor/includes/review-service.php
git commit -m "feat(subcontractor): add review service"
```

---

### Task 7: Build notification service

**Files:**
- Create: `subcontractor/includes/notification-service.php`

**Step 1: Write notification service**

Implement these functions using the existing `EmailService` class pattern from `includes/email-service.php`:
- `notifyVendorNewRequest(array $booking, array $vendor): array` — email vendor about new booking request
- `notifyOrganizerQuoteReceived(array $booking, array $organizer): array` — email organizer about vendor quote
- `notifyVendorQuoteAccepted(array $booking, array $vendor): array` — email vendor about accepted quote
- `notifyBothPaymentConfirmed(array $booking, array $vendor, array $organizer): array` — email both about confirmed payment
- `notifyBothCancellation(array $booking, array $vendor, array $organizer, string $cancelledBy): array`
- `notifyVendorNewReview(array $review, array $vendor): array`
- `notifyBothRefund(array $booking, array $vendor, array $organizer): array`

Each function builds an HTML email using ob_start/include pattern and calls EmailService::sendViaSendGrid internally.

**Step 2: Create email templates**

Create simple HTML email templates:
- `subcontractor/includes/email-templates/booking-request.php`
- `subcontractor/includes/email-templates/quote-received.php`
- `subcontractor/includes/email-templates/payment-confirmed.php`
- `subcontractor/includes/email-templates/booking-cancelled.php`
- `subcontractor/includes/email-templates/review-received.php`
- `subcontractor/includes/email-templates/refund-confirmed.php`

Follow the existing invitation email template style (inline CSS, responsive, Nordic branding).

**Step 3: Commit**

```bash
git add subcontractor/includes/notification-service.php subcontractor/includes/email-templates/
git commit -m "feat(subcontractor): add notification service with email templates"
```

---

### Task 8: Build vendor validation service

**Files:**
- Create: `subcontractor/includes/vendor-validation.php`

**Step 1: Write validation functions**

Implement:
- `validateVendorRegistration(array $data): array` — validate email, password (8+), company_name, contact_name. Return errors array.
- `validateVendorProfile(array $data): array` — validate company_name, contact_name, description (max 5000), short_description (max 500), phone, website (valid URL), city, postal_code
- `validateService(array $data): array` — validate title, price_from (positive number), price_to (>= price_from if set), price_unit (in enum)
- `validateBookingRequest(array $data): array` — validate event_date (future date), message (not empty, max 2000), guest_count (positive int if set)
- `validateReview(array $data): array` — validate rating (1-5), title (max 255), review_text (max 5000)
- `validateImageUpload(array $file): array` — reuse pattern from `includes/functions.php` validateImageUpload with finfo content-type check

**Step 2: Commit**

```bash
git add subcontractor/includes/vendor-validation.php
git commit -m "feat(subcontractor): add input validation service"
```

---

## Phase 2: Vendor Dashboard

### Task 9: Build vendor dashboard layout

**Files:**
- Create: `subcontractor/includes/vendor-header.php`
- Create: `subcontractor/includes/vendor-footer.php`
- Create: `subcontractor/assets/css/subcontractor.css`

**Step 1: Write vendor header**

Follow exact pattern from `includes/app-header.php`: sidebar navigation with links to dashboard pages (Oversigt, Profil, Ydelser, Galleri, Bookinger, Kalender, Indtjening, Anmeldelser). Mobile hamburger toggle. Include `subcontractor.css`. Show vendor company name and unread message count in nav.

**Step 2: Write vendor footer**

Simple footer matching `includes/app-footer.php` pattern. Close main content div, close body/html.

**Step 3: Write CSS**

Use existing Nordic design system CSS custom properties. Add vendor-specific styles for dashboard cards, booking status badges, calendar grid, earnings charts. Use `@skill frontend-design` for high-quality implementation. Follow existing breakpoints (1024px, 768px, 640px, 480px).

**Step 4: Commit**

```bash
git add subcontractor/includes/vendor-header.php subcontractor/includes/vendor-footer.php subcontractor/assets/css/subcontractor.css
git commit -m "feat(subcontractor): add vendor dashboard layout and styles"
```

---

### Task 10: Build vendor registration page

**Files:**
- Create: `subcontractor/dashboard/register.php`

**Step 1: Write registration page**

Multi-step form: 1) Company info (name, contact, email, phone), 2) Password, 3) Select categories (checkboxes), 4) Short description. On POST: validate with `validateVendorRegistration()`, call `registerVendor()`, insert vendor_category_links, redirect to login with success flash. Style with Nordic auth page pattern (matching `/app/auth/register.php` design).

**Step 2: Commit**

```bash
git add subcontractor/dashboard/register.php
git commit -m "feat(subcontractor): add vendor registration page"
```

---

### Task 11: Build vendor login/logout

**Files:**
- Create: `subcontractor/dashboard/login.php`
- Create: `subcontractor/dashboard/logout.php`

**Step 1: Write login page**

Follow exact pattern from `/app/auth/login.php`. CSRF protection, rate limiting (reuse `isLoginRateLimited`/`recordLoginAttempt` pattern). Check vendor status is 'approved' before allowing login. Flash message for pending/rejected status. Nordic auth design.

**Step 2: Write logout**

Call `vendorLogout()`, redirect to login page with flash message.

**Step 3: Commit**

```bash
git add subcontractor/dashboard/login.php subcontractor/dashboard/logout.php
git commit -m "feat(subcontractor): add vendor login and logout"
```

---

### Task 12: Build vendor dashboard home

**Files:**
- Create: `subcontractor/dashboard/index.php`

**Step 1: Write dashboard**

Require vendor login. Show stats cards: active bookings, pending requests, total earned, average rating. Show upcoming bookings list (next 5, sorted by event_date). Show unread messages count. Show recent reviews. Use stat-card pattern from existing dashboard.

**Step 2: Commit**

```bash
git add subcontractor/dashboard/index.php
git commit -m "feat(subcontractor): add vendor dashboard home"
```

---

### Task 13: Build vendor profile editor

**Files:**
- Create: `subcontractor/dashboard/profile.php`

**Step 1: Write profile page**

Form to edit: company_name, contact_name, phone, website, description, short_description, address, city, postal_code, nationwide checkbox, service_areas (textarea, comma-separated cities). Logo upload and cover image upload using `validateImageUpload()` and `generateUploadFilename()`. Category selection (checkboxes). On POST: validate, update vendors table, sync vendor_category_links. Handle file uploads to `uploads/vendors/`.

**Step 2: Create uploads directory**

```bash
mkdir -p uploads/vendors
```

Add to `.gitignore`:
```
uploads/vendors/*
!uploads/vendors/.gitkeep
```

**Step 3: Commit**

```bash
git add subcontractor/dashboard/profile.php uploads/vendors/.gitkeep .gitignore
git commit -m "feat(subcontractor): add vendor profile editor"
```

---

### Task 14: Build vendor services management

**Files:**
- Create: `subcontractor/dashboard/services.php`

**Step 1: Write services page**

CRUD for vendor_services. List all services with edit/delete. Modal for add/edit: title, description, price_from, price_to, price_unit (dropdown: fast pris / pr. person / pr. time), duration_hours, is_active toggle. Drag-and-drop sort order (simple JS). Follow budget.php modal pattern.

**Step 2: Commit**

```bash
git add subcontractor/dashboard/services.php
git commit -m "feat(subcontractor): add vendor services management"
```

---

### Task 15: Build vendor gallery management

**Files:**
- Create: `subcontractor/dashboard/gallery.php`

**Step 1: Write gallery page**

Photo grid with upload form. Max 20 images per vendor. Upload via file input, validate with `validateImageUpload()`, save to `uploads/vendors/gallery/`. Caption editing inline. Delete with confirmation. Drag-and-drop sort order. Follow photos.php pattern.

**Step 2: Commit**

```bash
git add subcontractor/dashboard/gallery.php
git commit -m "feat(subcontractor): add vendor gallery management"
```

---

### Task 16: Build vendor bookings & messaging

**Files:**
- Create: `subcontractor/dashboard/bookings.php`
- Create: `subcontractor/dashboard/booking-detail.php`

**Step 1: Write bookings list**

List all vendor bookings with status filter tabs (Alle, Forespørgsler, Aktive, Afsluttede). Show: event date, organizer name, service, quoted price, status badge. Link to detail page.

**Step 2: Write booking detail page**

Show full booking info: event details, organizer message, quoted price breakdown (depositum, commission, payout). Action buttons based on status:
- `requested` → Quote form (price + message)
- `accepted` → "Afventer betaling" notice
- `deposited` → "Bekræft booking" button
- `confirmed` → Event info, countdown
- `completed` → "Afsluttet" badge

Message thread below (like chat). Input for new messages. Mark messages as read on page load.

**Step 3: Commit**

```bash
git add subcontractor/dashboard/bookings.php subcontractor/dashboard/booking-detail.php
git commit -m "feat(subcontractor): add vendor booking management and messaging"
```

---

### Task 17: Build vendor calendar and earnings

**Files:**
- Create: `subcontractor/dashboard/calendar.php`
- Create: `subcontractor/dashboard/earnings.php`
- Create: `subcontractor/dashboard/reviews.php`

**Step 1: Write calendar page**

Monthly calendar grid showing confirmed bookings. Clickable dates link to booking detail. Use simple HTML table grid with CSS styling. No JS library needed.

**Step 2: Write earnings page**

Show: total earned (all time), this month, pending payouts. Table of completed bookings with: date, service, quoted price, depositum, commission, payout. Summary row with totals. Stripe Connect onboarding banner if not complete — link to Stripe onboarding.

**Step 3: Write reviews page**

List all reviews with rating stars, reviewer name, date, text. Vendor can respond to each review (form below review). Show average rating and total count at top.

**Step 4: Commit**

```bash
git add subcontractor/dashboard/calendar.php subcontractor/dashboard/earnings.php subcontractor/dashboard/reviews.php
git commit -m "feat(subcontractor): add vendor calendar, earnings, and reviews pages"
```

---

## Phase 3: Marketplace Public Pages

### Task 18: Build marketplace browse page

**Files:**
- Create: `subcontractor/index.php`
- Create: `subcontractor/includes/marketplace-header.php`
- Create: `subcontractor/.htaccess`

**Step 1: Write .htaccess for clean URLs**

Rewrite `/subcontractor/kategori/fotograf` to `category.php?slug=fotograf`, `/subcontractor/leverandor/123` to `profile.php?id=123`.

**Step 2: Write marketplace header**

Public-facing header with search bar, category navigation. Different from vendor dashboard header. Use Nordic design. Link to vendor registration.

**Step 3: Write marketplace index page**

@skill frontend-design — Create a visually distinctive marketplace landing page.

Hero section with search. Category grid (cards with emoji icons, name, vendor count). Featured vendors section (is_featured=1). Recent reviews carousel. "Bliv leverandør" CTA. Filter sidebar: category, city, price range, minimum rating. Vendor cards in grid: cover image, company name, short_description, rating stars, price_from, city. Pagination.

**Step 4: Commit**

```bash
git add subcontractor/index.php subcontractor/includes/marketplace-header.php subcontractor/.htaccess
git commit -m "feat(subcontractor): add marketplace browse page with filters"
```

---

### Task 19: Build category page

**Files:**
- Create: `subcontractor/category.php`

**Step 1: Write category page**

Show all vendors in a category. Same card grid as index but filtered. Sort options: rating, price, newest. Filter by city/area. Show category name, icon, description, vendor count at top.

**Step 2: Commit**

```bash
git add subcontractor/category.php
git commit -m "feat(subcontractor): add marketplace category page"
```

---

### Task 20: Build vendor profile page

**Files:**
- Create: `subcontractor/profile.php`

**Step 1: Write vendor profile**

@skill frontend-design — Create a compelling vendor profile page.

Cover image hero. Logo, company name, rating stars, review count, city, response time. Tabs: Ydelser, Galleri, Anmeldelser. Services list with prices and "Send forespørgsel" button per service. Photo gallery with lightbox (simple CSS modal). Reviews with rating distribution bar chart. Contact info (shown after login). Increment view_count on load.

**Step 2: Commit**

```bash
git add subcontractor/profile.php
git commit -m "feat(subcontractor): add vendor profile page"
```

---

### Task 21: Build booking request flow

**Files:**
- Create: `subcontractor/book.php`

**Step 1: Write booking request page**

Require account login (organizer must be logged in). Select which event to book for (dropdown of user's events). Pre-fill event date and guest count. Message textarea. Vendor and service info displayed. On POST: validate with `validateBookingRequest()`, call `createBookingRequest()`, send notification to vendor, redirect to event vendor tab with success flash.

**Step 2: Commit**

```bash
git add subcontractor/book.php
git commit -m "feat(subcontractor): add booking request flow"
```

---

### Task 22: Build Stripe Connect payment flow

**Files:**
- Create: `subcontractor/payment.php`
- Create: `subcontractor/payment-success.php`
- Create: `subcontractor/payment-cancel.php`
- Create: `app/webhooks/stripe-connect.php`

**Step 1: Write payment initiation**

Require account login. Load booking (must be status='accepted'). Show payment summary: vendor, service, quoted price, depositum amount (25%), what's covered by the guarantee. "Betal depositum" button creates Stripe Checkout session via `createDepositumCheckout()` and redirects to Stripe.

**Step 2: Write payment success page**

Verify Stripe session via query param. Show confirmation: booking confirmed, vendor will be notified, depositum paid. Link back to event vendor tab.

**Step 3: Write payment cancel page**

Show cancellation notice. Link to retry or go back to event.

**Step 4: Write Stripe Connect webhook handler**

Follow exact pattern from `app/webhooks/stripe.php`. Verify signature with `STRIPE_CONNECT_WEBHOOK_SECRET`. Handle events:
- `checkout.session.completed` → call `confirmBooking()`, send notifications
- `charge.refunded` → update booking to 'refunded'
- `account.updated` → update vendor stripe_onboarding_complete

Add new env var `STRIPE_CONNECT_WEBHOOK_SECRET` to `.env.example`.

**Step 5: Commit**

```bash
git add subcontractor/payment.php subcontractor/payment-success.php subcontractor/payment-cancel.php app/webhooks/stripe-connect.php .env.example
git commit -m "feat(subcontractor): add Stripe Connect payment flow and webhooks"
```

---

## Phase 4: Event Integration

### Task 23: Add vendors tab to event management

**Files:**
- Create: `app/events/pages/vendors.php`
- Modify: `app/events/manage.php` (add 'vendors' to $validPages array and tab navigation)

**Step 1: Write vendors page**

No feature gate — available on all plans. Two sections:

**Marketplace Bookings:** Table of bookings for this event. Columns: vendor, service, status badge, quoted price, depositum, actions. Action buttons per status:
- `requested` → "Afventer svar"
- `quoted` → "Se tilbud" link to booking detail
- `accepted` → "Betal depositum" link
- `deposited`/`confirmed` → "Bekræftet" badge + message link
- `completed` → "Skriv anmeldelse" link

**Manual Vendors:** Table of manual_vendors for this event. Add button opens modal. Edit/delete per row. Fields: company_name, category (dropdown), contact_name, email, phone, agreed_price, is_paid, notes.

**Budget Summary:** Total vendor costs (marketplace + manual), amount paid, amount outstanding.

**Actions:** "Find leverandør" button links to marketplace. "Tilføj egen leverandør" opens modal.

**Step 2: Modify manage.php**

Add 'vendors' to `$validPages` array. Add tab link with vendor icon between 'budget' and 'settings' tabs. No feature gate check needed.

**Step 3: Commit**

```bash
git add app/events/pages/vendors.php app/events/manage.php
git commit -m "feat(subcontractor): add vendors tab to event management"
```

---

### Task 24: Build organizer booking detail page

**Files:**
- Create: `app/events/pages/vendor-booking.php`
- Modify: `app/events/manage.php` (add 'vendor-booking' to $validPages)

**Step 1: Write booking detail for organizer**

Show booking info: vendor profile (name, rating, contact), service details, quoted price breakdown. Status-specific actions:
- `quoted` → Accept/Decline buttons, vendor's quote message
- `accepted` → "Betal depositum" button linking to `/subcontractor/payment.php?booking_id=X`
- `deposited`/`confirmed` → Vendor contact info, event countdown
- `completed` → Review form (rating stars, title, text)
- Any status → "Annuller booking" with reason textarea
- `completed` with issues → "Anmod om refund" button

Message thread with input. Same pattern as vendor booking-detail.php.

**Step 2: Add to manage.php routing**

Add 'vendor-booking' to $validPages.

**Step 3: Commit**

```bash
git add app/events/pages/vendor-booking.php app/events/manage.php
git commit -m "feat(subcontractor): add organizer booking detail page"
```

---

### Task 25: Build budget integration

**Files:**
- Modify: `subcontractor/includes/booking-service.php` (add budget sync functions)

**Step 1: Add budget sync functions**

Add to booking-service.php:
- `syncBookingToBudget(int $bookingId): void` — when booking is confirmed, create/update a budget_item linked to the booking. Category mapped from vendor category. Estimated = quoted_price. Description = "Vendor: {company_name} - {service_title}".
- `syncManualVendorToBudget(int $manualVendorId): void` — create/update budget_item from manual_vendors. Estimated = agreed_price.
- `updateBudgetPaymentStatus(int $bookingId): void` — when booking payment status changes, update budget_item is_paid.

Call these functions from the appropriate places in the booking flow (confirmBooking, completeBooking, etc.).

**Step 2: Commit**

```bash
git add subcontractor/includes/booking-service.php
git commit -m "feat(subcontractor): add budget integration for bookings"
```

---

### Task 26: Stripe Connect vendor onboarding in dashboard

**Files:**
- Modify: `subcontractor/dashboard/earnings.php` (add onboarding flow)

**Step 1: Add onboarding UI**

If vendor's `stripe_onboarding_complete` is false, show prominent banner at top of earnings page: "Opsæt udbetaling" with explanation. Button calls `createConnectAccount()` if no stripe_account_id, then `createOnboardingLink()` and redirects to Stripe. Return URL updates vendor record. If onboarding in progress but not complete, show "Fortsæt opsætning" button.

**Step 2: Commit**

```bash
git add subcontractor/dashboard/earnings.php
git commit -m "feat(subcontractor): add Stripe Connect onboarding flow"
```

---

## Phase 5: Admin & Cleanup

### Task 27: Build admin vendor management

**Files:**
- Create: `admin-platform/vendors.php` (new, replaces partners.php)
- Create: `admin-platform/vendor-detail.php`
- Create: `admin-platform/vendor-payouts.php`

**Step 1: Write vendors list page**

Follow exact pattern from existing `admin-platform/partners.php`. Require platform admin auth. List all vendors with filters: status (pending/approved/rejected/suspended), category, city. Actions: approve, reject (with reason), suspend. Show stats at top: total vendors, pending, approved, total bookings, total commission earned.

**Step 2: Write vendor detail page**

Show full vendor profile, booking history, reviews, earnings, payout history. Edit vendor status. View Stripe Connect account status.

**Step 3: Write payouts page**

Table of all bookings with payment info. Columns: date, organizer, vendor, service, quoted price, depositum, commission, vendor payout, status. Filter by date range, status. Summary cards: total revenue, total commission, total paid out. Export to CSV.

**Step 4: Commit**

```bash
git add admin-platform/vendors.php admin-platform/vendor-detail.php admin-platform/vendor-payouts.php
git commit -m "feat(subcontractor): add admin vendor management and payouts"
```

---

### Task 28: Update admin sidebar navigation

**Files:**
- Modify: `includes/admin-platform-header.php` (replace Partners link with Vendors)

**Step 1: Update navigation**

Replace the "Partnere" link with "Leverandører" pointing to `/admin-platform/vendors.php`. Replace "Kommissioner" link with "Udbetalinger" pointing to `/admin-platform/vendor-payouts.php`. Keep same icon style.

**Step 2: Commit**

```bash
git add includes/admin-platform-header.php
git commit -m "feat(subcontractor): update admin navigation for vendor module"
```

---

### Task 29: Remove old partner marketplace code

**Files:**
- Delete: `partners/` (entire directory)
- Delete: `includes/partner-auth.php`
- Delete: `includes/partner-commission.php`
- Delete: `includes/partner-reviews.php`
- Delete: `includes/partner-verification.php`
- Delete: `includes/partner-header.php`
- Delete: old `admin-platform/partners.php` (already replaced)
- Delete: old `admin-platform/commissions.php` (replaced by vendor-payouts.php)
- Create: `database/migrations/016_drop_old_partner_tables.sql`

**Step 1: Create migration to drop old tables**

```sql
-- Migration 016: Remove old partner marketplace tables
-- These are replaced by the new vendor/subcontractor module

DROP TABLE IF EXISTS partner_inquiries;
DROP TABLE IF EXISTS partner_commissions;
DROP TABLE IF EXISTS partner_reviews;
DROP TABLE IF EXISTS partner_verification;
DROP TABLE IF EXISTS partner_gallery;
DROP TABLE IF EXISTS partners;
DROP TABLE IF EXISTS partner_categories;
```

**Step 2: Delete old files**

Remove all files listed above. Verify no other files reference them with grep.

**Step 3: Commit**

```bash
git add -A
git commit -m "refactor(subcontractor): remove old partner marketplace code"
```

---

### Task 30: Add subcontractor module JavaScript

**Files:**
- Create: `subcontractor/assets/js/subcontractor.js`

**Step 1: Write JavaScript**

Minimal vanilla JS for:
- Modal open/close (add/edit service, add manual vendor)
- Star rating input (click to select 1-5 stars on review form)
- Message auto-scroll (scroll to bottom of message thread)
- Sort order drag-and-drop for services (simple sortable with fetch POST)
- Booking status filter tabs (show/hide rows)
- Calendar month navigation (prev/next month via URL params)

Follow existing `assets/js/main.js` patterns. No libraries.

**Step 2: Commit**

```bash
git add subcontractor/assets/js/subcontractor.js
git commit -m "feat(subcontractor): add client-side JavaScript"
```

---

### Task 31: Write tests

**Files:**
- Create: `tests/subcontractor/BookingServiceTest.php`
- Create: `tests/subcontractor/CommissionServiceTest.php`
- Create: `tests/subcontractor/PaymentServiceTest.php`
- Create: `tests/subcontractor/VendorValidationTest.php`

**Step 1: Write commission tests**

Test `calculateDepositum()`, `calculateCommission()`, `calculateVendorPayout()` with various prices. Verify math: 10000 kr quoted → 2500 kr depositum → 375 kr commission → 2125 kr vendor payout.

**Step 2: Write validation tests**

Test all validation functions with valid and invalid data. Test edge cases: empty strings, too-long strings, invalid emails, negative prices, past dates, rating out of range.

**Step 3: Write booking state machine tests**

Test that bookings can only transition through valid states. Test that only the correct party can perform each action.

**Step 4: Commit**

```bash
git add tests/
git commit -m "test(subcontractor): add unit tests for services"
```

---

### Task 32: Final integration testing and polish

**Step 1: Verify all flows end-to-end**

- Register vendor → login → edit profile → add services → add gallery
- Browse marketplace → filter → view profile → send booking request
- Vendor receives request → submits quote → organizer accepts → pays depositum
- Booking confirmed → event happens → mark complete → write review
- Vendor responds to review
- Test refund flow: organizer requests refund → admin approves
- Test manual vendor: add → edit → mark paid → verify in budget
- Test budget integration: confirmed booking appears in budget

**Step 2: Verify responsive design**

Test all pages at 1024px, 768px, 640px, 480px breakpoints.

**Step 3: Verify security**

- CSRF on all forms
- Auth required on all protected pages
- Vendor can only see own bookings
- Organizer can only see own event bookings
- Input validation on all user input
- SQL injection prevention (all prepared statements)
- XSS prevention (all output escaped)

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(subcontractor): complete subcontractor module v1.0"
git push origin main
```

---

## File Inventory (new files: 35+)

### Services (8 files)
- `subcontractor/includes/vendor-auth.php`
- `subcontractor/includes/booking-service.php`
- `subcontractor/includes/payment-service.php`
- `subcontractor/includes/commission-service.php`
- `subcontractor/includes/review-service.php`
- `subcontractor/includes/notification-service.php`
- `subcontractor/includes/vendor-validation.php`
- `subcontractor/includes/email-templates/` (6 templates)

### Vendor Dashboard (12 files)
- `subcontractor/dashboard/login.php`
- `subcontractor/dashboard/register.php`
- `subcontractor/dashboard/logout.php`
- `subcontractor/dashboard/index.php`
- `subcontractor/dashboard/profile.php`
- `subcontractor/dashboard/services.php`
- `subcontractor/dashboard/gallery.php`
- `subcontractor/dashboard/bookings.php`
- `subcontractor/dashboard/booking-detail.php`
- `subcontractor/dashboard/calendar.php`
- `subcontractor/dashboard/earnings.php`
- `subcontractor/dashboard/reviews.php`

### Marketplace (5 files)
- `subcontractor/index.php`
- `subcontractor/category.php`
- `subcontractor/profile.php`
- `subcontractor/book.php`
- `subcontractor/.htaccess`

### Payment (4 files)
- `subcontractor/payment.php`
- `subcontractor/payment-success.php`
- `subcontractor/payment-cancel.php`
- `app/webhooks/stripe-connect.php`

### Event Integration (2 files)
- `app/events/pages/vendors.php`
- `app/events/pages/vendor-booking.php`

### Admin (3 files)
- `admin-platform/vendors.php`
- `admin-platform/vendor-detail.php`
- `admin-platform/vendor-payouts.php`

### Layout & Assets (4 files)
- `subcontractor/includes/vendor-header.php`
- `subcontractor/includes/vendor-footer.php`
- `subcontractor/includes/marketplace-header.php`
- `subcontractor/assets/css/subcontractor.css`
- `subcontractor/assets/js/subcontractor.js`

### Database (2 files)
- `database/migrations/015_subcontractor_module.sql`
- `database/migrations/016_drop_old_partner_tables.sql`

### Tests (4 files)
- `tests/subcontractor/BookingServiceTest.php`
- `tests/subcontractor/CommissionServiceTest.php`
- `tests/subcontractor/PaymentServiceTest.php`
- `tests/subcontractor/VendorValidationTest.php`
