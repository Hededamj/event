<?php
/**
 * Booking Request Email Template
 * Sent to vendor when a new booking request arrives, or when their quote is accepted.
 *
 * Variables available:
 * - $booking: Booking data array from getBooking()
 * - $vendor: Vendor data array
 * - $subject: Email subject (used to detect context: new request vs accepted quote)
 * - $dashboardUrl: URL to vendor dashboard
 */

$sageGreen = '#A8B5A0';
$cream = '#FAF9F7';
$charcoal = '#2C2C2C';

$isAccepted = (strpos($subject ?? '', 'accepteret') !== false);

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
    <title><?= $isAccepted ? 'Tilbud accepteret' : 'Ny bookingforespørgsel' ?></title>
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
                                    <?= $isAccepted ? 'Tilbud accepteret' : 'Ny forespørgsel' ?>
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
                                            <?php if ($isAccepted): ?>
                                                Dit tilbud er blevet accepteret!
                                            <?php else: ?>
                                                Du har modtaget en ny bookingforespørgsel
                                            <?php endif; ?>
                                        </h1>

                                        <p style="margin: 0 0 28px; font-size: 16px; line-height: 1.7; color: #555555;">
                                            <?php if ($isAccepted): ?>
                                                <?= htmlspecialchars($booking['account_name'] ?? 'Arrangøren') ?> har accepteret dit tilbud<?php if (!empty($booking['event_title'])): ?> til <strong><?= htmlspecialchars($booking['event_title']) ?></strong><?php endif; ?>.
                                                Betaling af depositum afventes nu.
                                            <?php else: ?>
                                                <?= htmlspecialchars($booking['account_name'] ?? 'En arrangør') ?> vil gerne booke dig<?php if (!empty($booking['event_title'])): ?> til <strong><?= htmlspecialchars($booking['event_title']) ?></strong><?php endif; ?>.
                                                Se detaljerne nedenfor og send et tilbud.
                                            <?php endif; ?>
                                        </p>

                                        <!-- Booking Details -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: <?= $cream ?>; border-radius: 12px; margin-bottom: 28px;">
                                            <tr>
                                                <td style="padding: 24px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding-bottom: 14px;">
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Arrangement</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>;">
                                                                    <?= htmlspecialchars($booking['event_title'] ?? 'Ikke angivet') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
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
                                                        <?php if (!empty($booking['guest_count'])): ?>
                                                        <tr>
                                                            <td style="padding-bottom: 14px;">
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Antal gæster</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>;">
                                                                    <?= htmlspecialchars($booking['guest_count']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <tr>
                                                            <td>
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Arrangør</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>;">
                                                                    <?= htmlspecialchars($booking['account_name'] ?? 'Ikke angivet') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php if ($isAccepted && !empty($booking['quoted_price'])): ?>
                                                        <tr>
                                                            <td style="padding-top: 14px;">
                                                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: <?= $sageGreen ?>;">Accepteret pris</strong><br>
                                                                <span style="font-size: 15px; color: <?= $charcoal ?>; font-weight: 600;">
                                                                    <?= number_format((float)$booking['quoted_price'], 2, ',', '.') ?> DKK
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <?php if (!empty($booking['organizer_message']) && !$isAccepted): ?>
                                        <p style="margin: 0 0 28px; font-size: 15px; line-height: 1.7; color: #555555; padding: 16px; background-color: <?= $cream ?>; border-left: 3px solid <?= $sageGreen ?>; border-radius: 0 8px 8px 0;">
                                            <strong style="color: <?= $charcoal ?>;">Besked fra arrangør:</strong><br>
                                            <?= nl2br(htmlspecialchars($booking['organizer_message'])) ?>
                                        </p>
                                        <?php endif; ?>

                                        <!-- CTA Button -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <a href="<?= htmlspecialchars($dashboardUrl ?? '#') ?>"
                                                       style="display: inline-block; padding: 16px 44px; background-color: <?= $sageGreen ?>; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; border-radius: 12px;">
                                                        <?= $isAccepted ? 'Se booking' : 'Se forespørgsel & send tilbud' ?>
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
                                Du modtager denne email fordi du er registreret som leverandør.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
