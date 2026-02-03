<?php
/**
 * Email Service - SendGrid Integration
 * Handles sending invitation emails via SendGrid API
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/invitation-functions.php';

class EmailService {
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private ?PDO $db;

    public function __construct(?PDO $db = null) {
        $this->apiKey = env('SENDGRID_API_KEY', '');
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

        return "{$baseUrl}/e/{$slug}/?kode={$code}";
    }

    /**
     * Send email via SendGrid API
     */
    private function sendViaSendGrid(string $toEmail, string $toName, string $subject, string $html): array {
        $data = [
            'personalizations' => [
                [
                    'to' => [['email' => $toEmail, 'name' => $toName]],
                    'subject' => $subject
                ]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName
            ],
            'content' => [
                ['type' => 'text/html', 'value' => $html]
            ],
            'tracking_settings' => [
                'click_tracking' => ['enable' => true],
                'open_tracking' => ['enable' => true]
            ]
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
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
            // Extract message ID from response headers if available
            $responseData = json_decode($response, true);
            return [
                'success' => true,
                'message_id' => $responseData['x-message-id'] ?? null
            ];
        }

        $responseData = json_decode($response, true);
        $errorMessage = $responseData['errors'][0]['message'] ?? "HTTP fejl: $httpCode";

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
     * Process webhook event from SendGrid
     */
    public function processWebhook(array $events): void {
        if (!$this->db) {
            return;
        }

        foreach ($events as $event) {
            $messageId = $event['sg_message_id'] ?? null;
            $eventType = $event['event'] ?? '';

            if (!$messageId) {
                continue;
            }

            // Find email by external ID
            $stmt = $this->db->prepare("SELECT id FROM invitation_emails WHERE external_id LIKE ?");
            $stmt->execute([$messageId . '%']);
            $email = $stmt->fetch();

            if (!$email) {
                continue;
            }

            $statusMap = array(
                'delivered' => 'delivered',
                'open' => 'opened',
                'click' => 'clicked',
                'bounce' => 'bounced',
                'dropped' => 'bounced'
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
