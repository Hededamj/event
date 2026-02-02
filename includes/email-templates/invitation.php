<?php
/**
 * Invitation Email Template
 *
 * Variables available:
 * - $guest: Guest data array
 * - $event: Event data array
 * - $config: Invitation configuration array
 * - $greeting: Personalized greeting string
 * - $invitation_url: Full URL to invitation
 */

$eventDate = null;
$months = ['', 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
if (!empty($event['event_date'])) {
    $date = new DateTime($event['event_date']);
    $eventDate = [
        'day' => $date->format('j'),
        'month' => $months[(int)$date->format('n')],
        'year' => $date->format('Y'),
        'weekday' => ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag'][(int)$date->format('w')],
        'time' => !empty($event['event_time']) ? (new DateTime($event['event_time']))->format('H:i') : null
    ];
}

$primaryColor = $config['color_secondary'] ?? '#8FA583';
$textColor = $config['color_text'] ?? '#1A1A1A';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 560px; margin: 0 auto;">

                    <!-- Header -->
                    <tr>
                        <td style="text-align: center; padding-bottom: 32px;">
                            <div style="display: inline-block; padding: 12px 24px; background-color: <?= htmlspecialchars($primaryColor) ?>; border-radius: 8px;">
                                <span style="color: #ffffff; font-size: 14px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">
                                    Invitation
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Card -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

                                <!-- Greeting & Content -->
                                <tr>
                                    <td style="padding: 48px 40px;">
                                        <p style="margin: 0 0 8px; font-size: 28px; color: <?= htmlspecialchars($textColor) ?>; font-weight: 400;">
                                            <?= htmlspecialchars($greeting) ?>
                                        </p>

                                        <h1 style="margin: 0 0 24px; font-size: 32px; font-weight: 600; color: <?= htmlspecialchars($textColor) ?>; line-height: 1.2;">
                                            Du er inviteret til<br><?= htmlspecialchars($event['name'] ?? 'et arrangement') ?>
                                        </h1>

                                        <?php if (!empty($config['invitation_message'])): ?>
                                        <p style="margin: 0 0 32px; font-size: 16px; line-height: 1.7; color: #555555;">
                                            <?= nl2br(htmlspecialchars($config['invitation_message'])) ?>
                                        </p>
                                        <?php endif; ?>

                                        <!-- Event Details -->
                                        <?php if ($eventDate): ?>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f8f8; border-radius: 12px; margin-bottom: 32px;">
                                            <tr>
                                                <td style="padding: 24px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding-bottom: 16px;">
                                                                <strong style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= htmlspecialchars($primaryColor) ?>;">Dato</strong><br>
                                                                <span style="font-size: 16px; color: <?= htmlspecialchars($textColor) ?>;">
                                                                    <?= ucfirst($eventDate['weekday']) ?> d. <?= $eventDate['day'] ?>. <?= $eventDate['month'] ?> <?= $eventDate['year'] ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php if ($eventDate['time']): ?>
                                                        <tr>
                                                            <td style="padding-bottom: 16px;">
                                                                <strong style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= htmlspecialchars($primaryColor) ?>;">Tidspunkt</strong><br>
                                                                <span style="font-size: 16px; color: <?= htmlspecialchars($textColor) ?>;">
                                                                    Kl. <?= $eventDate['time'] ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if (!empty($event['location'])): ?>
                                                        <tr>
                                                            <td>
                                                                <strong style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= htmlspecialchars($primaryColor) ?>;">Sted</strong><br>
                                                                <span style="font-size: 16px; color: <?= htmlspecialchars($textColor) ?>;">
                                                                    <?= htmlspecialchars($event['location']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <?php endif; ?>

                                        <!-- CTA Button -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <a href="<?= htmlspecialchars($invitation_url) ?>"
                                                       style="display: inline-block; padding: 18px 48px; background-color: <?= htmlspecialchars($primaryColor) ?>; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 12px;">
                                                        Se invitation & svar
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 24px 0 0; font-size: 13px; color: #999999; text-align: center;">
                                            Hvis knappen ikke virker, kan du kopiere dette link:<br>
                                            <a href="<?= htmlspecialchars($invitation_url) ?>" style="color: <?= htmlspecialchars($primaryColor) ?>; word-break: break-all;">
                                                <?= htmlspecialchars($invitation_url) ?>
                                            </a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 32px 20px; text-align: center;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #999999;">
                                Denne email er sendt via PartyParart
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #bbbbbb;">
                                Din personlige kode: <strong><?= htmlspecialchars($guest['unique_code'] ?? '') ?></strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
