#smoke test for CRUD API for following tables: authtoken, trusted contact and adherence reports

$ErrorActionPreference = "Stop"

$base = "http://localhost/mda_backend/api"

function Pass($name) {
    Write-Host "[PASS] $name" -ForegroundColor Green
}

function Fail($name, $message) {
    Write-Host "[FAIL] $name - $message" -ForegroundColor Red
    exit 1
}

function Invoke-Json {
    param(
        [string]$Uri,
        [string]$Method,
        $Body = $null
    )

    if ($null -eq $Body) {
        return Invoke-RestMethod -Uri $Uri -Method $Method
    }

    $json = $Body | ConvertTo-Json -Depth 10

    return Invoke-RestMethod `
        -Uri $Uri `
        -Method $Method `
        -ContentType "application/json" `
        -Body $json
}

Write-Host "=== Last 3 CRUD smoke test ===" -ForegroundColor Cyan

# Create disposable user through the existing endpoint.
$email = "last3-crud-$([Guid]::NewGuid().ToString('N').Substring(0,8))@example.invalid"

$user = Invoke-Json `
    "$base/users/create.php" `
    "POST" `
    @{
        fullName = "Last 3 CRUD Test User"
        email = $email
        password = "TestPassword123!"
    }

if (-not $user.success) { Fail "Create test user" $user.message }

$userId = [int]$user.data.userId
Write-Host "Created disposable user: $userId ($email)"

# -------------------------
# Adherence Reports
# -------------------------
$report = Invoke-Json `
    "$base/adherence_reports/create.php" `
    "POST" `
    @{
        userId = $userId
        periodStart = "2026-10-01"
        periodEnd = "2026-10-07"
        totalScheduled = 7
        totalTaken = 5
        totalSkipped = 1
        totalMissed = 1
        adherencePercentage = 71.43
    }

if (-not $report.success) { Fail "Adherence reports CREATE" $report.message }
$reportId = [int]$report.data.reportId
Pass "Adherence reports: CREATE"

$got = Invoke-RestMethod "$base/adherence_reports/get.php?reportId=$reportId"
if ($got.data.reportId -ne $reportId) { Fail "Adherence reports READ one" "Wrong ID" }
Pass "Adherence reports: READ one"

$list = Invoke-RestMethod "$base/adherence_reports/get.php?userId=$userId"
if ($list.data.Count -lt 1) { Fail "Adherence reports READ list" "No rows returned" }
Pass "Adherence reports: READ list"

$upd = Invoke-Json `
    "$base/adherence_reports/update.php" `
    "PUT" `
    @{
        reportId = $reportId
        totalTaken = 6
        totalMissed = 0
        adherencePercentage = 85.71
    }

if ([double]$upd.data.adherencePercentage -ne 85.71) {
    Fail "Adherence reports UPDATE" "Updated value not returned"
}
Pass "Adherence reports: UPDATE"

# -------------------------
# Auth Tokens
# -------------------------
$tokenHash = ("a" * 64)

$token = Invoke-Json `
    "$base/auth_tokens/create.php" `
    "POST" `
    @{
        userId = $userId
        tokenHash = $tokenHash
        expiresAt = "2026-12-31 23:59:59"
    }

if (-not $token.success) { Fail "Auth tokens CREATE" $token.message }
$tokenId = [int]$token.data.tokenId
Pass "Auth tokens: CREATE"

$got = Invoke-RestMethod "$base/auth_tokens/get.php?tokenId=$tokenId"
if ($got.data.tokenId -ne $tokenId) { Fail "Auth tokens READ one" "Wrong ID" }
Pass "Auth tokens: READ one"

$list = Invoke-RestMethod "$base/auth_tokens/get.php?userId=$userId"
if ($list.data.Count -lt 1) { Fail "Auth tokens READ list" "No rows returned" }
Pass "Auth tokens: READ list"

$newTokenHash = ("b" * 64)

$upd = Invoke-Json `
    "$base/auth_tokens/update.php" `
    "PUT" `
    @{
        tokenId = $tokenId
        tokenHash = $newTokenHash
        expiresAt = "2027-01-31 23:59:59"
    }

if ($upd.data.tokenHash -ne $newTokenHash) {
    Fail "Auth tokens UPDATE" "Updated hash not returned"
}
Pass "Auth tokens: UPDATE"

# -------------------------
# Trusted Contacts
# -------------------------
$contact = Invoke-Json `
    "$base/trusted_contacts/create.php" `
    "POST" `
    @{
        userId = $userId
        contactName = "CRUD Test Contact"
        contactEmail = "trusted-$([Guid]::NewGuid().ToString('N').Substring(0,8))@example.invalid"
        relationship = "Friend"
        invitationStatus = "pending"
    }

if (-not $contact.success) { Fail "Trusted contacts CREATE" $contact.message }
$contactId = [int]$contact.data.contactId
Pass "Trusted contacts: CREATE"

$got = Invoke-RestMethod "$base/trusted_contacts/get.php?contactId=$contactId"
if ($got.data.contactId -ne $contactId) { Fail "Trusted contacts READ one" "Wrong ID" }
Pass "Trusted contacts: READ one"

$list = Invoke-RestMethod "$base/trusted_contacts/get.php?userId=$userId"
if ($list.data.Count -lt 1) { Fail "Trusted contacts READ list" "No rows returned" }
Pass "Trusted contacts: READ list"

$upd = Invoke-Json `
    "$base/trusted_contacts/update.php" `
    "PUT" `
    @{
        contactId = $contactId
        contactName = "Updated Contact"
        relationship = "Sibling"
        invitationStatus = "accepted"
    }

if ($upd.data.invitationStatus -ne "accepted") {
    Fail "Trusted contacts UPDATE" "Updated status not returned"
}
Pass "Trusted contacts: UPDATE"

# -------------------------
# Delete in reverse order
# -------------------------
Invoke-Json "$base/trusted_contacts/delete.php" "DELETE" @{ contactId = $contactId } | Out-Null
Pass "Trusted contacts: DELETE"

Invoke-Json "$base/auth_tokens/delete.php" "DELETE" @{ tokenId = $tokenId } | Out-Null
Pass "Auth tokens: DELETE"

Invoke-Json "$base/adherence_reports/delete.php" "DELETE" @{ reportId = $reportId } | Out-Null
Pass "Adherence reports: DELETE"

# Verify 404 after deletes
foreach ($check in @(
    @{ Name = "Trusted contacts deletion persisted"; Url = "$base/trusted_contacts/get.php?contactId=$contactId" },
    @{ Name = "Auth tokens deletion persisted"; Url = "$base/auth_tokens/get.php?tokenId=$tokenId" },
    @{ Name = "Adherence reports deletion persisted"; Url = "$base/adherence_reports/get.php?reportId=$reportId" }
)) {
    try {
        Invoke-RestMethod $check.Url | Out-Null
        Fail $check.Name "GET unexpectedly succeeded"
    } catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 404) {
            Pass "$($check.Name) (GET -> 404)"
        } else {
            throw
        }
    }
}

Write-Host ""
Write-Host "All last-3 CRUD checks passed." -ForegroundColor Green
Write-Host "Disposable user left for manual cleanup: $userId ($email)" -ForegroundColor Yellow
