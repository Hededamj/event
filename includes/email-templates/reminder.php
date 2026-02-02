<?php
/**
 * Reminder Email Template
 *
 * Variables available:
 * - $guest: Guest data array
 * - $event: Event data array
 * - $config: Invitation configuration array
 * - $greeting: Personalized greeting string
 * - $invitation_url: Full URL to invitation
 */

$eventDate = null;
$daysUntil = null;
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

    $now = new DateTime();
    $diff = $now->diff($date);
    $daysUntil = $diff->days;
}

$primaryColor = $config['color_secondary'] ?? '#8FA583';
$accentColor = $config['color_accent'] ?? '#B8923D';
$textColor = $config['color_text'] ?? '#1A1A1A';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Påmindelse</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 560px; margin: 0 auto;">

                    <!-- Header -->
                    <tr>
                        <td style="text-align: center; padding-bottom: 32px;">
                            <div style="display: inline-block; padding: 12px 24px; background-color: <?= htmlspecialchars($accentColor) ?>; border-radius: 8px;">
                                <span style="color: #ffffff; font-size: 14px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">
                                    Påmindelse
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Card -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

                                <!-- Countdown Banner -->
                                <?php if ($daysUntil !== null && $daysUntil > 0): ?>
                                <tr>
                                    <td style="background-color: <?= htmlspecialchars($accentColor) ?>; padding: 20px; text-align: center; border-radius: 16px 16px 0 0;">
                                        <span style="color: #ffffff; font-size: 14px; font-weight: 500;">
                                            <?php if ($daysUntil === 1): ?>
                                                Arrangementet er i morgen!
                                            <?php elseif ($daysUntil <= 7): ?>
                                                Kun <?= $daysUntil ?> dage til arrangementet!
                                            <?php else: ?>
                                                <?= $daysUntil ?> dage til arrangementet
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <!-- Content -->
                                <tr>
                                    <td style="padding: 48px 40px;">
                                        <p style="margin: 0 0 8px; font-size: 24px; color: <?= htmlspecialchars($textColor) ?>; font-weight: 400;">
                                            <?= htmlspecialchars($greeting) ?>
                                        </p>

                                        <h1 style="margin: 0 0 24px; font-size: 28px; font-weight: 600; color: <?= htmlspecialchars($textColor) ?>; line-height: 1.3;">
                                            Vi mangler stadig dit svar til<br><?= htmlspecialchars($event['name'] ?? 'arrangementet') ?>
                                        </h1>

                                        <p style="margin: 0 0 32px; font-size: 16px; line-height: 1.7; color: #555555;">
                                            Vi vil meget gerne vide om du kan deltage, så vi kan planlægge arrangementet bedst muligt.
                                            <?php if ($daysUntil !== null && $daysUntil <= 14): ?>
                                            <br><br>
                                            <strong>Giv venligst dit svar snarest muligt.</strong>
                                            <?php endif; ?>
                                        </p>

                                        <!-- Event Details -->
                                        <?php if ($eventDate): ?>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f8f8; border-radius: 12px; margin-bottom: 32px;">
                                            <tr>
                                                <td style="padding: 20px 24px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td valign="middle" style="width: 24px; padding-right: 12px;">
                                                                <div style="width: 8px; height: 8px; background-color: <?= htmlspecialchars($primaryColor) ?>; border-radius: 50%;"></div>
                                                            </td>
                                                            <td>
                                                                <span style="font-size: 15px; color: <?= htmlspecialchars($textColor) ?>;">
                                                                    <?= ucfirst($eventDate['weekday']) ?> d. <?= $eventDate['day'] ?>. <?= $eventDate['month'] ?> <?= $eventDate['year'] ?>
                                                                    <?php if ($eventDate['time']): ?>
                                                                    kl. <?= $eventDate['time'] ?>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php if (!empty($event['location'])): ?>
                                                        <tr>
                                                            <td valign="middle" style="width: 24px; padding-right: 12px; padding-top: 8px;">
                                                                <div style="width: 8px; height: 8px; background-color: <?= htmlspecialchars($primaryColor) ?>; border-radius: 50%;"></div>
                                                            </td>
                                                            <td style="padding-top: 8px;">
                                                                <span style="font-size: 15px; color: <?= htmlspecialchars($textColor) ?>;">
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
                                                        Giv dit svar nu
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 24px 0 0; font-size: 13px; color: #999999; text-align: center;">
                                            <a href="<?= htmlspecialchars($invitation_url) ?>" style="color: <?= htmlspecialchars($primaryColor) ?>;">
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
                                Denne påmindelse er sendt via PartyParart
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
