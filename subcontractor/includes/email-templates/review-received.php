<?php
/**
 * Review Received Email Template
 * Sent to vendor when they receive a new review.
 *
 * Variables available:
 * - $review: Review data array (rating, title, review_text, reviewer_name)
 * - $vendor: Vendor data array
 * - $dashboardUrl: URL to vendor dashboard
 */

$sageGreen = '#A8B5A0';
$cream = '#FAF9F7';
$charcoal = '#2C2C2C';
$starColor = '#D4A843';

$rating = (int)($review['rating'] ?? 0);
$filledStars = str_repeat('&#9733;', $rating);
$emptyStars = str_repeat('&#9734;', 5 - $rating);
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ny anmeldelse</title>
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
                                    Ny anmeldelse
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
                                            Du har modtaget en ny anmeldelse
                                        </h1>

                                        <p style="margin: 0 0 28px; font-size: 16px; line-height: 1.7; color: #555555;">
                                            <?= htmlspecialchars($review['reviewer_name'] ?? 'En kunde') ?> har efterladt en anmeldelse af din ydelse.
                                        </p>

                                        <!-- Review Card -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: <?= $cream ?>; border-radius: 12px; margin-bottom: 28px;">
                                            <tr>
                                                <td style="padding: 24px;">
                                                    <!-- Stars -->
                                                    <div style="font-size: 28px; color: <?= $starColor ?>; margin-bottom: 12px; letter-spacing: 2px;">
                                                        <?= $filledStars ?><?= $emptyStars ?>
                                                    </div>

                                                    <?php if (!empty($review['title'])): ?>
                                                    <p style="margin: 0 0 10px; font-size: 17px; font-weight: 600; color: <?= $charcoal ?>;">
                                                        <?= htmlspecialchars($review['title']) ?>
                                                    </p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($review['review_text'])): ?>
                                                    <p style="margin: 0 0 12px; font-size: 15px; line-height: 1.7; color: #555555; font-style: italic;">
                                                        &ldquo;<?= nl2br(htmlspecialchars($review['review_text'])) ?>&rdquo;
                                                    </p>
                                                    <?php endif; ?>

                                                    <p style="margin: 0; font-size: 13px; color: #999999;">
                                                        &mdash; <?= htmlspecialchars($review['reviewer_name'] ?? 'Anonym') ?>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 0 0 28px; font-size: 15px; line-height: 1.7; color: #555555;">
                                            Du kan svare på anmeldelsen fra dit dashboard. Et venligt svar viser potentielle kunder at du sætter pris på feedback.
                                        </p>

                                        <!-- CTA Button -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <a href="<?= htmlspecialchars($dashboardUrl ?? '#') ?>"
                                                       style="display: inline-block; padding: 16px 44px; background-color: <?= $sageGreen ?>; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; border-radius: 12px;">
                                                        Se anmeldelse & svar
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
