<?php
/**
 * Quote Received Email Template
 * Sent to organizer when a vendor submits a quote.
 *
 * Variables available:
 * - $booking: Booking data array from getBooking()
 * - $organizer: Organizer data array
 * - $dashboardUrl: URL to organizer dashboard
 */

$sageGreen = '#A8B5A0';
$cream = '#FAF9F7';
$charcoal = '#2C2C2C';

$eventDate = null;
$months = ['', 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
if (!empty($booking['event_date'])) {
    $date = new DateTime($booking['event_date']);
    $eventDate = [
        'day' => $date->format('j'),
        'month' => $months[(int)$date->format('n')],
        'year' => $date->format('Y'),
        'weekday' => ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag'][(int)$date->format('w')],
    ];
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tilbud modtaget</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: <?= $cream ?>;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: <?= $cream ?>;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 560px; margin: 0 auto;">

                    <!-- Logo -->
                    <tr>
                        <td style="text-align: center; padding-bottom: 32px;">
                            <span style="font-size: 24px; font-weight: 700; color: <?= $charcoal ?>; letter-spacing: 0.02em;">PartyParart</span>
                        </td>
                    </tr>

                    <!-- Badge -->
                    <tr>
                        <td style="text-align: center; padding-bottom: 24px;">
                            <div style="display: inline-block; padding: 10px 20px; background-color: <?= $sageGreen ?>; border-radius: 8px;">
                                <span style="color: #ffffff; font-size: 13px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">
                                    Tilbud modtaget
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Card -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06);">
                                <tr>
                                    <td style="padding: 44px 40px;">

                                        <h1 style="margin: 0 0 16px; font-size: 26px; font-weight: 600; color: <?= $charcoal ?>; line-height: 1.3;">
                                            Du har modtaget et tilbud
                                        </h1>

                                        <p style="margin: 0 0 28px; font-size: 16px; line-height: 1.7; color: #555555;">
                                            <strong><?= htmlspecialchars($booking['vendor_company_name'] ?? 'En leverandør') ?></strong> har sendt et tilbud<?php if (!empty($booking['event_title'])): ?> til dit arrangement <strong><?= htmlspecialchars($booking['event_title']) ?></strong><?php endif; ?>.
                                            Se detaljerne nedenfor og beslut om du vil acceptere.
                                        </p>

                                        <!-- Quote Details -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: <?= $cream ?>; border-radius: 12px; margin-bottom: 28px;">
                                            <tr>
                                                <td style="padding: 24px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding-bottom: 14px;">
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Leverandør</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>;">
                                                                    <?= htmlspecialchars($booking['vendor_company_name'] ?? 'Ikke angivet') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php if (!empty($booking['service_title'])): ?>
                                                        <tr>
                                                            <td style="padding-bottom: 14px;">
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Ydelse</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>;">
                                                                    <?= htmlspecialchars($booking['service_title']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if ($eventDate): ?>
                                                        <tr>
                                                            <td style="padding-bottom: 14px;">
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Dato</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>;">
                                                                    <?= ucfirst($eventDate['weekday']) ?> d. <?= $eventDate['day'] ?>. <?= $eventDate['month'] ?> <?= $eventDate['year'] ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if (!empty($booking['quoted_price'])): ?>
                                                        <tr>
                                                            <td style="padding-bottom: 14px;">
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Samlet pris</strong><br>
                                                                <span style="font-size: 20px; color: <?= $charcoal ?>; font-weight: 700;">
                                                                    <?= number_format((float)$booking['quoted_price'], 2, ',', '.') ?> DKK
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if (!empty($booking['depositum_amount'])): ?>
                                                        <tr>
                                                            <td>
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Depositum (25%)</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>;">
                                                                    <?= number_format((float)$booking['depositum_amount'], 2, ',', '.') ?> DKK
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <?php if (!empty($booking['vendor_message'])): ?>
                                        <p style="margin: 0 0 28px; font-size: 15px; line-height: 1.7; color: #555555; padding: 16px; background-color: <?= $cream ?>; border-left: 3px solid <?= $sageGreen ?>; border-radius: 0 8px 8px 0;">
                                            <strong style="color: <?= $charcoal ?>;">Besked fra leverandør:</strong><br>
                                            <?= nl2br(htmlspecialchars($booking['vendor_message'])) ?>
                                        </p>
                                        <?php endif; ?>

                                        <!-- CTA Button -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <a href="<?= htmlspecialchars($dashboardUrl ?? '#') ?>"
                                                       style="display: inline-block; padding: 16px 44px; background-color: <?= $sageGreen ?>; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; border-radius: 12px;">
                                                        Se tilbud & accepter
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

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
                                Du modtager denne email fordi du har en aktiv booking.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
