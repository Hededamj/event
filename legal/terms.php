<?php
/**
 * Terms of Service Page
 */
$pageTitle = 'Vilkår og betingelser';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vilkår og betingelser - Partypart</title>
    <style>
        :root {
            --accent: #6B8F5E;
            --surface: #FAF9F7;
            --border-light: #EDEAE5;
            --border: #E2DED8;
            --text-secondary: #6B6560;
            --text: #1A1A1A;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.7;
            color: var(--text);
            background: var(--surface);
        }

        .header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
            text-decoration: none;
        }

        .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            color: var(--accent);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        h1 {
            font-size: 32px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .last-updated {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 40px;
        }

        .content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        h2 {
            font-size: 20px;
            color: var(--text);
            margin: 32px 0 16px;
            padding-top: 24px;
            border-top: 1px solid var(--border-light);
        }

        h2:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }

        h3 {
            font-size: 16px;
            color: var(--text);
            margin: 20px 0 12px;
        }

        p {
            margin-bottom: 16px;
        }

        ul, ol {
            margin: 16px 0;
            padding-left: 24px;
        }

        li {
            margin-bottom: 8px;
        }

        .highlight-box {
            background: #eef2ff;
            border-left: 4px solid var(--accent);
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 0 8px 8px 0;
        }

        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #d97706;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 0 8px 8px 0;
        }

        .contact-info {
            background: var(--surface);
            padding: 20px;
            border-radius: 8px;
            margin-top: 24px;
        }

        a {
            color: var(--accent);
        }

        @media (max-width: 640px) {
            .content {
                padding: 24px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="/" class="logo">Partypart</a>
            <a href="javascript:history.back()" class="back-link">Tilbage</a>
        </div>
    </header>

    <div class="container">
        <h1>Vilkår og betingelser</h1>
        <p class="last-updated">Senest opdateret: <?= date('d. F Y') ?> (Version 1.0)</p>

        <div class="content">
            <h2>1. Accept af vilkår</h2>
            <p>
                Ved at oprette en konto eller bruge Partypart-platformen accepterer du disse vilkår og betingelser.
                Hvis du ikke accepterer vilkårene, må du ikke bruge vores tjenester.
            </p>

            <h2>2. Beskrivelse af tjenesten</h2>
            <p>
                Partypart er en online platform til planlægning og administration af private arrangementer såsom
                bryllupper, fødselsdage, konfirmationer og andre fejringer. Tjenesten omfatter:
            </p>
            <ul>
                <li>Oprettelse og administration af events</li>
                <li>Gæstehåndtering og RSVP-funktionalitet</li>
                <li>Digitale invitationer og kommunikation</li>
                <li>Bordplanlægning og menustyring</li>
                <li>Markedsplads for leverandører (partnere)</li>
            </ul>

            <h2>3. Brugerkonti</h2>

            <h3>3.1 Registrering</h3>
            <p>For at bruge vores tjenester skal du:</p>
            <ul>
                <li>Være mindst 18 år gammel</li>
                <li>Oprette en konto med korrekte oplysninger</li>
                <li>Holde dine loginoplysninger fortrolige</li>
            </ul>

            <h3>3.2 Ansvar for kontoen</h3>
            <p>
                Du er ansvarlig for al aktivitet på din konto. Hvis du opdager uautoriseret adgang,
                skal du straks kontakte os og ændre din adgangskode.
            </p>

            <h2>4. Acceptabel brug</h2>
            <p>Du må ikke bruge Partypart til:</p>
            <ul>
                <li>Ulovlige formål eller aktiviteter</li>
                <li>At krænke andres rettigheder</li>
                <li>At uploade skadeligt, stødende eller ulovligt indhold</li>
                <li>At spamme eller sende uønskede beskeder</li>
                <li>At forsøge at hacke eller kompromittere systemet</li>
                <li>At videresælge eller misbruge tjenesten kommercielt</li>
            </ul>

            <div class="warning-box">
                <strong>Vigtigt:</strong> Overtrædelse af disse vilkår kan medføre øjeblikkelig lukning af din konto
                uden refusion.
            </div>

            <h2>5. Brugerindhold</h2>

            <h3>5.1 Dit ansvar</h3>
            <p>
                Du er ansvarlig for alt indhold, du uploader (billeder, tekster, gæstedata). Du garanterer, at du
                har ret til at bruge og dele dette indhold.
            </p>

            <h3>5.2 Licens til Partypart</h3>
            <p>
                Ved at uploade indhold giver du Partypart en begrænset licens til at vise og behandle indholdet
                som nødvendigt for at levere tjenesten. Vi videresælger eller deler ikke dit indhold med tredjeparter.
            </p>

            <h3>5.3 Gæstedata</h3>
            <p>
                Når du tilføjer gæster til et arrangement, er du dataansvarlig for disse oplysninger.
                Du skal sikre, at du har ret til at registrere gæsternes oplysninger.
            </p>

            <h2>6. Partnere og markedsplads</h2>

            <h3>6.1 Partnerleverandører</h3>
            <p>
                Partypart formidler kontakt mellem brugere og leverandører (partnere). Vi er ikke part i aftaler
                mellem dig og leverandører og bærer ikke ansvar for leverandørernes ydelser.
            </p>

            <h3>6.2 Kommission</h3>
            <p>
                Ved bookinger gennem platformen opkræves en formidlingskommission fra leverandøren.
                Priser vist på platformen er inklusive denne kommission.
            </p>

            <h2>7. Priser og betaling</h2>

            <h3>7.1 Abonnementer</h3>
            <p>
                Visse funktioner kræver betalt abonnement. Priser fremgår af vores prisside og kan ændres
                med 30 dages varsel.
            </p>

            <h3>7.2 Fortrydelsesret</h3>
            <p>
                For digitale tjenester gælder 14 dages fortrydelsesret fra købsdato, medmindre du har
                påbegyndt brug af tjenesten.
            </p>

            <h2>8. Ansvarsbegrænsning</h2>

            <div class="highlight-box">
                <strong>Partypart leveres "som den er".</strong> Vi garanterer ikke uafbrudt eller fejlfri drift.
                Vi er ikke ansvarlige for indirekte tab, tabt indtjening eller følgeskader.
            </div>

            <p>Vores maksimale ansvar er begrænset til det beløb, du har betalt for tjenesten de seneste 12 måneder.</p>

            <h2>9. Opsigelse</h2>

            <h3>9.1 Din opsigelse</h3>
            <p>
                Du kan til enhver tid opsige din konto via Indstillinger. Ved opsigelse har du 30 dage
                til at downloade dine data, hvorefter de slettes permanent.
            </p>

            <h3>9.2 Vores opsigelse</h3>
            <p>
                Vi kan opsige eller suspendere din konto ved overtrædelse af vilkårene eller ved mistanke
                om misbrug.
            </p>

            <h2>10. Ændringer af vilkår</h2>
            <p>
                Vi kan opdatere disse vilkår. Ved væsentlige ændringer informerer vi dig mindst 30 dage
                i forvejen via email. Fortsat brug efter ændringer udgør accept.
            </p>

            <h2>11. Lovvalg og tvister</h2>
            <p>
                Disse vilkår er underlagt dansk ret. Tvister afgøres ved de danske domstole med Københavns
                Byret som værneting i første instans.
            </p>

            <h2>12. Kontakt</h2>
            <div class="contact-info">
                <p><strong>Partypart ApS</strong></p>
                <p>CVR: [Indsæt CVR-nummer]</p>
                <p>Email: support@partypart.dk</p>
                <p>
                    Se også vores <a href="/legal/privacy.php">Privatlivspolitik</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
