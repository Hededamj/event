<?php
/**
 * Email Service - Resend Integration
 * Handles sending invitation emails via Resend API
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/invitation-functions.php';

class EmailService {
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private ?PDO $db;

    public function __construct(?PDO $db = null) {
        $this->apiKey = env('RESEND_API_KEY', '');
        $this->fromEmail = env('EMAIL_FROM_ADDRESS', 'invitation@partyparart.dk');
        $this->fromName = env('EMAIL_FROM_NAME', 'PartyParart');
        $this->db = $db;
    }

    /**
     * Check if email service is configured
     */
    public function isConfigured(): bool {
        return !empty($this->apiKey);
    }

    /**
     * Send invitation email to a guest
     */
    public function sendInvitation(array $guest, array $event, array $invitationConfig): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Email service ikke konfigureret'];
        }

        if (empty($guest['email'])) {
            return ['success' => false, 'error' => 'Gæst har ingen email'];
        }

        // Log email attempt
        $emailId = null;
        if ($this->db) {
            $emailId = logInvitationEmail($this->db, $event['id'], $guest['id'], $guest['email'], 'invitation');
        }

        // Generate personalized content
        $greeting = personalizeGreeting($invitationConfig['greeting_template'] ?? 'Kære {guest_name}', $guest);
        $invitationUrl = $this->getInvitationUrl($event, $guest);

        // Render email HTML
        $html = $this->renderInvitationEmail([
            'guest' => $guest,
            'event' => $event,
            'config' => $invitationConfig,
            'greeting' => $greeting,
            'invitation_url' => $invitationUrl
        ]);

        // Generate subject
        $subject = "Du er inviteret til " . ($event['name'] ?? 'et arrangement');

        // Send via SendGrid
        $result = $this->sendViaSendGrid(
            $guest['email'],
            $guest['name'] ?? 'Gæst',
            $subject,
            $html
        );

        // Update email log
        if ($this->db && $emailId) {
            if ($result['success']) {
                updateEmailStatus($this->db, $emailId, 'sent', $result['message_id'] ?? null);
            } else {
                updateEmailStatus($this->db, $emailId, 'failed', null, $result['error'] ?? 'Ukendt fejl');
            }
        }

        return $result;
    }

    /**
     * Send reminder email to a guest
     */
    public function sendReminder(array $guest, array $event, array $invitationConfig): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Email service ikke konfigureret'];
        }

        if (empty($guest['email'])) {
            return ['success' => false, 'error' => 'Gæst har ingen email'];
        }

        // Log email attempt
        $emailId = null;
        if ($this->db) {
            $emailId = logInvitationEmail($this->db, $event['id'], $guest['id'], $guest['email'], 'reminder');
        }

        $greeting = personalizeGreeting($invitationConfig['greeting_template'] ?? 'Kære {guest_name}', $guest);
        $invitationUrl = $this->getInvitationUrl($event, $guest);

        $html = $this->renderReminderEmail([
            'guest' => $guest,
            'event' => $event,
            'config' => $invitationConfig,
            'greeting' => $greeting,
            'invitation_url' => $invitationUrl
        ]);

        $subject = "Påmindelse: " . ($event['name'] ?? 'Arrangement') . " - Vi mangler dit svar";

        $result = $this->sendViaSendGrid(
            $guest['email'],
            $guest['name'] ?? 'Gæst',
            $subject,
            $html
        );

        if ($this->db && $emailId) {
            if ($result['success']) {
                updateEmailStatus($this->db, $emailId, 'sent', $result['message_id'] ?? null);
            } else {
                updateEmailStatus($this->db, $emailId, 'failed', null, $result['error'] ?? 'Ukendt fejl');
            }
        }

        return $result;
    }

    /**
     * Send bulk invitations to multiple guests
     */
    public function sendBulkInvitations(array $guests, array $event, array $invitationConfig): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($guests as $guest) {
            $result = $this->sendInvitation($guest, $event, $invitationConfig);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'guest_id' => $guest['id'],
                    'name' => $guest['name'],
                    'error' => $result['error']
                ];
            }

            // Small delay to respect rate limits
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Get invitation URL for a guest
     */
    private function getInvitationUrl(array $event, array $guest): string {
        $baseUrl = env('APP_URL', 'https://partyparart.dk');
        $slug = $event['slug'] ?? '';
        $code = $guest['unique_code'] ?? '';

        return "{$baseUrl}/e/{$slug}/?g={$code}";
    }

    /**
     * Send email via Resend API
     */
    private function sendViaSendGrid(string $toEmail, string $toName, string $subject, string $html): array {
        $data = [
            'from' => $this->fromName . ' <' . $this->fromEmail . '>',
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $html
        ];

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "cURL fejl: $error"];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            return [
                'success' => true,
                'message_id' => $responseData['id'] ?? null
            ];
        }

        $responseData = json_decode($response, true);
        $errorMessage = $responseData['message'] ?? "HTTP fejl: $httpCode";

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Render invitation email HTML
     */
    private function renderInvitationEmail(array $data): string {
        ob_start();
        extract($data);
        include __DIR__ . '/email-templates/invitation.php';
        return ob_get_clean();
    }

    /**
     * Render reminder email HTML
     */
    private function renderReminderEmail(array $data): string {
        ob_start();
        extract($data);
        include __DIR__ . '/email-templates/reminder.php';
        return ob_get_clean();
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $email, string $token): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Email service ikke konfigureret'];
        }

        $baseUrl = env('APP_URL', 'https://partyparart.dk');
        $resetUrl = "{$baseUrl}/app/auth/forgot-password.php?token=" . urlencode($token);

        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">'
            . '<h2 style="color: #1a1a2e;">Nulstil din adgangskode</h2>'
            . '<p>Vi har modtaget en anmodning om at nulstille adgangskoden for din konto.</p>'
            . '<p>Klik på linket herunder for at vælge en ny adgangskode:</p>'
            . '<p style="margin: 24px 0;"><a href="' . htmlspecialchars($resetUrl) . '" '
            . 'style="background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block;">'
            . 'Nulstil adgangskode</a></p>'
            . '<p style="color: #6b7280; font-size: 14px;">Linket udløber om 1 time.</p>'
            . '<p style="color: #6b7280; font-size: 14px;">Hvis du ikke har anmodet om dette, kan du ignorere denne email.</p>'
            . '</body></html>';

        return $this->sendViaSendGrid($email, 'Bruger', 'Nulstil din adgangskode - PartyParart', $html);
    }

    /**
     * Process webhook event from Resend
     */
    public function processWebhook(array $events): void {
        if (!$this->db) {
            return;
        }

        foreach ($events as $event) {
            // Resend format: { type: "email.delivered", data: { email_id: "..." } }
            $eventType = $event['type'] ?? '';
            $messageId = $event['data']['email_id'] ?? null;

            if (!$messageId) {
                continue;
            }

            // Find email by external ID
            $stmt = $this->db->prepare("SELECT id FROM invitation_emails WHERE external_id = ?");
            $stmt->execute([$messageId]);
            $email = $stmt->fetch();

            if (!$email) {
                continue;
            }

            $statusMap = array(
                'email.delivered' => 'delivered',
                'email.opened' => 'opened',
                'email.clicked' => 'clicked',
                'email.bounced' => 'bounced',
                'email.complained' => 'bounced'
            );
            $status = isset($statusMap[$eventType]) ? $statusMap[$eventType] : null;

            if ($status) {
                updateEmailStatus($this->db, $email['id'], $status);
            }
        }
    }
}

/**
 * Get EmailService instance
 */
function getEmailService(?PDO $db = null): EmailService {
    return new EmailService($db);
}
