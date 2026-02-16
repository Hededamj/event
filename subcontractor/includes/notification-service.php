<?php
/**
 * Notification Service
 * Handles sending booking-lifecycle email notifications to vendors and organizers.
 * Uses the platform EmailService and SendGrid integration.
 */

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/email-service.php';

// ============================================================
// Helper: get base URL for dashboard links
// ============================================================

function getAppBaseUrl(): string {
    return env('APP_URL', 'https://partyparart.dk');
}

// ============================================================
// 1. notifyVendorNewRequest
// ============================================================

/**
 * Notify a vendor about a new booking request from an organizer.
 *
 * @param array $booking Booking data from getBooking()
 * @param array $vendor  Vendor data (needs at least: email, company_name)
 * @return bool True if email was sent successfully
 */
function notifyVendorNewRequest(array $booking, array $vendor): bool {
    try {
        $to = $vendor['email'] ?? $booking['vendor_email'] ?? '';
        if (empty($to)) {
            error_log("notifyVendorNewRequest: No vendor email");
            return false;
        }

        $subject = "Ny bookingforespørgsel fra " . ($booking['account_name'] ?? 'en arrangør');

        $dashboardUrl = getAppBaseUrl() . '/subcontractor/dashboard.php';

        ob_start();
        include __DIR__ . '/email-templates/booking-request.php';
        $htmlBody = ob_get_clean();

        $emailService = new EmailService();
        $result = $emailService->sendViaSendGrid(
            $to,
            $vendor['company_name'] ?? $booking['vendor_company_name'] ?? 'Leverandør',
            $subject,
            $htmlBody
        );

        return $result['success'] ?? false;
    } catch (Exception $e) {
        error_log("notifyVendorNewRequest failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 2. notifyOrganizerQuoteReceived
// ============================================================

/**
 * Notify an organizer that a vendor has submitted a quote.
 *
 * @param array $booking   Booking data from getBooking()
 * @param array $organizer Organizer data (needs at least: email, name)
 * @return bool True if email was sent successfully
 */
function notifyOrganizerQuoteReceived(array $booking, array $organizer): bool {
    try {
        $to = $organizer['email'] ?? $booking['account_email'] ?? '';
        if (empty($to)) {
            error_log("notifyOrganizerQuoteReceived: No organizer email");
            return false;
        }

        $subject = "Tilbud modtaget fra " . ($booking['vendor_company_name'] ?? 'en leverandør');

        $dashboardUrl = getAppBaseUrl() . '/dashboard.php';

        ob_start();
        include __DIR__ . '/email-templates/quote-received.php';
        $htmlBody = ob_get_clean();

        $emailService = new EmailService();
        $result = $emailService->sendViaSendGrid(
            $to,
            $organizer['name'] ?? $booking['account_name'] ?? 'Arrangør',
            $subject,
            $htmlBody
        );

        return $result['success'] ?? false;
    } catch (Exception $e) {
        error_log("notifyOrganizerQuoteReceived failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 3. notifyVendorQuoteAccepted
// ============================================================

/**
 * Notify a vendor that their quote has been accepted by the organizer.
 *
 * @param array $booking Booking data from getBooking()
 * @param array $vendor  Vendor data (needs at least: email, company_name)
 * @return bool True if email was sent successfully
 */
function notifyVendorQuoteAccepted(array $booking, array $vendor): bool {
    try {
        $to = $vendor['email'] ?? $booking['vendor_email'] ?? '';
        if (empty($to)) {
            error_log("notifyVendorQuoteAccepted: No vendor email");
            return false;
        }

        $subject = "Dit tilbud er accepteret!";

        $dashboardUrl = getAppBaseUrl() . '/subcontractor/dashboard.php';

        ob_start();
        include __DIR__ . '/email-templates/booking-request.php';
        $htmlBody = ob_get_clean();

        $emailService = new EmailService();
        $result = $emailService->sendViaSendGrid(
            $to,
            $vendor['company_name'] ?? $booking['vendor_company_name'] ?? 'Leverandør',
            $subject,
            $htmlBody
        );

        return $result['success'] ?? false;
    } catch (Exception $e) {
        error_log("notifyVendorQuoteAccepted failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 4. notifyBothPaymentConfirmed
// ============================================================

/**
 * Notify both vendor and organizer that the deposit payment has been confirmed.
 *
 * @param array $booking   Booking data from getBooking()
 * @param array $vendor    Vendor data (needs at least: email, company_name)
 * @param array $organizer Organizer data (needs at least: email, name)
 * @return bool True if both emails were sent successfully
 */
function notifyBothPaymentConfirmed(array $booking, array $vendor, array $organizer): bool {
    try {
        $subject = "Booking bekræftet - depositum betalt";
        $dashboardUrl = getAppBaseUrl();
        $emailService = new EmailService();
        $allSent = true;

        // Send to vendor
        $vendorEmail = $vendor['email'] ?? $booking['vendor_email'] ?? '';
        if (!empty($vendorEmail)) {
            $recipientType = 'vendor';
            ob_start();
            include __DIR__ . '/email-templates/payment-confirmed.php';
            $htmlBody = ob_get_clean();

            $result = $emailService->sendViaSendGrid(
                $vendorEmail,
                $vendor['company_name'] ?? $booking['vendor_company_name'] ?? 'Leverandør',
                $subject,
                $htmlBody
            );
            if (!($result['success'] ?? false)) {
                $allSent = false;
            }
        } else {
            $allSent = false;
        }

        // Send to organizer
        $organizerEmail = $organizer['email'] ?? $booking['account_email'] ?? '';
        if (!empty($organizerEmail)) {
            $recipientType = 'organizer';
            ob_start();
            include __DIR__ . '/email-templates/payment-confirmed.php';
            $htmlBody = ob_get_clean();

            $result = $emailService->sendViaSendGrid(
                $organizerEmail,
                $organizer['name'] ?? $booking['account_name'] ?? 'Arrangør',
                $subject,
                $htmlBody
            );
            if (!($result['success'] ?? false)) {
                $allSent = false;
            }
        } else {
            $allSent = false;
        }

        return $allSent;
    } catch (Exception $e) {
        error_log("notifyBothPaymentConfirmed failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 5. notifyBothCancellation
// ============================================================

/**
 * Notify both vendor and organizer about a booking cancellation.
 *
 * @param array  $booking     Booking data from getBooking()
 * @param array  $vendor      Vendor data (needs at least: email, company_name)
 * @param array  $organizer   Organizer data (needs at least: email, name)
 * @param string $cancelledBy Who cancelled: 'organizer', 'vendor', or 'platform'
 * @return bool True if both emails were sent successfully
 */
function notifyBothCancellation(array $booking, array $vendor, array $organizer, string $cancelledBy): bool {
    try {
        $subject = "Booking annulleret";
        $emailService = new EmailService();
        $allSent = true;

        // Send to vendor
        $vendorEmail = $vendor['email'] ?? $booking['vendor_email'] ?? '';
        if (!empty($vendorEmail)) {
            $recipientType = 'vendor';
            ob_start();
            include __DIR__ . '/email-templates/booking-cancelled.php';
            $htmlBody = ob_get_clean();

            $result = $emailService->sendViaSendGrid(
                $vendorEmail,
                $vendor['company_name'] ?? $booking['vendor_company_name'] ?? 'Leverandør',
                $subject,
                $htmlBody
            );
            if (!($result['success'] ?? false)) {
                $allSent = false;
            }
        } else {
            $allSent = false;
        }

        // Send to organizer
        $organizerEmail = $organizer['email'] ?? $booking['account_email'] ?? '';
        if (!empty($organizerEmail)) {
            $recipientType = 'organizer';
            ob_start();
            include __DIR__ . '/email-templates/booking-cancelled.php';
            $htmlBody = ob_get_clean();

            $result = $emailService->sendViaSendGrid(
                $organizerEmail,
                $organizer['name'] ?? $booking['account_name'] ?? 'Arrangør',
                $subject,
                $htmlBody
            );
            if (!($result['success'] ?? false)) {
                $allSent = false;
            }
        } else {
            $allSent = false;
        }

        return $allSent;
    } catch (Exception $e) {
        error_log("notifyBothCancellation failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 6. notifyVendorNewReview
// ============================================================

/**
 * Notify a vendor that they received a new review.
 *
 * @param array $review Review data (needs at least: rating, title, review_text, reviewer_name)
 * @param array $vendor Vendor data (needs at least: email, company_name)
 * @return bool True if email was sent successfully
 */
function notifyVendorNewReview(array $review, array $vendor): bool {
    try {
        $to = $vendor['email'] ?? '';
        if (empty($to)) {
            error_log("notifyVendorNewReview: No vendor email");
            return false;
        }

        $subject = "Ny anmeldelse modtaget";

        $dashboardUrl = getAppBaseUrl() . '/subcontractor/dashboard.php';

        ob_start();
        include __DIR__ . '/email-templates/review-received.php';
        $htmlBody = ob_get_clean();

        $emailService = new EmailService();
        $result = $emailService->sendViaSendGrid(
            $to,
            $vendor['company_name'] ?? 'Leverandør',
            $subject,
            $htmlBody
        );

        return $result['success'] ?? false;
    } catch (Exception $e) {
        error_log("notifyVendorNewReview failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 7. notifyBothRefund
// ============================================================

/**
 * Notify both vendor and organizer that a refund has been processed.
 *
 * @param array $booking   Booking data from getBooking()
 * @param array $vendor    Vendor data (needs at least: email, company_name)
 * @param array $organizer Organizer data (needs at least: email, name)
 * @return bool True if both emails were sent successfully
 */
function notifyBothRefund(array $booking, array $vendor, array $organizer): bool {
    try {
        $subject = "Refundering bekræftet";
        $emailService = new EmailService();
        $allSent = true;

        // Send to vendor
        $vendorEmail = $vendor['email'] ?? $booking['vendor_email'] ?? '';
        if (!empty($vendorEmail)) {
            $recipientType = 'vendor';
            ob_start();
            include __DIR__ . '/email-templates/refund-confirmed.php';
            $htmlBody = ob_get_clean();

            $result = $emailService->sendViaSendGrid(
                $vendorEmail,
                $vendor['company_name'] ?? $booking['vendor_company_name'] ?? 'Leverandør',
                $subject,
                $htmlBody
            );
            if (!($result['success'] ?? false)) {
                $allSent = false;
            }
        } else {
            $allSent = false;
        }

        // Send to organizer
        $organizerEmail = $organizer['email'] ?? $booking['account_email'] ?? '';
        if (!empty($organizerEmail)) {
            $recipientType = 'organizer';
            ob_start();
            include __DIR__ . '/email-templates/refund-confirmed.php';
            $htmlBody = ob_get_clean();

            $result = $emailService->sendViaSendGrid(
                $organizerEmail,
                $organizer['name'] ?? $booking['account_name'] ?? 'Arrangør',
                $subject,
                $htmlBody
            );
            if (!($result['success'] ?? false)) {
                $allSent = false;
            }
        } else {
            $allSent = false;
        }

        return $allSent;
    } catch (Exception $e) {
        error_log("notifyBothRefund failed: " . $e->getMessage());
        return false;
    }
}
