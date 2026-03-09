# Referral/Agent Program + Leverandor-landingsside - Design Document

**Date:** 2026-03-08
**Status:** Approved

---

## 1. Overview

Build a referral/agent program to onboard 25-50 vendors in 3 months, plus a dedicated vendor landing page. Agents (existing vendors or invited freelancers) share unique referral links. When a referred vendor receives bookings, the agent earns 1% of the platform's commission for 12 months.

### Revenue Flow (per booking)
```
Quoted price: 4.000 kr
Depositum (25%):           1.000 kr
Platform commission (15%):   150 kr
Agent provision (1%):         10 kr  (from platform's cut)
Platform netto:              140 kr
Vendor payout (85%):         850 kr
```

### Key Rules
- Agent provision comes from the platform's cut, not the vendor's
- Provision rate is locked at onboarding time (changes don't affect existing deals)
- Provision expires 12 months after vendor registration
- Commission rate and provision rate are configurable (global default + per-entity override)
- Agents are invitation-only (activated by admin)
- Manual payout to agents initially (no Stripe Connect)

---

## 2. Database Schema

### 2.1 referral_agents

```sql
CREATE TABLE IF NOT EXISTS referral_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL UNIQUE,
    referral_code VARCHAR(20) NOT NULL UNIQUE,
    provision_rate DECIMAL(4,2) NULL COMMENT 'Override, NULL = use global default',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT COMMENT 'Admin notes about this agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_referral_code (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 referral_links

```sql
CREATE TABLE IF NOT EXISTS referral_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    vendor_id INT NOT NULL UNIQUE COMMENT 'One agent per vendor (first-touch)',
    provision_rate DECIMAL(4,2) NOT NULL COMMENT 'Locked rate at onboarding time',
    provision_until DATE NOT NULL COMMENT '12 months from vendor registration',
    total_earned DECIMAL(10,2) DEFAULT 0 COMMENT 'Denormalized running total',
    last_paid_at DATE NULL COMMENT 'Last manual payout date',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (agent_id) REFERENCES referral_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_agent (agent_id),
    INDEX idx_provision_until (provision_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 Alterations to existing tables

```sql
-- bookings: track agent provision per booking
ALTER TABLE bookings
    ADD COLUMN agent_provision_amount DECIMAL(10,2) NULL
        COMMENT 'Agent cut for this booking (from platform commission)',
    ADD COLUMN referral_link_id INT NULL,
    ADD FOREIGN KEY (referral_link_id) REFERENCES referral_links(id)
        ON DELETE SET NULL;

-- vendors: configurable commission rate per vendor
ALTER TABLE vendors
    ADD COLUMN commission_rate DECIMAL(4,2) NULL
        COMMENT 'Override, NULL = use global default';
```

### 2.4 Platform settings (config/saas.php)

```php
// Configurable rates (global defaults)
define('DEFAULT_COMMISSION_RATE', 0.15);      // 15% of depositum
define('DEFAULT_AGENT_PROVISION_RATE', 0.01); // 1% of depositum
define('AGENT_PROVISION_MONTHS', 12);         // Provision duration
```

---

## 3. Architecture

### 3.1 New Files

```
/bliv-leverandor.php                      # Vendor landing page (public)

/app/account/referrals.php                # Agent dashboard (in account panel)

/subcontractor/includes/referral-service.php  # Referral business logic

/admin-platform/agents.php                # Admin: manage agents
/admin-platform/agent-detail.php          # Admin: agent detail + provision overview

/database/migrations/022_referral_agents.sql  # Migration
```

### 3.2 Modified Files

```
/subcontractor/includes/booking-service.php   # Add agent provision calculation
/subcontractor/includes/commission-service.php # Use configurable commission rate
/subcontractor/dashboard/register.php         # Accept ref= parameter
/config/saas.php                              # Add rate defaults
/includes/admin-platform-header.php           # Add agent nav link
```

---

## 4. Business Logic (referral-service.php)

### Functions

```
createAgent(accountId, provisionRate?) -> referral_code
deactivateAgent(agentId) -> bool
getAgentByCode(code) -> agent | null
getAgentByAccountId(accountId) -> agent | null

recordReferral(agentId, vendorId) -> referral_link
getReferralsByAgent(agentId) -> [referral_links with vendor info]
getAgentDashboard(agentId) -> {stats, referrals, total_earned}

calculateAgentProvision(booking, vendorId) -> {amount, referral_link_id} | null
  - Check referral_links for vendor
  - Check provision_until >= NOW()
  - Calculate: depositum * provision_rate
  - Return amount and link ID

updateReferralEarnings(referralLinkId, amount) -> void
  - Increment total_earned on referral_links

getEffectiveCommissionRate(vendorId) -> float
  - Check vendors.commission_rate (override)
  - Fallback to DEFAULT_COMMISSION_RATE
```

---

## 5. User Flows

### 5.1 Admin Creates Agent

```
1. Admin goes to /admin-platform/agents.php
2. Clicks "Opret agent"
3. Selects an existing account (search by email/name)
4. Optionally sets custom provision rate
5. System generates unique referral_code
6. Agent is active, referral link is ready
```

### 5.2 Agent Shares Referral Link

```
1. Agent logs in to their account
2. Goes to "Referral" section in account panel
3. Sees their unique link: partyparart.dk/bliv-leverandor?ref=KODE
4. Copies and shares with vendors they know
5. Can see list of referred vendors and earned provision
```

### 5.3 Vendor Registers via Referral

```
1. Vendor clicks referral link
2. Lands on /bliv-leverandor?ref=KODE
3. Sees "Anbefalet af [Agent navn]" banner
4. Clicks "Opret din profil" -> /subcontractor/dashboard/register.php?ref=KODE
5. ref= code stored in session/cookie (survives page navigation)
6. Vendor completes registration normally
7. On successful registration:
   - Look up agent by referral_code
   - Create referral_links record with:
     - provision_rate = agent's rate or global default
     - provision_until = NOW() + 12 months
8. Vendor sees "Du er henvist af [Agent navn]" on their dashboard
```

### 5.4 Booking with Agent Provision

```
1. Booking reaches 'deposited' status (payment confirmed)
2. System checks: does this vendor have an active referral_link?
3. If yes AND provision_until >= today:
   - agent_provision = depositum * referral_links.provision_rate
   - Save on booking: agent_provision_amount, referral_link_id
   - Increment referral_links.total_earned
4. If no: agent_provision_amount = NULL (no agent involved)
5. Platform netto = commission - agent_provision
```

### 5.5 Admin Pays Agent

```
1. Admin sees provision overview in agent-detail.php
2. Filters unpaid provision (bookings since last_paid_at)
3. Transfers amount manually (bank/MobilePay)
4. Marks as paid (updates last_paid_at on referral_link)
```

---

## 6. Vendor Landing Page (/bliv-leverandor.php)

### Structure

1. **Hero section**
   - Headline: "Fa flere kunder til din virksomhed - helt gratis"
   - Subtext: "Opret en profil og bliv fundet af hundredvis af arrangoerer"
   - CTA button: "Opret din profil" -> register.php
   - If ?ref= present: "Anbefalet af [Agent navn]" banner

2. **3 fordele** (icon cards)
   - Gratis eksponering til arrangoerer der soeger leverandoerer
   - Dit eget dashboard med galleri, bookinger og anmeldelser
   - Du betaler kun naar du faar en ordre

3. **Saadan virker det** (3-step)
   - Step 1: Opret profil og tilfoj dine ydelser
   - Step 2: Modtag foresoergsler fra arrangoerer
   - Step 3: Accepter bookinger og bliv betalt via platformen

4. **Kategorier** - "Vi soeger leverandoerer inden for..."
   - Grid of 10 categories with icons from vendor_categories

5. **CTA bottom**
   - "Kom i gang paa 5 minutter" -> register.php

### Design
- Nordic palette (sage, cream, wood)
- Fraunces headings, DM Sans body
- Responsive, mobile-first
- No login required to view

---

## 7. Agent Dashboard (/app/account/referrals.php)

### Content

- **Referral link** (copyable, with copy button)
- **Stats cards** (3):
  - Antal referrede leverandoerer (godkendte)
  - Aktive leverandoerer (med mindst 1 booking)
  - Total optjent provision
- **Referral table:**
  - Leverandoernavn | Registreret | Status | Provision udloeber | Optjent

Only visible to accounts that are active agents.

---

## 8. Admin Pages

### 8.1 agents.php - Agent Overview
- List all agents with: name, email, referral_code, active referrals, total earned
- Filter by active/inactive
- Button: "Opret agent"
- Create agent modal: search account, set provision rate (optional)

### 8.2 agent-detail.php - Agent Detail
- Agent info (account, referral code, provision rate, created date)
- Referral list with vendor details
- Provision breakdown per booking
- "Marker som udbetalt" button (sets last_paid_at)
- Deactivate agent button

---

## 9. Commission Rate Changes

### Current hardcoded rates → configurable

Update `commission-service.php`:
- `calculateCommission()` accepts optional rate parameter
- Falls back to `getEffectiveCommissionRate(vendorId)` which checks vendor override then global default
- `COMMISSION_RATE` constant remains as fallback but new function preferred

Update `booking-service.php`:
- When calculating financials, use vendor's effective rate
- Include agent provision in the booking record

---

## 10. Implementation Phases

### Phase 1: Database + Service Layer
- Migration 022 (tables + alterations)
- referral-service.php (all business logic)
- Update commission-service.php for configurable rates
- Update booking-service.php for agent provision

### Phase 2: Vendor Landing Page
- /bliv-leverandor.php (public page)
- Update register.php to accept ref= parameter
- Cookie/session handling for referral code

### Phase 3: Agent Dashboard
- /app/account/referrals.php
- Link in account navigation

### Phase 4: Admin Pages
- /admin-platform/agents.php
- /admin-platform/agent-detail.php
- Nav link in admin header
- Create/deactivate agent flow

---

## 11. Decision Log

| Decision | Choice | Alternatives | Reasoning |
|----------|--------|-------------|-----------|
| Onboarding strategy | Start self, scale with agents + referrals | Only own outreach, only paid ads | Low cost, scalable |
| Agent type | Vendors + invited freelancers (invitation-only) | Open program | Quality control |
| Agent incentive | 1% of platform commission for 12 months | Fixed per signup, forever, booking count | Aligns with platform success, natural end date, covers full season |
| Provision source | From platform's cut | From vendor's cut | Vendor sees no difference |
| Rate configuration | Hybrid: global default + per-entity override | Hardcoded, full per-entity | Balance simplicity and flexibility |
| Agent login | Existing account system | Separate login, token links | No new auth system needed |
| Dashboard | Simple overview in account panel | Full dashboard, email reports | Enough for start, can expand |
| Referral transparency | Vendor sees who referred them | Hidden tracking | Builds trust |
| Payout | Manual initially | Stripe Connect | YAGNI - automate when volume requires |
| Landing page focus | Free exposure + full control | Depositum model, social proof | Removes risk for vendor |
| Rate locking | Locked at onboarding time | Dynamic (follows global rate) | Protects existing agreements |
| Attribution | First-touch (one agent per vendor) | Last-touch, multi-touch | Simple, fair, no disputes |
