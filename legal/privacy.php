<?php
/**
 * Privacy Policy Page
 */
$pageTitle = 'Privatlivspolitik';

// Simple standalone page - no auth required
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privatlivspolitik - Partypart</title>
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

        .contact-info {
            background: var(--surface);
            padding: 20px;
            border-radius: 8px;
            margin-top: 24px;
        }

        .contact-info p {
            margin-bottom: 8px;
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
        <h1>Privatlivspolitik</h1>
        <p class="last-updated">Senest opdateret: <?= date('d. F Y') ?> (Version 1.0)</p>

        <div class="content">
            <h2>1. Introduktion</h2>
            <p>
                Hos Partypart tager vi dit privatliv alvorligt. Denne privatlivspolitik beskriver, hvordan vi indsamler,
                bruger og beskytter dine personoplysninger, når du bruger vores platform til at planlægge og administrere events.
            </p>

            <div class="highlight-box">
                <strong>Dataansvarlig:</strong><br>
                Partypart ApS<br>
                CVR: [Indsæt CVR-nummer]<br>
                Email: privacy@partypart.dk
            </div>

            <h2>2. Hvilke oplysninger indsamler vi?</h2>

            <h3>2.1 Kontooplysninger</h3>
            <p>Når du opretter en konto, indsamler vi:</p>
            <ul>
                <li>Navn</li>
                <li>Email-adresse</li>
                <li>Telefonnummer (valgfrit)</li>
                <li>Krypteret adgangskode</li>
            </ul>

            <h3>2.2 Event-oplysninger</h3>
            <p>Når du opretter et arrangement, gemmer vi:</p>
            <ul>
                <li>Arrangementets navn, dato og sted</li>
                <li>Gæstelister med navne, kontaktoplysninger og kostpræferencer</li>
                <li>Billeder og filer du uploader</li>
                <li>Bordplaner og menuer</li>
            </ul>

            <h3>2.3 Tekniske oplysninger</h3>
            <p>Vi indsamler automatisk:</p>
            <ul>
                <li>IP-adresser (til sikkerhed og rate limiting)</li>
                <li>Browser- og enhedsinformation</li>
                <li>Tidspunkter for login og aktivitet</li>
            </ul>

            <h2>3. Hvordan bruger vi dine oplysninger?</h2>
            <p>Vi bruger dine oplysninger til at:</p>
            <ul>
                <li>Levere og administrere vores tjenester</li>
                <li>Sende dig vigtige beskeder om dit arrangement</li>
                <li>Forbedre vores platform og brugeroplevelse</li>
                <li>Sikre mod misbrug og beskytte din konto</li>
                <li>Overholde lovmæssige forpligtelser</li>
            </ul>

            <h2>4. Retsgrundlag for behandling</h2>
            <p>Vi behandler dine personoplysninger baseret på:</p>
            <ul>
                <li><strong>Kontrakt:</strong> For at levere de tjenester, du har tilmeldt dig</li>
                <li><strong>Samtykke:</strong> For markedsføring og valgfri funktioner</li>
                <li><strong>Legitim interesse:</strong> For sikkerhed, svindelforebyggelse og forbedring af tjenesten</li>
                <li><strong>Retlig forpligtelse:</strong> For at overholde lovkrav</li>
            </ul>

            <h2>5. Deling af oplysninger</h2>
            <p>Vi deler kun dine oplysninger med:</p>
            <ul>
                <li><strong>Leverandører du vælger:</strong> Hvis du booker partnere gennem platformen</li>
                <li><strong>Tekniske underleverandører:</strong> Hosting, email-tjenester (under databehandleraftaler)</li>
                <li><strong>Myndigheder:</strong> Hvis vi er lovmæssigt forpligtet</li>
            </ul>
            <p>Vi sælger aldrig dine personoplysninger til tredjeparter.</p>

            <h2>6. Opbevaringsperiode</h2>
            <ul>
                <li><strong>Aktive konti:</strong> Så længe kontoen er aktiv</li>
                <li><strong>Gæstedata:</strong> Anonymiseres 1 år efter arrangementsdato</li>
                <li><strong>Slettede konti:</strong> Fjernes permanent efter 30 dages grace period</li>
                <li><strong>Sikkerhedslogs:</strong> Opbevares i 2 år</li>
            </ul>

            <h2>7. Dine rettigheder</h2>
            <p>Under GDPR har du ret til:</p>
            <ul>
                <li><strong>Indsigt:</strong> Få en kopi af alle dine data</li>
                <li><strong>Berigtigelse:</strong> Rette forkerte oplysninger</li>
                <li><strong>Sletning:</strong> Få dine data slettet ("ret til at blive glemt")</li>
                <li><strong>Dataportabilitet:</strong> Modtage dine data i maskinlæsbart format</li>
                <li><strong>Indsigelse:</strong> Gøre indsigelse mod visse behandlinger</li>
                <li><strong>Tilbagetrækning af samtykke:</strong> Til enhver tid</li>
            </ul>

            <div class="highlight-box">
                <strong>Sådan udøver du dine rettigheder:</strong><br>
                Log ind på din konto og gå til Indstillinger &gt; Download mine data eller Slet min konto.
                Du kan også kontakte os på privacy@partypart.dk.
            </div>

            <h2>8. Sikkerhed</h2>
            <p>Vi beskytter dine data med:</p>
            <ul>
                <li>SSL/TLS-kryptering af al datatransmission</li>
                <li>Krypterede adgangskoder (bcrypt)</li>
                <li>Regelmæssige sikkerhedsopdateringer</li>
                <li>Adgangskontrol og logning</li>
                <li>Rate limiting mod angreb</li>
            </ul>

            <h2>9. Cookies</h2>
            <p>Vi bruger kun teknisk nødvendige cookies til:</p>
            <ul>
                <li>Sessions-håndtering (login)</li>
                <li>CSRF-beskyttelse</li>
            </ul>
            <p>Vi bruger ikke tracking-cookies eller tredjepartscookies til markedsføring.</p>

            <h2>10. Børns privatliv</h2>
            <p>
                Vores tjeneste er ikke rettet mod børn under 13 år. Vi indsamler ikke bevidst
                personoplysninger fra børn under 13 år.
            </p>

            <h2>11. Ændringer til denne politik</h2>
            <p>
                Vi kan opdatere denne privatlivspolitik. Ved væsentlige ændringer vil vi informere dig
                via email eller ved login. Fortsat brug efter ændringer udgør accept af den opdaterede politik.
            </p>

            <h2>12. Kontakt og klager</h2>
            <div class="contact-info">
                <p><strong>Spørgsmål om privatliv:</strong></p>
                <p>Email: privacy@partypart.dk</p>
                <p><strong>Datatilsynet:</strong></p>
                <p>
                    Hvis du mener, vi ikke overholder reglerne, kan du klage til Datatilsynet:<br>
                    <a href="https://www.datatilsynet.dk" target="_blank">www.datatilsynet.dk</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
